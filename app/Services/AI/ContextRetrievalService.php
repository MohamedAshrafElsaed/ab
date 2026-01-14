<?php

namespace App\Services\AI;

use App\DTOs\RetrievalResult;
use App\Enums\IntentType;
use App\Models\IntentAnalysis;
use App\Models\Project;
use App\Models\ProjectFileChunk;
use App\Services\AskAI\DTO\RetrievedChunk;
use App\Services\AskAI\SensitiveContentRedactor;
use App\Services\Projects\KnowledgeBaseReader;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Enhanced knowledge retrieval for agent context.
 *
 * Provides intelligent context selection based on:
 * - User intent and request
 * - File dependencies (imports, references)
 * - Route awareness (for web features)
 * - Tech stack patterns
 * - Symbol relationships
 */
class ContextRetrievalService
{
    private array $config;
    private array $scoringWeights;

    public function __construct(
        private readonly SymbolGraphService $symbolGraph,
        private readonly RouteAnalyzerService $routeAnalyzer,
        private readonly SensitiveContentRedactor $redactor,
    ) {
        $this->config = config('retrieval', []);
        $this->scoringWeights = $this->config['scoring']['weights'] ?? [
            'keyword_match' => 0.25,
            'file_type_relevance' => 0.20,
            'domain_match' => 0.20,
            'dependency_proximity' => 0.15,
            'route_relevance' => 0.10,
            'symbol_match' => 0.10,
        ];
    }

