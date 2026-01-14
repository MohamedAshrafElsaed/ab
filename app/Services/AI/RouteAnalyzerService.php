<?php


namespace App\Services\AI;

use App\Models\Project;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Analyzes routes and their handlers for context retrieval.
 */
class RouteAnalyzerService
{
    private array $config;

    public function __construct()
    {
        $this->config = config('retrieval.route_analyzer', []);
    }

    /**
     * Get all routes from the knowledge base.
     *
     * @return Collection<int, array{uri: string, method: string, controller: ?string, action: ?string, name: ?string, file: string}>
     */
    public function getRoutes(Project $project): Collection
    {
        $cacheKey = $this->getCacheKey($project, 'routes');

        if (config('retrieval.cache.enabled', true)) {
            $cached = Cache::get($cacheKey);
            if ($cached !== null) {
                return collect($cached);
            }
        }

        $routesJson = $this->loadRoutesJson($project);
        if ($routesJson === null) {
            return collect();
        }

        $routes = collect();

        foreach ($routesJson['files'] ?? [] as $routeKey => $fileData) {
            foreach ($fileData['routes'] ?? [] as $route) {
                $routes->push([
                    'uri' => $route['uri'] ?? '',
                    'method' => $route['method'] ?? 'GET',
                    'controller' => $route['controller'] ?? null,
                    'action' => $route['action'] ?? null,
                    'name' => $route['name'] ?? null,
                    'file' => $fileData['file'] ?? $routeKey,
                    'type' => $route['type'] ?? 'standard',
                    'view' => $route['view'] ?? null,
                    'redirect_to' => $route['redirect_to'] ?? null,
                ]);
            }
        }

        if (config('retrieval.cache.enabled', true)) {
            $ttl = config('retrieval.cache.ttl.routes', 1800);
            Cache::put($cacheKey, $routes->toArray(), $ttl);
        }

        return $routes;
    }

    /**
     * Find the handler (controller/action) for a route pattern.
     *
     * @return array{controller: string, action: ?string, file: string, route: array}|null
     */
    public function findHandler(Project $project, string $routePattern): ?array
    {
        $routes = $this->getRoutes($project);
        $normalizedPattern = $this->normalizeRoutePattern($routePattern);

        // Find best matching route
        $bestMatch = null;
        $bestScore = 0;

        foreach ($routes as $route) {
            $score = $this->calculateRouteMatchScore($normalizedPattern, $route['uri']);
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestMatch = $route;
            }
        }

        if ($bestMatch === null || empty($bestMatch['controller'])) {
            return null;
        }

