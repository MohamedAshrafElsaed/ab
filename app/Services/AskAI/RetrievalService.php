<?php

namespace App\Services\AskAI;

use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\ProjectFileChunk;
use App\Services\AskAI\DTO\RetrievedChunk;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class RetrievalService
{
    private SensitiveContentRedactor $redactor;
    private array $config;

    public function __construct(SensitiveContentRedactor $redactor)
    {
        $this->redactor = $redactor;
        $this->config = config('askai.retrieval', []);
    }

    /**
     * Retrieve relevant chunks for a question.
     *
     * @return array{chunks: RetrievedChunk[], total_length: int, files_searched: int}
     */
    public function retrieve(Project $project, string $question, string $depth = 'quick'): array
    {
        $maxChunks = $depth === 'deep'
            ? (int) ($this->config['max_chunks'] ?? 50) * 1.5
            : (int) ($this->config['max_chunks'] ?? 50);

        $maxContentLength = (int) ($this->config['max_content_length'] ?? 80000);

        // Extract keywords and intent from question
        $queryAnalysis = $this->analyzeQuery($question, $project);

        // Get candidate chunks with scores
        $candidates = $this->getCandidateChunks($project, $queryAnalysis, $maxChunks * 3);

        // Score and rank candidates
        $scoredChunks = $this->scoreChunks($candidates, $queryAnalysis, $project);

        // Select diverse top chunks within content limit
        $selectedChunks = $this->selectDiverseChunks($scoredChunks, $maxChunks, $maxContentLength);

        // Load content for selected chunks
        $chunksWithContent = $this->loadChunkContent($project, $selectedChunks);

        // Redact sensitive content
        $redactedChunks = $this->redactChunks($chunksWithContent);

        $totalLength = array_sum(array_map(fn($c) => $c->getContentLength(), $redactedChunks));

        return [
            'chunks' => $redactedChunks,
            'total_length' => $totalLength,
            'files_searched' => count(array_unique(array_map(fn($c) => $c->path, $redactedChunks))),
            'query_analysis' => $queryAnalysis,
        ];
    }

    /**
     * Analyze the query to extract keywords, file patterns, and intent.
     */
    private function analyzeQuery(string $question, Project $project): array
    {
        $question = strtolower($question);

        // Extract explicit file/path mentions
        $pathPatterns = [];
        preg_match_all('/(?:in|from|file|path|class|controller|model|view|component)\s+[`\'"]?([a-zA-Z0-9_\/\.\-]+)[`\'"]?/i', $question, $pathMatches);
        if (!empty($pathMatches[1])) {
            $pathPatterns = array_map('trim', $pathMatches[1]);
        }

        // Extract symbol mentions (function names, class names)
        $symbolPatterns = [];
        preg_match_all('/(?:function|method|class|trait|interface)\s+[`\'"]?(\w+)[`\'"]?/i', $question, $symbolMatches);
        if (!empty($symbolMatches[1])) {
            $symbolPatterns = $symbolMatches[1];
        }
        // Also catch camelCase/PascalCase words that look like code identifiers
        preg_match_all('/\b([A-Z][a-z]+(?:[A-Z][a-z]+)+)\b/', $question, $pascalMatches);
        preg_match_all('/\b([a-z]+(?:[A-Z][a-z]+)+)\b/', $question, $camelMatches);
        $symbolPatterns = array_merge($symbolPatterns, $pascalMatches[1] ?? [], $camelMatches[1] ?? []);

        // Detect route/endpoint mentions
        $routePatterns = [];
        $isRouteQuery = preg_match('/\b(route|endpoint|api|url|uri|path)\b/i', $question);
        preg_match_all('/(?:\/[a-z0-9\-_\/{}]+)/i', $question, $routeMatches);
        if (!empty($routeMatches[0])) {
            $routePatterns = $routeMatches[0];
        }
        // Also look for HTTP methods
        $httpMethods = [];
        if (preg_match('/\b(GET|POST|PUT|PATCH|DELETE)\b/i', $question, $httpMatch)) {
            $httpMethods[] = strtoupper($httpMatch[1]);
        }

        // Extract general keywords (removing stop words)
        $stopWords = ['the', 'a', 'an', 'is', 'are', 'was', 'were', 'how', 'what', 'where', 'when', 'why', 'which',
            'does', 'do', 'did', 'can', 'could', 'would', 'should', 'this', 'that', 'these', 'those',
            'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by', 'from', 'and', 'or', 'but', 'not', 'it', 'its'];
        $words = preg_split('/\s+/', preg_replace('/[^\w\s]/', ' ', $question));
        $keywords = array_filter($words, fn($w) => strlen($w) > 2 && !in_array(strtolower($w), $stopWords));

        // Detect stack-specific terms
        $stackTerms = $this->detectStackTerms($question, $project);

        // Detect authentication/middleware related queries
        $isAuthQuery = preg_match('/\b(auth|login|logout|session|guard|middleware|permission|role|gate|policy)\b/i', $question);

        // Detect database/model queries
        $isDbQuery = preg_match('/\b(database|migration|model|eloquent|query|table|column|relation|foreign|schema)\b/i', $question);

        return [
            'original' => $question,
            'keywords' => array_values(array_unique($keywords)),
            'path_patterns' => array_unique($pathPatterns),
            'symbol_patterns' => array_unique($symbolPatterns),
            'route_patterns' => $routePatterns,
            'http_methods' => $httpMethods,
            'stack_terms' => $stackTerms,
            'is_route_query' => $isRouteQuery,
            'is_auth_query' => $isAuthQuery,
            'is_db_query' => $isDbQuery,
        ];
    }

    private function detectStackTerms(string $question, Project $project): array
    {
        $terms = [];
        $stack = $project->stack_info ?? [];

        $stackKeywords = [
            'laravel' => ['laravel', 'eloquent', 'blade', 'artisan', 'facade', 'service provider'],
            'vue' => ['vue', 'component', 'composable', 'reactive', 'ref', 'computed'],
            'inertia' => ['inertia', 'page', 'form', 'router', 'visit'],
            'livewire' => ['livewire', 'wire:', 'mount', 'emit'],
            'react' => ['react', 'hook', 'useState', 'useEffect', 'jsx', 'tsx'],
            'tailwind' => ['tailwind', 'class=', 'className'],
        ];

        foreach ($stackKeywords as $tech => $keywords) {
            foreach ($keywords as $keyword) {
                if (stripos($question, $keyword) !== false) {
                    $terms[] = $tech;
                    break;
                }
            }
        }

        // Add terms from project stack
        if (isset($stack['framework'])) {
            $terms[] = strtolower($stack['framework']);
        }
        if (isset($stack['frontend'])) {
            $terms = array_merge($terms, array_map('strtolower', $stack['frontend']));
        }

        return array_unique($terms);
    }

    /**
     * Get candidate chunks from database.
     */
    private function getCandidateChunks(Project $project, array $queryAnalysis, int $limit): Collection
    {
        $chunks = collect();

        // 1. Direct path matches
        if (!empty($queryAnalysis['path_patterns'])) {
            foreach ($queryAnalysis['path_patterns'] as $pattern) {
                $pathChunks = ProjectFileChunk::where('project_id', $project->id)
                    ->where(function ($q) use ($pattern) {
                        $q->where('path', 'LIKE', "%{$pattern}%")
                            ->orWhere('path', 'LIKE', "%{$pattern}");
                    })
                    ->limit($limit)
                    ->get();
                $chunks = $chunks->merge($pathChunks);
            }
        }

        // 2. Symbol matches
        if (!empty($queryAnalysis['symbol_patterns'])) {
            foreach ($queryAnalysis['symbol_patterns'] as $symbol) {
                $symbolChunks = ProjectFileChunk::where('project_id', $project->id)
                    ->where(function ($q) use ($symbol) {
                        $q->whereJsonContains('symbols_declared', $symbol)
                            ->orWhereJsonContains('symbols_used', $symbol);
                    })
                    ->limit($limit)
                    ->get();
                $chunks = $chunks->merge($symbolChunks);
            }
        }

        // 3. Import matches
        if (!empty($queryAnalysis['symbol_patterns'])) {
            $importChunks = ProjectFileChunk::where('project_id', $project->id)
                ->where(function ($q) use ($queryAnalysis) {
                    foreach ($queryAnalysis['symbol_patterns'] as $symbol) {
                        $q->orWhereRaw("JSON_SEARCH(imports, 'one', ?) IS NOT NULL", ["%{$symbol}%"]);
                    }
                })
                ->limit($limit)
                ->get();
            $chunks = $chunks->merge($importChunks);
        }

        // 4. Route-aware lookup
        if ($queryAnalysis['is_route_query'] || !empty($queryAnalysis['route_patterns'])) {
            $routeChunks = $this->getRouteRelatedChunks($project, $queryAnalysis, $limit);
            $chunks = $chunks->merge($routeChunks);
        }

        // 5. Auth-related files
        if ($queryAnalysis['is_auth_query']) {
            $authChunks = ProjectFileChunk::where('project_id', $project->id)
                ->where(function ($q) {
                    $q->where('path', 'LIKE', '%Auth%')
                        ->orWhere('path', 'LIKE', '%auth%')
                        ->orWhere('path', 'LIKE', '%Middleware%')
                        ->orWhere('path', 'LIKE', '%Guard%')
                        ->orWhere('path', 'LIKE', '%Policy%');
                })
                ->limit($limit)
                ->get();
            $chunks = $chunks->merge($authChunks);
        }

        // 6. Database/model related
        if ($queryAnalysis['is_db_query']) {
            $dbChunks = ProjectFileChunk::where('project_id', $project->id)
                ->where(function ($q) {
                    $q->where('path', 'LIKE', '%Model%')
                        ->orWhere('path', 'LIKE', '%models%')
                        ->orWhere('path', 'LIKE', '%migration%')
                        ->orWhere('path', 'LIKE', '%database%');
                })
                ->limit($limit)
                ->get();
            $chunks = $chunks->merge($dbChunks);
        }

        // 7. Keyword fallback - search in paths
        if ($chunks->count() < $limit / 2 && !empty($queryAnalysis['keywords'])) {
            foreach (array_slice($queryAnalysis['keywords'], 0, 5) as $keyword) {
                $keywordChunks = ProjectFileChunk::where('project_id', $project->id)
                    ->where('path', 'LIKE', "%{$keyword}%")
                    ->limit(10)
                    ->get();
                $chunks = $chunks->merge($keywordChunks);
            }
        }

        // 8. Stack-aware path boosting
        $stackPaths = $this->getStackPaths($project);
        if (!empty($stackPaths) && $chunks->count() < $limit) {
            foreach ($stackPaths as $stackPath) {
                $stackChunks = ProjectFileChunk::where('project_id', $project->id)
                    ->where('path', 'LIKE', "{$stackPath}%")
                    ->limit(10)
                    ->get();
                $chunks = $chunks->merge($stackChunks);
            }
        }

        return $chunks->unique('id');
    }

    private function getRouteRelatedChunks(Project $project, array $queryAnalysis, int $limit): Collection
    {
        // First check for routes.json in knowledge base
        $routesJson = $this->loadRoutesJson($project);
        $controllerPaths = [];

        if ($routesJson && !empty($queryAnalysis['route_patterns'])) {
            foreach ($queryAnalysis['route_patterns'] as $routePattern) {
                $pattern = trim($routePattern, '/');
                foreach ($routesJson['files'] ?? [] as $file => $data) {
                    foreach ($data['routes'] ?? [] as $route) {
                        $uri = trim($route['uri'] ?? '', '/');
                        if (str_contains($uri, $pattern) || str_contains($pattern, $uri)) {
                            if (isset($route['controller'])) {
                                $controllerPaths[] = str_replace('\\', '/', $route['controller']);
                            }
                        }
                    }
                }
            }
        }

        // Get chunks from route files and controllers
        return ProjectFileChunk::where('project_id', $project->id)
            ->where(function ($q) use ($controllerPaths) {
                $q->where('path', 'LIKE', 'routes/%')
                    ->orWhere('path', 'LIKE', '%routes.php');
                foreach ($controllerPaths as $controller) {
                    $q->orWhere('path', 'LIKE', "%{$controller}%");
                }
            })
            ->limit($limit)
            ->get();
    }

    private function loadRoutesJson(Project $project): ?array
    {
        $routesPath = $project->knowledge_path . '/routes.json';
        if (!file_exists($routesPath)) {
            return null;
        }

        $cacheKey = "routes_json_{$project->id}";
        return Cache::remember($cacheKey, 3600, function () use ($routesPath) {
            return json_decode(file_get_contents($routesPath), true);
        });
    }

    private function getStackPaths(Project $project): array
    {
        $stack = $project->stack_info ?? [];
        $paths = [];
        $stackPathConfig = $this->config['stack_paths'] ?? [];

        if (isset($stack['framework']) && isset($stackPathConfig[strtolower($stack['framework'])])) {
            $paths = array_merge($paths, $stackPathConfig[strtolower($stack['framework'])]);
        }

        foreach ($stack['frontend'] ?? [] as $frontend) {
            if (isset($stackPathConfig[strtolower($frontend)])) {
                $paths = array_merge($paths, $stackPathConfig[strtolower($frontend)]);
            }
        }

        return array_unique($paths);
    }

    /**
     * Score chunks based on relevance.
     */
    private function scoreChunks(Collection $chunks, array $queryAnalysis, Project $project): array
    {
        $boost = $this->config['boost'] ?? [];
        $scored = [];

        foreach ($chunks as $chunk) {
            $score = 0.0;
            $matchedKeywords = [];

            $path = strtolower($chunk->path);
            $symbolsDeclared = $chunk->symbols_declared ?? [];
            $imports = $chunk->imports ?? [];

            // Path matching
            foreach ($queryAnalysis['path_patterns'] as $pattern) {
                if (strcasecmp(basename($chunk->path), $pattern) === 0) {
                    $score += $boost['exact_path_match'] ?? 10.0;
                    $matchedKeywords[] = "path:{$pattern}";
                } elseif (str_contains($path, strtolower($pattern))) {
                    $score += $boost['path_contains'] ?? 5.0;
                    $matchedKeywords[] = "path:{$pattern}";
                }
            }

            // Symbol matching
            foreach ($queryAnalysis['symbol_patterns'] as $symbol) {
                if (in_array($symbol, $symbolsDeclared)) {
                    $score += $boost['symbol_match'] ?? 8.0;
                    $matchedKeywords[] = "symbol:{$symbol}";
                }
            }

            // Import matching
            foreach ($queryAnalysis['symbol_patterns'] as $symbol) {
                foreach ($imports as $import) {
                    if (str_contains($import, $symbol)) {
                        $score += $boost['import_match'] ?? 6.0;
                        $matchedKeywords[] = "import:{$symbol}";
                        break;
                    }
                }
            }

            // Keyword matching in path
            foreach ($queryAnalysis['keywords'] as $keyword) {
                if (str_contains($path, strtolower($keyword))) {
                    $score += $boost['content_keyword'] ?? 3.0;
                    $matchedKeywords[] = "keyword:{$keyword}";
                }
            }

            // Route-related boost
            if ($queryAnalysis['is_route_query'] && str_contains($path, 'route')) {
                $score += $boost['route_match'] ?? 7.0;
            }

            // Stack-aware boosting
            $stackPaths = $this->getStackPaths($project);
            foreach ($stackPaths as $stackPath) {
                if (str_starts_with($path, strtolower($stackPath))) {
                    $score += $boost['framework_hint'] ?? 2.0;
                    break;
                }
            }

            // Prefer complete files for understanding context
            if ($chunk->is_complete_file) {
                $score *= 1.1;
            }

            // Penalize very large chunks (they may be less focused)
            $lineCount = $chunk->end_line - $chunk->start_line;
            if ($lineCount > 300) {
                $score *= 0.9;
            }

            if ($score > 0) {
                $scored[] = [
                    'chunk' => $chunk,
                    'score' => $score,
                    'matched_keywords' => array_unique($matchedKeywords),
                ];
            }
        }

        // Sort by score descending
        usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);

        return $scored;
    }

    /**
     * Select diverse chunks from different files.
     */
    private function selectDiverseChunks(array $scoredChunks, int $maxChunks, int $maxContentLength): array
    {
        $selected = [];
        $fileChunkCounts = [];
        $totalLength = 0;
        $minDiverseFiles = $this->config['min_diverse_files'] ?? 3;
        $maxPerFile = max(3, (int) ceil($maxChunks / $minDiverseFiles));

        foreach ($scoredChunks as $item) {
            $chunk = $item['chunk'];
            $path = $chunk->path;

            // Limit chunks per file for diversity
            $fileChunkCounts[$path] = ($fileChunkCounts[$path] ?? 0) + 1;
            if ($fileChunkCounts[$path] > $maxPerFile) {
                continue;
            }

            // Estimate content length (actual length loaded later)
            $estimatedLength = ($chunk->end_line - $chunk->start_line + 1) * 80;
            if ($totalLength + $estimatedLength > $maxContentLength) {
                continue;
            }

            $selected[] = $item;
            $totalLength += $estimatedLength;

            if (count($selected) >= $maxChunks) {
                break;
            }
        }

        return $selected;
    }

    /**
     * Load actual content for selected chunks.
     *
     * @return RetrievedChunk[]
     */
    private function loadChunkContent(Project $project, array $selectedChunks): array
    {
        $result = [];

        foreach ($selectedChunks as $item) {
            $chunk = $item['chunk'];
            $content = $this->getChunkContentFromRepo($project, $chunk);

            if ($content === null) {
                continue;
            }

            $file = ProjectFile::where('project_id', $project->id)
                ->where('path', $chunk->path)
                ->first();

            $result[] = RetrievedChunk::fromDatabaseRow(
                array_merge($chunk->toArray(), ['language' => $file?->language]),
                $content,
                $item['score'],
                $item['matched_keywords']
            );
        }

        return $result;
    }

    private function getChunkContentFromRepo(Project $project, $chunk): ?string
    {
        $fullPath = $project->repo_path . '/' . $chunk->path;

        if (!file_exists($fullPath)) {
            return null;
        }

        $content = @file_get_contents($fullPath);
        if ($content === false) {
            return null;
        }

        $lines = explode("\n", $content);
        $startLine = max(1, $chunk->start_line);
        $endLine = min(count($lines), $chunk->end_line);

        if ($startLine > count($lines)) {
            return null;
        }

        $chunkLines = array_slice($lines, $startLine - 1, $endLine - $startLine + 1);
        return implode("\n", $chunkLines);
    }

    /**
     * Redact sensitive content from chunks.
     *
     * @param RetrievedChunk[] $chunks
     * @return RetrievedChunk[]
     */
    private function redactChunks(array $chunks): array
    {
        return array_map(function (RetrievedChunk $chunk) {
            $redactedContent = $this->redactor->redact($chunk->content, $chunk->path);

            if ($redactedContent === $chunk->content) {
                return $chunk;
            }

            return new RetrievedChunk(
                chunkId: $chunk->chunkId,
                path: $chunk->path,
                startLine: $chunk->startLine,
                endLine: $chunk->endLine,
                sha1: $chunk->sha1,
                content: $redactedContent,
                relevanceScore: $chunk->relevanceScore,
                matchedKeywords: $chunk->matchedKeywords,
                symbolsDeclared: $chunk->symbolsDeclared,
                imports: $chunk->imports,
                language: $chunk->language,
                isCompleteFile: $chunk->isCompleteFile,
            );
        }, $chunks);
    }
}