    /**
     * Main retrieval method - get relevant chunks for a request.
     *
     * @param array{max_chunks?: int, token_budget?: int, include_dependencies?: bool, depth?: int} $options
     */
    public function retrieve(
        Project $project,
        IntentAnalysis $intent,
        string $userMessage,
        array $options = []
    ): RetrievalResult {
        $startTime = microtime(true);

        $maxChunks = $options['max_chunks'] ?? $this->config['max_chunks'] ?? 50;
        $tokenBudget = $options['token_budget'] ?? $this->config['max_token_budget'] ?? 100000;
        $includeDependencies = $options['include_dependencies'] ?? true;
        $depth = $options['depth'] ?? $this->config['default_dependency_depth'] ?? 2;

        try {
            // Step 1: Identify entry points
            $entryPoints = $this->identifyEntryPoints($project, $intent, $userMessage);

            Log::debug('ContextRetrieval: Entry points identified', [
                'project_id' => $project->id,
                'entry_points' => $entryPoints,
            ]);

            // Step 2: Get candidate chunks from multiple sources
            $candidates = $this->gatherCandidates($project, $intent, $userMessage, $entryPoints, $maxChunks * 3);

            if ($candidates->isEmpty()) {
                return RetrievalResult::empty('No relevant code found for this request.');
            }

            // Step 3: Score and rank candidates
            $scored = $this->rankChunks($candidates, $intent, $entryPoints);

            // Step 4: Select diverse top chunks
            $selectedChunks = $this->selectDiverseChunks($scored, $maxChunks);

            // Step 5: Expand with dependencies if requested
            $dependencies = [];
            if ($includeDependencies && !empty($entryPoints)) {
                $dependencies = $this->expandDependencies($project, $entryPoints, $depth);
            }

            // Step 6: Get related routes
            $relatedRoutes = $this->getRelatedRoutes($project, $intent, $userMessage);

            // Step 7: Load content and redact sensitive data
            $chunksWithContent = $this->loadAndRedactContent($project, $selectedChunks);

            // Step 8: Build file list
            $files = $this->buildFileList($chunksWithContent);

            $duration = (microtime(true) - $startTime) * 1000;

            $result = new RetrievalResult(
                chunks: $chunksWithContent,
                files: $files,
                entryPoints: $entryPoints,
                dependencies: $dependencies,
                relatedRoutes: $relatedRoutes,
                metadata: [
                    'project_id' => $project->id,
                    'intent_type' => $intent->intent_type->value,
                    'user_message' => substr($userMessage, 0, 100),
                    'candidates_found' => $candidates->count(),
                    'chunks_selected' => $chunksWithContent->count(),
                    'retrieval_time_ms' => round($duration, 2),
                    'options' => $options,
                ],
            );

            // Apply token budget limit
            if ($tokenBudget > 0) {
                $result = $result->limitToTokenBudget($tokenBudget);
            }

            Log::info('ContextRetrieval: Complete', [
                'project_id' => $project->id,
                'chunks' => $result->getChunkCount(),
                'files' => $result->getFileCount(),
                'estimated_tokens' => $result->getTotalTokenEstimate(),
                'duration_ms' => round($duration, 2),
            ]);

            return $result;

        } catch (Exception $e) {
            Log::error('ContextRetrieval: Failed', [
                'project_id' => $project->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return RetrievalResult::empty('Retrieval error: ' . $e->getMessage());
        }
    }

    /**
     * Keyword-based search across chunks.
     *
     * @param array<string> $keywords
     * @return Collection<int, ProjectFileChunk>
     */
    public function searchByKeywords(Project $project, array $keywords): Collection
    {
        if (empty($keywords)) {
            return collect();
        }

        $chunks = collect();
        $limit = ($this->config['max_chunks'] ?? 50) * 2;

        foreach ($keywords as $keyword) {
            if (strlen($keyword) < 2) {
                continue;
            }

            // Search in paths
            $pathChunks = ProjectFileChunk::where('project_id', $project->id)
                ->where('path', 'LIKE', "%{$keyword}%")
                ->limit($limit)
                ->get();
            $chunks = $chunks->merge($pathChunks);

            // Search in symbols
            $symbolChunks = ProjectFileChunk::where('project_id', $project->id)
                ->where(function ($q) use ($keyword) {
                    $q->whereJsonContains('symbols_declared', $keyword)
                        ->orWhereRaw("JSON_SEARCH(symbols_declared, 'one', ?) IS NOT NULL", ["%{$keyword}%"]);
                })
                ->limit($limit)
                ->get();
            $chunks = $chunks->merge($symbolChunks);
        }

        return $chunks->unique('id');
    }

    /**
     * Find files related to a specific route/URL.
     *
     * @return Collection<int, ProjectFileChunk>
     */
    public function findByRoute(Project $project, string $routePattern): Collection
    {
        $routeStack = $this->routeAnalyzer->getRouteStack($project, $routePattern);
        $filePaths = array_filter([
            $routeStack['controller'],
            $routeStack['request'],
            $routeStack['resource'],
            $routeStack['model'],
            $routeStack['view'],
            $routeStack['page'],
            $routeStack['route_file'],
        ]);

        $filePaths = array_merge($filePaths, $routeStack['related'] ?? []);

        if (empty($filePaths)) {
            return collect();
        }

        return ProjectFileChunk::where('project_id', $project->id)
            ->where(function ($q) use ($filePaths) {
                foreach ($filePaths as $path) {
                    $q->orWhere('path', 'LIKE', "%{$path}%");
                }
            })
            ->get();
    }

    /**
     * Follow the dependency graph from entry points.
     *
     * @param array<string> $entryFiles
     * @return Collection<int, ProjectFileChunk>
     */
    public function expandDependencies(Project $project, array $entryFiles, int $depth = 2): array
    {
        if (empty($entryFiles)) {
            return [];
        }

        $depth = min($depth, $this->config['max_dependency_depth'] ?? 5);
        $dependencies = [];

        foreach ($entryFiles as $entryFile) {
            $tree = $this->symbolGraph->getDependencyTree($project, $entryFile, $depth);

            foreach ($tree as $path => $info) {
                if (!isset($dependencies[$path])) {
                    $dependencies[$path] = $info;
                }
            }
        }

        return $dependencies;
    }

    /**
     * Get files by domain/area of the codebase.
     *
     * @return Collection<int, ProjectFileChunk>
     */
    public function findByDomain(Project $project, string $domain): Collection
    {
        $domainPaths = $this->config['domain_paths'][$domain] ?? [];

        if (empty($domainPaths)) {
            return collect();
        }

        $limit = $this->config['max_chunks'] ?? 50;

        return ProjectFileChunk::where('project_id', $project->id)
            ->where(function ($q) use ($domainPaths) {
                foreach ($domainPaths as $path) {
                    $pathPattern = str_replace('*', '%', $path);
                    $q->orWhere('path', 'LIKE', $pathPattern . '%');
                }
            })
            ->limit($limit * 2)
            ->get();
    }

    /**
     * Score and rank chunks by relevance.
     *
     * @param Collection<int, ProjectFileChunk> $chunks
     * @return Collection<int, array{chunk: ProjectFileChunk, score: float, matched: array<string>}>
     */
    public function rankChunks(Collection $chunks, IntentAnalysis $intent, array $entryPoints = []): Collection
    {
        $keywords = $this->extractKeywords($intent);

        return $chunks->map(function (ProjectFileChunk $chunk) use ($intent, $keywords, $entryPoints) {
            $score = $this->calculateRelevanceScore($chunk, $intent, $keywords, $entryPoints);

            return [
                'chunk' => $chunk,
                'score' => $score['total'],
                'matched' => $score['matched'],
                'breakdown' => $score['breakdown'],
            ];
        })
            ->filter(fn($item) => $item['score'] > 0.1)
            ->sortByDesc('score')
            ->values();
    }

    /**
     * Identify main entry points for a task.
     *
     * @return array<string>
     */
    private function identifyEntryPoints(Project $project, IntentAnalysis $intent, string $userMessage): array
    {
        $entryPoints = [];

        // From explicitly mentioned files
        $mentionedFiles = $intent->extracted_entities['files'] ?? [];
        foreach ($mentionedFiles as $file) {
            $found = $this->findMatchingFile($project, $file);
            if ($found) {
                $entryPoints[] = $found;
            }
        }

        // From route patterns
        if ($this->isRouteRelated($intent, $userMessage)) {
            $routeMatches = $this->routeAnalyzer->matchDescriptionToRoutes($project, $userMessage);

            foreach ($routeMatches->take(3) as $match) {
                $handler = $this->routeAnalyzer->findHandler($project, $match['route']['uri']);
                if ($handler && !empty($handler['file'])) {
                    $entryPoints[] = $handler['file'];
                }
            }
        }

        // From domain classification
        $primaryDomain = $intent->domain_classification['primary'] ?? 'general';
        if ($primaryDomain !== 'general') {
            $domainChunks = $this->findByDomain($project, $primaryDomain);
            $domainFiles = $domainChunks->pluck('path')->unique()->take(3);
            $entryPoints = array_merge($entryPoints, $domainFiles->toArray());
        }

        // From mentioned symbols
        $symbols = $intent->extracted_entities['symbols'] ?? [];
        foreach (array_slice($symbols, 0, 5) as $symbol) {
            $symbolFiles = $this->symbolGraph->findBySymbol($project, $symbol);
            if (!empty($symbolFiles['declares'])) {
                $entryPoints = array_merge($entryPoints, array_slice($symbolFiles['declares'], 0, 2));
            }
        }

        return array_unique(array_slice($entryPoints, 0, 10));
    }

    /**
     * Gather candidate chunks from multiple sources.
     *
     * @param array<string> $entryPoints
     * @return Collection<int, ProjectFileChunk>
     */
    private function gatherCandidates(
        Project $project,
        IntentAnalysis $intent,
        string $userMessage,
        array $entryPoints,
        int $limit
    ): Collection {
        $candidates = collect();

        // 1. Chunks from entry point files
        foreach ($entryPoints as $entryPoint) {
            $chunks = ProjectFileChunk::where('project_id', $project->id)
                ->where('path', $entryPoint)
                ->get();
            $candidates = $candidates->merge($chunks);
        }

        // 2. Keyword-based search
        $keywords = $this->extractKeywords($intent);
        if (!empty($keywords)) {
            $keywordChunks = $this->searchByKeywords($project, $keywords);
            $candidates = $candidates->merge($keywordChunks);
        }

        // 3. Domain-specific files
        $primaryDomain = $intent->domain_classification['primary'] ?? 'general';
        if ($primaryDomain !== 'general') {
            $domainChunks = $this->findByDomain($project, $primaryDomain);
            $candidates = $candidates->merge($domainChunks);
        }

        // 4. Route-related files
        if ($this->isRouteRelated($intent, $userMessage)) {
            $routeMatches = $this->routeAnalyzer->matchDescriptionToRoutes($project, $userMessage);
            foreach ($routeMatches->take(3) as $match) {
                $routeChunks = $this->findByRoute($project, $match['route']['uri']);
                $candidates = $candidates->merge($routeChunks);
            }
        }

        // 5. Symbol-based lookup
        $symbols = $intent->extracted_entities['symbols'] ?? [];
        foreach (array_slice($symbols, 0, 5) as $symbol) {
            $symbolChunks = ProjectFileChunk::where('project_id', $project->id)
                ->where(function ($q) use ($symbol) {
                    $q->whereJsonContains('symbols_declared', $symbol)
                        ->orWhereJsonContains('symbols_used', $symbol);
                })
                ->limit(20)
                ->get();
            $candidates = $candidates->merge($symbolChunks);
        }

        // 6. Stack-aware paths
        $stackPaths = $this->getStackPaths($project);
        if (!empty($stackPaths) && $candidates->count() < $limit) {
            foreach ($stackPaths as $stackPath) {
                $stackChunks = ProjectFileChunk::where('project_id', $project->id)
                    ->where('path', 'LIKE', $stackPath . '%')
                    ->limit(20)
                    ->get();
                $candidates = $candidates->merge($stackChunks);
            }
        }

        // 7. Intent-based file types
        $fileTypes = $this->getRelevantFileTypes($intent->intent_type);
        foreach ($fileTypes['primary'] as $type) {
            $typeChunks = ProjectFileChunk::where('project_id', $project->id)
                ->where('path', 'LIKE', "%{$type}%")
                ->limit(10)
                ->get();
            $candidates = $candidates->merge($typeChunks);
        }

        return $candidates->unique('id')->take($limit);
    }

    /**
     * Calculate comprehensive relevance score.
     *
     * @param array<string> $keywords
     * @param array<string> $entryPoints
     * @return array{total: float, matched: array<string>, breakdown: array<string, float>}
     */
    private function calculateRelevanceScore(
        ProjectFileChunk $chunk,
        IntentAnalysis $intent,
        array $keywords,
        array $entryPoints
    ): array {
        $breakdown = [];
        $matched = [];
        $boost = $this->config['scoring']['boost'] ?? [];
        $path = strtolower($chunk->path);

        // 1. Keyword match score
        $keywordScore = 0.0;
        foreach ($keywords as $keyword) {
            if (str_contains($path, strtolower($keyword))) {
                $keywordScore += $boost['content_keyword'] ?? 3.0;
                $matched[] = "keyword:{$keyword}";
            }
        }
        $breakdown['keyword'] = min(1.0, $keywordScore / 10);

        // 2. File type relevance
        $fileTypeScore = $this->calculateFileTypeRelevance($chunk, $intent);
        $breakdown['file_type'] = $fileTypeScore;

        // 3. Domain match
        $domainScore = $this->calculateDomainMatchScore($chunk, $intent);
        $breakdown['domain'] = $domainScore;
        if ($domainScore > 0.5) {
            $matched[] = "domain:{$intent->domain_classification['primary']}";
        }

        // 4. Dependency proximity (entry point or dependency)
        $dependencyScore = 0.0;
        if (in_array($chunk->path, $entryPoints)) {
            $dependencyScore = 1.0;
            $matched[] = 'entry_point';
        }
        $breakdown['dependency'] = $dependencyScore;

        // 5. Route relevance
        $routeScore = $this->calculateRouteRelevanceScore($chunk, $intent);
        $breakdown['route'] = $routeScore;
        if ($routeScore > 0.5) {
            $matched[] = 'route_related';
        }

        // 6. Symbol match
        $symbolScore = $this->calculateSymbolMatchScore($chunk, $intent);
        $breakdown['symbol'] = $symbolScore;

        // Calculate weighted total
        $total = 0.0;
        foreach ($this->scoringWeights as $key => $weight) {
            $scoreKey = str_replace('_match', '', str_replace('_relevance', '', str_replace('_proximity', '', $key)));
            $total += ($breakdown[$scoreKey] ?? 0) * $weight;
        }

        // Apply boost multipliers
        if ($chunk->is_complete_file) {
            $total *= 1.1;
        }

        // Penalize very large chunks
        $lineCount = $chunk->end_line - $chunk->start_line;
        if ($lineCount > 300) {
            $total *= 0.9;
        }

        return [
            'total' => min(1.0, $total),
            'matched' => array_unique($matched),
            'breakdown' => $breakdown,
        ];
    }

    /**
     * Select diverse chunks from different files.
     *
     * @param Collection<int, array{chunk: ProjectFileChunk, score: float, matched: array}> $scored
     * @return Collection<int, array{chunk: ProjectFileChunk, score: float, matched: array}>
     */
    private function selectDiverseChunks(Collection $scored, int $maxChunks): Collection
    {
        $selected = collect();
        $fileChunkCounts = [];
        $minDiverseFiles = 3;
        $maxPerFile = max(3, (int) ceil($maxChunks / $minDiverseFiles));

        foreach ($scored as $item) {
            $path = $item['chunk']->path;

            $fileChunkCounts[$path] = ($fileChunkCounts[$path] ?? 0) + 1;
            if ($fileChunkCounts[$path] > $maxPerFile) {
                continue;
            }

            $selected->push($item);

            if ($selected->count() >= $maxChunks) {
                break;
            }
        }

        return $selected;
    }

    /**
     * Load content for chunks and redact sensitive data.
     *
     * @param Collection<int, array{chunk: ProjectFileChunk, score: float, matched: array}> $selected
     * @return Collection<int, RetrievedChunk>
     */
    private function loadAndRedactContent(Project $project, Collection $selected): Collection
    {
        $result = collect();

        foreach ($selected as $item) {
            $chunk = $item['chunk'];
            $content = $this->getChunkContent($project, $chunk);

            if ($content === null) {
                continue;
            }

            // Redact sensitive content
            $redactedContent = $this->redactor->redact($content, $chunk->path);

            $result->push(new RetrievedChunk(
                chunkId: $chunk->chunk_id,
                path: $chunk->path,
                startLine: $chunk->start_line,
                endLine: $chunk->end_line,
                sha1: $chunk->sha1 ?? '',
                content: $redactedContent,
                relevanceScore: $item['score'],
                matchedKeywords: $item['matched'],
                symbolsDeclared: $chunk->symbols_declared ?? [],
                imports: $chunk->imports ?? [],
                language: $this->detectLanguage($chunk->path),
                isCompleteFile: (bool) $chunk->is_complete_file,
            ));
        }

        return $result;
    }

    /**
     * Get routes related to the intent/message.
     *
     * @return array<array{uri: string, method: string, controller: ?string, name: ?string}>
     */
    private function getRelatedRoutes(Project $project, IntentAnalysis $intent, string $userMessage): array
    {
        if (!$this->isRouteRelated($intent, $userMessage)) {
            return [];
        }

        $matches = $this->routeAnalyzer->matchDescriptionToRoutes($project, $userMessage);

        return $matches->take(5)->map(fn($m) => $m['route'])->toArray();
    }

    /**
     * Build file list from chunks.
     *
     * @param Collection<int, RetrievedChunk> $chunks
     * @return Collection<int, array{path: string, language: string, relevance: float}>
     */
    private function buildFileList(Collection $chunks): Collection
    {
        $files = [];

        foreach ($chunks as $chunk) {
            $path = $chunk->path;
            if (!isset($files[$path])) {
                $files[$path] = [
                    'path' => $path,
                    'language' => $chunk->language ?? 'plaintext',
                    'relevance' => $chunk->relevanceScore,
                ];
            } else {
                $files[$path]['relevance'] = max($files[$path]['relevance'], $chunk->relevanceScore);
            }
        }

        return collect(array_values($files))
            ->sortByDesc('relevance')
            ->values();
    }

    /**
     * Extract keywords from intent analysis.
     *
     * @return array<string>
     */
    private function extractKeywords(IntentAnalysis $intent): array
    {
        $keywords = [];

        // From entities
        $keywords = array_merge($keywords, $intent->extracted_entities['files'] ?? []);
        $keywords = array_merge($keywords, $intent->extracted_entities['components'] ?? []);
        $keywords = array_merge($keywords, $intent->extracted_entities['features'] ?? []);
        $keywords = array_merge($keywords, $intent->extracted_entities['symbols'] ?? []);

        // From domain
        $keywords[] = $intent->domain_classification['primary'] ?? 'general';
        $keywords = array_merge($keywords, $intent->domain_classification['secondary'] ?? []);

        return array_unique(array_filter($keywords, fn($k) => strlen($k) > 2));
    }

    /**
     * Check if intent is route-related.
     */
    private function isRouteRelated(IntentAnalysis $intent, string $userMessage): bool
    {
        // Keywords that directly imply routes
        $routeKeywords = [
            'route', 'endpoint', 'api', 'url', 'path', 'controller', 'page',
            'form', 'submit', 'redirect', 'request', 'response',
        ];

        // Keywords that imply specific route features
        $featureKeywords = [
            'login', 'logout', 'register', 'signup', 'signin', 'signout',
            'password', 'reset', 'forgot', 'verify', 'confirm',
            'dashboard', 'profile', 'settings', 'admin',
        ];

        $message = strtolower($userMessage);

        foreach ($routeKeywords as $keyword) {
            if (str_contains($message, $keyword)) {
                return true;
            }
        }

        foreach ($featureKeywords as $keyword) {
            if (str_contains($message, $keyword)) {
                return true;
            }
        }

        // Domains that typically involve routes
        $routeRelatedDomains = ['api', 'ui', 'routing', 'auth', 'users'];
        $domain = $intent->domain_classification['primary'] ?? '';

        if (in_array($domain, $routeRelatedDomains)) {
            return true;
        }

        // Intent types that typically involve routes
        $routeRelatedIntents = [
            IntentType::FeatureRequest,
            IntentType::BugFix,
            IntentType::UiComponent,
        ];

        return in_array($intent->intent_type, $routeRelatedIntents);
    }

    /**
     * Get file types relevant for an intent.
     *
     * @return array{primary: array<string>, secondary: array<string>}
     */
    private function getRelevantFileTypes(IntentType $intentType): array
    {
        $mapping = $this->config['intent_file_types'] ?? [];
        return $mapping[$intentType->value] ?? ['primary' => [], 'secondary' => []];
    }

    /**
     * Get stack-aware priority paths.
     *
     * @return array<string>
     */
    private function getStackPaths(Project $project): array
    {
        $stack = $project->stack_info ?? [];
        $stackPathConfig = $this->config['stack_paths'] ?? [];
        $paths = [];

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
     * Calculate file type relevance for intent.
     */
    private function calculateFileTypeRelevance(ProjectFileChunk $chunk, IntentAnalysis $intent): float
    {
        $fileTypes = $this->getRelevantFileTypes($intent->intent_type);
        $path = $chunk->path;
        $boost = $this->config['scoring']['boost'] ?? [];

        foreach ($fileTypes['primary'] as $type) {
            if (str_contains($path, $type)) {
                return 1.0;
            }
        }

        foreach ($fileTypes['secondary'] as $type) {
            if (str_contains($path, $type)) {
                return 0.6;
            }
        }

        return 0.0;
    }

    /**
     * Calculate domain match score.
     */
    private function calculateDomainMatchScore(ProjectFileChunk $chunk, IntentAnalysis $intent): float
    {
        $domain = $intent->domain_classification['primary'] ?? 'general';
        $domainPaths = $this->config['domain_paths'][$domain] ?? [];

        foreach ($domainPaths as $domainPath) {
            $pattern = str_replace('*', '', $domainPath);
            if (str_contains($chunk->path, $pattern)) {
                return 1.0;
            }
        }

        // Check secondary domains
        foreach ($intent->domain_classification['secondary'] ?? [] as $secondary) {
            $secondaryPaths = $this->config['domain_paths'][$secondary] ?? [];
            foreach ($secondaryPaths as $path) {
                $pattern = str_replace('*', '', $path);
                if (str_contains($chunk->path, $pattern)) {
                    return 0.5;
                }
            }
        }

        return 0.0;
    }

    /**
     * Calculate route relevance score.
     */
    private function calculateRouteRelevanceScore(ProjectFileChunk $chunk, IntentAnalysis $intent): float
    {
        $routePatterns = ['routes/', 'Controller', 'Request', 'Resource'];
        $path = $chunk->path;

        foreach ($routePatterns as $pattern) {
            if (str_contains($path, $pattern)) {
                return 0.5;
            }
        }

        return 0.0;
    }

    /**
     * Calculate symbol match score.
     */
    private function calculateSymbolMatchScore(ProjectFileChunk $chunk, IntentAnalysis $intent): float
    {
        $symbols = $intent->extracted_entities['symbols'] ?? [];
        if (empty($symbols)) {
            return 0.0;
        }

        $declared = $chunk->symbols_declared ?? [];
        $declaredNames = array_map(function ($s) {
            return is_array($s) ? ($s['name'] ?? '') : $s;
        }, $declared);

        $matches = 0;
        foreach ($symbols as $symbol) {
            if (in_array($symbol, $declaredNames)) {
                $matches++;
            }
        }

        return min(1.0, $matches / count($symbols));
    }

    /**
     * Find matching file path in project.
     */
    private function findMatchingFile(Project $project, string $fileHint): ?string
    {
        // Try exact match first
        $chunk = ProjectFileChunk::where('project_id', $project->id)
            ->where('path', 'LIKE', "%{$fileHint}%")
            ->first();

        return $chunk?->path;
    }

    /**
     * Get chunk content from repository.
     */
    private function getChunkContent(Project $project, ProjectFileChunk $chunk): ?string
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
     * Detect language from file path.
     */
    private function detectLanguage(string $path): string
    {
        $extension = pathinfo($path, PATHINFO_EXTENSION);

        return match ($extension) {
            'php' => 'php',
            'js' => 'javascript',
            'ts' => 'typescript',
            'vue' => 'vue',
            'jsx' => 'jsx',
            'tsx' => 'tsx',
            'css' => 'css',
            'scss', 'sass' => 'scss',
            'json' => 'json',
            'md' => 'markdown',
            'yaml', 'yml' => 'yaml',
            'blade.php' => 'blade',
            default => 'plaintext',
        };
    }
}