        return [
            'controller' => $bestMatch['controller'],
            'action' => $bestMatch['action'],
            'file' => $this->resolveControllerPath($bestMatch['controller']),
            'route' => $bestMatch,
        ];
    }

    /**
     * Get all files involved in handling a route.
     *
     * @return array{
     *     controller: ?string,
     *     request: ?string,
     *     resource: ?string,
     *     model: ?string,
     *     view: ?string,
     *     page: ?string,
     *     route_file: string,
     *     related: array<string>
     * }
     */
    public function getRouteStack(Project $project, string $routePattern): array
    {
        $handler = $this->findHandler($project, $routePattern);
        $stack = [
            'controller' => null,
            'request' => null,
            'resource' => null,
            'model' => null,
            'view' => null,
            'page' => null,
            'route_file' => '',
            'related' => [],
        ];

        if ($handler === null) {
            // Try to find the route file at least
            $routes = $this->getRoutes($project);
            $normalizedPattern = $this->normalizeRoutePattern($routePattern);

            foreach ($routes as $route) {
                if ($this->calculateRouteMatchScore($normalizedPattern, $route['uri']) > 0.5) {
                    $stack['route_file'] = 'routes/' . $route['file'];
                    if (!empty($route['view'])) {
                        $stack['view'] = $this->resolveViewPath($route['view']);
                    }
                    break;
                }
            }

            return $stack;
        }

        $stack['controller'] = $handler['file'];
        $stack['route_file'] = 'routes/' . $handler['route']['file'];

        // Infer related files from controller name
        $controllerName = $handler['controller'];
        $baseName = $this->extractBaseNameFromController($controllerName);

        if ($baseName) {
            $patterns = $this->config['handler_patterns'] ?? [];

            // Form Request
            $requestPath = sprintf($patterns['request'] ?? 'app/Http/Requests/%sRequest.php', $baseName);
            if ($this->fileExistsInProject($project, $requestPath)) {
                $stack['request'] = $requestPath;
            }

            // API Resource
            $resourcePath = sprintf($patterns['resource'] ?? 'app/Http/Resources/%sResource.php', $baseName);
            if ($this->fileExistsInProject($project, $resourcePath)) {
                $stack['resource'] = $resourcePath;
            }

            // Model
            $modelPath = sprintf($patterns['model'] ?? 'app/Models/%s.php', $baseName);
            if ($this->fileExistsInProject($project, $modelPath)) {
                $stack['model'] = $modelPath;
            }

            // View (Blade)
            $viewName = strtolower($baseName);
            $action = $handler['action'] ?? 'index';
            $viewPath = sprintf($patterns['view'] ?? 'resources/views/%s.blade.php', "{$viewName}/{$action}");
            if ($this->fileExistsInProject($project, $viewPath)) {
                $stack['view'] = $viewPath;
            }

            // Inertia Page (Vue)
            $pagePath = sprintf($patterns['page'] ?? 'resources/js/Pages/%s.vue', "{$baseName}/{$this->ucfirstAction($action)}");
            if ($this->fileExistsInProject($project, $pagePath)) {
                $stack['page'] = $pagePath;
            }
        }

        // Find related files from same domain
        $stack['related'] = $this->findRelatedFiles($project, $baseName ?? '', $stack);

        return $stack;
    }

    /**
     * Match a natural language description to likely routes.
     *
     * @return Collection<int, array{route: array, score: float, reason: string}>
     */
    public function matchDescriptionToRoutes(Project $project, string $description): Collection
    {
        $routes = $this->getRoutes($project);
        $keywords = $this->extractKeywordsFromDescription($description);
        $matches = collect();

        foreach ($routes as $route) {
            $score = 0.0;
            $reasons = [];

            // Check URI segments
            $uriSegments = array_filter(explode('/', $route['uri']));
            foreach ($keywords as $keyword) {
                foreach ($uriSegments as $segment) {
                    if (stripos($segment, $keyword) !== false) {
                        $score += 0.3;
                        $reasons[] = "URI contains '{$keyword}'";
                    }
                }
            }

            // Check controller name
            if (!empty($route['controller'])) {
                $controllerName = strtolower($route['controller']);
                foreach ($keywords as $keyword) {
                    if (stripos($controllerName, $keyword) !== false) {
                        $score += 0.4;
                        $reasons[] = "Controller matches '{$keyword}'";
                    }
                }
            }

            // Check route name
            if (!empty($route['name'])) {
                $routeName = strtolower($route['name']);
                foreach ($keywords as $keyword) {
                    if (stripos($routeName, $keyword) !== false) {
                        $score += 0.3;
                        $reasons[] = "Route name matches '{$keyword}'";
                    }
                }
            }

            // Check HTTP method relevance
            $method = strtoupper($route['method']);
            if ($this->methodMatchesDescription($method, $description)) {
                $score += 0.2;
                $reasons[] = "HTTP method '{$method}' matches intent";
            }

            if ($score > 0) {
                $matches->push([
                    'route' => $route,
                    'score' => min(1.0, $score),
                    'reason' => implode('; ', array_unique($reasons)),
                ]);
            }
        }

        return $matches
            ->sortByDesc('score')
            ->take(10)
            ->values();
    }

    /**
     * Get routes grouped by domain/feature.
     *
     * @return Collection<string, Collection>
     */
    public function getRoutesByDomain(Project $project): Collection
    {
        $routes = $this->getRoutes($project);

        return $routes->groupBy(function ($route) {
            // Group by first URI segment or controller namespace
            $segments = array_filter(explode('/', $route['uri']));
            $firstSegment = $segments[0] ?? 'root';

            // Normalize common patterns
            if (str_starts_with($firstSegment, 'api')) {
                return 'api';
            }

            return $firstSegment;
        });
    }

    /**
     * Invalidate route cache for a project.
     */
    public function invalidateCache(Project $project): void
    {
        $cacheKey = $this->getCacheKey($project, 'routes');
        Cache::forget($cacheKey);
    }

    /**
     * Load routes.json from the project's knowledge base.
     *
     * @return array<string, mixed>|null
     */
    private function loadRoutesJson(Project $project): ?array
    {
        $routesPath = $project->knowledge_path . '/routes.json';

        if (!file_exists($routesPath)) {
            Log::debug('RouteAnalyzerService: routes.json not found', [
                'project_id' => $project->id,
                'path' => $routesPath,
            ]);
            return null;
        }

        $content = file_get_contents($routesPath);
        if ($content === false) {
            return null;
        }

        return json_decode($content, true);
    }

    /**
     * Normalize a route pattern for matching.
     */
    private function normalizeRoutePattern(string $pattern): string
    {
        $pattern = trim($pattern, '/');
        $pattern = strtolower($pattern);

        // Remove HTTP method prefix if present
        $pattern = preg_replace('/^(get|post|put|patch|delete)\s+/i', '', $pattern);

        return $pattern;
    }

    /**
     * Calculate match score between a pattern and a route URI.
     */
    private function calculateRouteMatchScore(string $pattern, string $uri): float
    {
        $uri = strtolower(trim($uri, '/'));
        $pattern = strtolower(trim($pattern, '/'));

        // Exact match
        if ($uri === $pattern) {
            return 1.0;
        }

        // Pattern matches start of URI
        if (str_starts_with($uri, $pattern)) {
            return 0.9;
        }

        // URI matches start of pattern
        if (str_starts_with($pattern, $uri)) {
            return 0.8;
        }

        // Contains match
        if (str_contains($uri, $pattern) || str_contains($pattern, $uri)) {
            return 0.6;
        }

        // Segment-based matching
        $patternSegments = array_filter(explode('/', $pattern));
        $uriSegments = array_filter(explode('/', $uri));

        // Replace route parameters with wildcards
        $uriSegments = array_map(function ($s) {
            return str_starts_with($s, '{') ? '*' : $s;
        }, $uriSegments);

        $matches = 0;
        $total = max(count($patternSegments), count($uriSegments));

        if ($total === 0) {
            return 0;
        }

        foreach ($patternSegments as $segment) {
            if (in_array($segment, $uriSegments) || in_array('*', $uriSegments)) {
                $matches++;
            }
        }

        return $matches / $total * 0.5;
    }

    /**
     * Resolve a controller class name to a file path.
     */
    private function resolveControllerPath(string $controller): string
    {
        // Handle namespace format: App\Http\Controllers\UserController
        if (str_contains($controller, '\\')) {
            $path = str_replace('\\', '/', $controller);
            $path = str_replace('App/', 'app/', $path);
            return $path . '.php';
        }

        // Simple name: UserController
        return 'app/Http/Controllers/' . $controller . '.php';
    }

    /**
     * Extract base model/resource name from controller.
     */
    private function extractBaseNameFromController(string $controller): ?string
    {
        // Get just the class name
        $className = class_basename(str_replace('/', '\\', $controller));

        // Remove 'Controller' suffix
        if (str_ends_with($className, 'Controller')) {
            $baseName = substr($className, 0, -10);

            // Handle API prefix
            if (str_starts_with($baseName, 'Api')) {
                $baseName = substr($baseName, 3);
            }

            return $baseName ?: null;
        }

        return null;
    }

    /**
     * Resolve a view name to file path.
     */
    private function resolveViewPath(string $viewName): string
    {
        $viewPath = str_replace('.', '/', $viewName);
        return 'resources/views/' . $viewPath . '.blade.php';
    }

    /**
     * Convert action name to ucfirst for Inertia pages.
     */
    private function ucfirstAction(string $action): string
    {
        return ucfirst($action);
    }

    /**
     * Check if a file exists in the project.
     */
    private function fileExistsInProject(Project $project, string $path): bool
    {
        return file_exists($project->repo_path . '/' . $path);
    }

    /**
     * Find related files based on naming conventions.
     *
     * @param array<string, ?string> $stack
     * @return array<string>
     */
    private function findRelatedFiles(Project $project, string $baseName, array $stack): array
    {
        $related = [];

        if (empty($baseName)) {
            return $related;
        }

        // Look for related controllers in the same domain
        $controllerDir = 'app/Http/Controllers';
        $repoPath = $project->repo_path;

        if (is_dir($repoPath . '/' . $controllerDir)) {
            $files = glob($repoPath . '/' . $controllerDir . '/*' . $baseName . '*.php') ?: [];
            foreach ($files as $file) {
                $relativePath = str_replace($repoPath . '/', '', $file);
                if (!in_array($relativePath, $stack)) {
                    $related[] = $relativePath;
                }
            }
        }

        // Look for related Inertia components
        $pagesDir = 'resources/js/Pages/' . $baseName;
        if (is_dir($repoPath . '/' . $pagesDir)) {
            $files = glob($repoPath . '/' . $pagesDir . '/*.vue') ?: [];
            foreach ($files as $file) {
                $relativePath = str_replace($repoPath . '/', '', $file);
                if (!in_array($relativePath, $stack)) {
                    $related[] = $relativePath;
                }
            }
        }

        return array_slice($related, 0, 5);
    }

    /**
     * Extract relevant keywords from a description.
     *
     * @return array<string>
     */
    private function extractKeywordsFromDescription(string $description): array
    {
        $stopWords = [
            'the', 'a', 'an', 'is', 'are', 'was', 'were', 'how', 'what', 'where',
            'when', 'why', 'which', 'does', 'do', 'did', 'can', 'could', 'would',
            'should', 'this', 'that', 'these', 'those', 'in', 'on', 'at', 'to',
            'for', 'of', 'with', 'by', 'from', 'and', 'or', 'but', 'not', 'it',
            'page', 'route', 'endpoint', 'api', 'url', 'path', 'handler',
        ];

        $words = preg_split('/[\s\-_\/]+/', strtolower($description));
        $keywords = array_filter($words, fn($w) => strlen($w) > 2 && !in_array($w, $stopWords));

        return array_values(array_unique($keywords));
    }

    /**
     * Check if HTTP method matches the description intent.
     */
    private function methodMatchesDescription(string $method, string $description): bool
    {
        $description = strtolower($description);

        $methodKeywords = [
            'GET' => ['get', 'show', 'view', 'list', 'fetch', 'retrieve', 'display'],
            'POST' => ['create', 'add', 'new', 'submit', 'post', 'store'],
            'PUT' => ['update', 'edit', 'modify', 'change', 'put'],
            'PATCH' => ['update', 'patch', 'modify', 'partial'],
            'DELETE' => ['delete', 'remove', 'destroy', 'erase'],
        ];

        $keywords = $methodKeywords[$method] ?? [];

        foreach ($keywords as $keyword) {
            if (str_contains($description, $keyword)) {
                return true;
            }
        }

        return false;
    }

    private function getCacheKey(Project $project, string $type): string
    {
        $prefix = config('retrieval.cache.prefix', 'retrieval');
        $scanId = $project->last_kb_scan_id ?? 'none';
        return "{$prefix}:{$type}:{$project->id}:{$scanId}";
    }
}
