<?php

namespace App\Services\Projects;

use App\Models\Project;
use Illuminate\Support\Facades\Log;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class RoutesExtractor
{
    /**
     * Extract all routes from the routes/ directory recursively
     */
    public function extract(Project $project): array
    {
        $routesDir = $project->repo_path . '/routes';

        Log::debug('RoutesExtractor: Checking routes directory', [
            'project_id' => $project->id,
            'routes_dir' => $routesDir,
            'exists' => is_dir($routesDir),
        ]);

        if (!is_dir($routesDir)) {
            Log::warning('RoutesExtractor: Routes directory does not exist', [
                'project_id' => $project->id,
                'routes_dir' => $routesDir,
            ]);
            return [];
        }

        $routes = [];
        $filesFound = [];

        // Recursively scan routes directory
        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(
                    $routesDir,
                    RecursiveDirectoryIterator::SKIP_DOTS
                ),
                RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach ($iterator as $file) {
                /** @var SplFileInfo $file */
                if (!$file->isFile()) {
                    continue;
                }

                // Only process PHP files
                if (strtolower($file->getExtension()) !== 'php') {
                    continue;
                }

                $fullPath = $file->getPathname();
                $relativePath = $this->getRelativePath($routesDir, $fullPath);
                $filesFound[] = $relativePath;

                // Read file content
                $content = @file_get_contents($fullPath);
                if ($content === false) {
                    Log::warning('RoutesExtractor: Could not read file', [
                        'file' => $fullPath,
                    ]);
                    continue;
                }

                // Extract route key (e.g., "web", "api", "api/v1/users")
                $routeKey = $this->pathToKey($relativePath);

                $routes[$routeKey] = [
                    'file' => $relativePath,
                    'routes' => $this->extractRouteInfo($content),
                    'middleware_groups' => $this->extractMiddlewareGroups($content),
                    'route_groups' => $this->extractRouteGroups($content),
                ];
            }
        } catch (\Exception $e) {
            Log::error('RoutesExtractor: Error scanning routes directory', [
                'project_id' => $project->id,
                'error' => $e->getMessage(),
            ]);
        }

        Log::debug('RoutesExtractor: Scan complete', [
            'project_id' => $project->id,
            'files_found' => $filesFound,
            'route_keys' => array_keys($routes),
        ]);

        // Sort by key for consistent output
        ksort($routes);

        return $routes;
    }

    /**
     * Save routes to JSON file
     */
    public function save(Project $project): array
    {
        $routes = $this->extract($project);

        $routesPath = $project->knowledge_path . '/routes.json';

        Log::debug('RoutesExtractor: Saving routes file', [
            'project_id' => $project->id,
            'path' => $routesPath,
            'routes_count' => count($routes),
        ]);

        // Ensure knowledge directory exists
        if (!is_dir($project->knowledge_path)) {
            mkdir($project->knowledge_path, 0755, true);
        }

        // Always create the file, even if empty
        $data = [
            'extracted_at' => now()->toIso8601String(),
            'total_files' => count($routes),
            'total_routes' => $this->countTotalRoutes($routes),
            'files' => $routes,
        ];

        $written = file_put_contents($routesPath, json_encode($data, JSON_PRETTY_PRINT));

        if ($written === false) {
            Log::error('RoutesExtractor: Failed to write routes.json', [
                'project_id' => $project->id,
                'path' => $routesPath,
            ]);
        } else {
            Log::info('RoutesExtractor: Successfully saved routes.json', [
                'project_id' => $project->id,
                'path' => $routesPath,
                'bytes_written' => $written,
                'total_files' => count($routes),
                'total_routes' => $this->countTotalRoutes($routes),
            ]);
        }

        return $routes;
    }

    /**
     * Extract route definitions from file content
     */
    private function extractRouteInfo(string $content): array
    {
        $routes = [];

        // Extract standard routes: Route::get, Route::post, etc.
        preg_match_all(
            '/Route::(get|post|put|patch|delete|options|any)\s*\(\s*[\'"]([^\'"]+)[\'"]/i',
            $content,
            $matches,
            PREG_SET_ORDER | PREG_OFFSET_CAPTURE
        );

        foreach ($matches as $match) {
            $method = strtoupper($match[1][0]);
            $uri = $match[2][0];
            $position = $match[0][1];

            // Try to find route name after this match
            $name = $this->findRouteName($content, $position);

            // Try to find controller
            $controller = $this->findController($content, $position);

            $route = [
                'method' => $method,
                'uri' => $uri,
            ];

            if ($name) {
                $route['name'] = $name;
            }

            if ($controller) {
                $route['controller'] = $controller['class'];
                if (isset($controller['method'])) {
                    $route['action'] = $controller['method'];
                }
            }

            $routes[] = $route;
        }

        // Extract Route::match
        preg_match_all(
            '/Route::match\s*\(\s*\[([^\]]+)\]\s*,\s*[\'"]([^\'"]+)[\'"]/i',
            $content,
            $matchMatches,
            PREG_SET_ORDER | PREG_OFFSET_CAPTURE
        );

        foreach ($matchMatches as $match) {
            $methods = $this->parseMethodsArray($match[1][0]);
            $uri = $match[2][0];
            $position = $match[0][1];
            $name = $this->findRouteName($content, $position);

            $route = [
                'method' => implode('|', $methods),
                'uri' => $uri,
            ];

            if ($name) {
                $route['name'] = $name;
            }

            $routes[] = $route;
        }

        // Extract Route::resource and apiResource
        preg_match_all(
            '/Route::(resource|apiResource)\s*\(\s*[\'"]([^\'"]+)[\'"]\s*,\s*([^\)\]]+)/i',
            $content,
            $resourceMatches,
            PREG_SET_ORDER
        );

        foreach ($resourceMatches as $match) {
            $type = $match[1];
            $uri = $match[2];
            $controller = trim($match[3], " \t\n\r\0\x0B,");

            $routes[] = [
                'method' => $type === 'apiResource' ? 'API_RESOURCE' : 'RESOURCE',
                'uri' => $uri,
                'controller' => $this->cleanControllerName($controller),
                'type' => $type,
            ];
        }

        // Extract Route::view
        preg_match_all(
            '/Route::view\s*\(\s*[\'"]([^\'"]+)[\'"]\s*,\s*[\'"]([^\'"]+)[\'"]/i',
            $content,
            $viewMatches,
            PREG_SET_ORDER
        );

        foreach ($viewMatches as $match) {
            $routes[] = [
                'method' => 'GET',
                'uri' => $match[1],
                'view' => $match[2],
                'type' => 'view',
            ];
        }

        // Extract Route::redirect
        preg_match_all(
            '/Route::redirect\s*\(\s*[\'"]([^\'"]+)[\'"]\s*,\s*[\'"]([^\'"]+)[\'"]/i',
            $content,
            $redirectMatches,
            PREG_SET_ORDER
        );

        foreach ($redirectMatches as $match) {
            $routes[] = [
                'method' => 'GET',
                'uri' => $match[1],
                'redirect_to' => $match[2],
                'type' => 'redirect',
            ];
        }

        return $routes;
    }

    /**
     * Extract middleware groups defined in the file
     */
    private function extractMiddlewareGroups(string $content): array
    {
        $groups = [];

        // Match ->middleware('name') or ->middleware(['name1', 'name2'])
        preg_match_all(
            '/->middleware\s*\(\s*(\[[^\]]+\]|[\'"][^\'"]+[\'"])\s*\)/i',
            $content,
            $matches
        );

        foreach ($matches[1] as $match) {
            if (str_starts_with($match, '[')) {
                // Array of middleware
                preg_match_all('/[\'"]([^\'"]+)[\'"]/', $match, $middlewareMatches);
                foreach ($middlewareMatches[1] as $mw) {
                    $groups[$mw] = ($groups[$mw] ?? 0) + 1;
                }
            } else {
                // Single middleware
                $mw = trim($match, '\'"');
                $groups[$mw] = ($groups[$mw] ?? 0) + 1;
            }
        }

        return $groups;
    }

    /**
     * Extract route groups (prefix, name prefix, etc.)
     */
    private function extractRouteGroups(string $content): array
    {
        $groups = [];

        // Match Route::prefix('...')
        preg_match_all(
            '/Route::prefix\s*\(\s*[\'"]([^\'"]+)[\'"]\s*\)/i',
            $content,
            $prefixMatches
        );
        if (!empty($prefixMatches[1])) {
            $groups['prefixes'] = array_values(array_unique($prefixMatches[1]));
        }

        // Match Route::name('...')
        preg_match_all(
            '/Route::name\s*\(\s*[\'"]([^\'"]+)[\'"]\s*\)/i',
            $content,
            $nameMatches
        );
        if (!empty($nameMatches[1])) {
            $groups['name_prefixes'] = array_values(array_unique($nameMatches[1]));
        }

        // Match Route::group with attributes
        preg_match_all(
            '/Route::group\s*\(\s*\[([^\]]+)\]/i',
            $content,
            $groupMatches
        );

        if (!empty($groupMatches[1])) {
            $groupAttributes = [];
            foreach ($groupMatches[1] as $attrs) {
                // Extract prefix from group
                if (preg_match('/[\'"]prefix[\'"]\s*=>\s*[\'"]([^\'"]+)[\'"]/', $attrs, $m)) {
                    $groupAttributes['prefixes'][] = $m[1];
                }
                // Extract middleware from group
                if (preg_match('/[\'"]middleware[\'"]\s*=>\s*[\'"]([^\'"]+)[\'"]/', $attrs, $m)) {
                    $groupAttributes['middleware'][] = $m[1];
                }
                // Extract namespace from group
                if (preg_match('/[\'"]namespace[\'"]\s*=>\s*[\'"]([^\'"]+)[\'"]/', $attrs, $m)) {
                    $groupAttributes['namespaces'][] = $m[1];
                }
            }

            foreach ($groupAttributes as $key => $values) {
                $groups[$key] = array_values(array_unique(array_merge($groups[$key] ?? [], $values)));
            }
        }

        return $groups;
    }

    /**
     * Find route name near a position in content
     */
    private function findRouteName(string $content, int $position): ?string
    {
        // Look for ->name('...') within next 500 characters
        $searchArea = substr($content, $position, 500);

        // Stop at next Route:: call or semicolon
        $endPositions = [];
        if (($p = strpos($searchArea, 'Route::')) !== false) {
            $endPositions[] = $p;
        }
        if (($p = strpos($searchArea, ';')) !== false) {
            $endPositions[] = $p;
        }

        if (!empty($endPositions)) {
            $searchArea = substr($searchArea, 0, min($endPositions));
        }

        if (preg_match('/->name\s*\(\s*[\'"]([^\'"]+)[\'"]\s*\)/', $searchArea, $match)) {
            return $match[1];
        }

        return null;
    }

    /**
     * Find controller near a position in content
     */
    private function findController(string $content, int $position): ?array
    {
        $searchArea = substr($content, $position, 300);

        // Match [Controller::class, 'method']
        if (preg_match('/\[\s*([A-Za-z0-9_\\\\]+)::class\s*,\s*[\'"]([^\'"]+)[\'"]\s*\]/', $searchArea, $match)) {
            return [
                'class' => $this->cleanControllerName($match[1]),
                'method' => $match[2],
            ];
        }

        // Match Controller::class (invokable)
        if (preg_match('/,\s*([A-Za-z0-9_\\\\]+)::class\s*\)/', $searchArea, $match)) {
            return [
                'class' => $this->cleanControllerName($match[1]),
            ];
        }

        // Match 'Controller@method' string syntax
        if (preg_match('/,\s*[\'"]([A-Za-z0-9_\\\\]+)@([^\'"]+)[\'"]/', $searchArea, $match)) {
            return [
                'class' => $match[1],
                'method' => $match[2],
            ];
        }

        return null;
    }

    /**
     * Parse methods array string like "'GET', 'POST'"
     */
    private function parseMethodsArray(string $methodsString): array
    {
        preg_match_all('/[\'"]([^\'"]+)[\'"]/', $methodsString, $matches);
        return array_map('strtoupper', $matches[1]);
    }

    /**
     * Clean controller name
     */
    private function cleanControllerName(string $controller): string
    {
        $controller = trim($controller);

        // Remove ::class suffix if present
        $controller = preg_replace('/::class$/', '', $controller);

        return $controller;
    }

    /**
     * Get relative path from base directory
     */
    private function getRelativePath(string $basePath, string $fullPath): string
    {
        $basePath = rtrim(str_replace('\\', '/', realpath($basePath) ?: $basePath), '/');
        $fullPath = str_replace('\\', '/', realpath($fullPath) ?: $fullPath);

        if (str_starts_with($fullPath, $basePath . '/')) {
            return substr($fullPath, strlen($basePath) + 1);
        }

        return basename($fullPath);
    }

    /**
     * Convert file path to route key
     * e.g., "api/v1/users.php" -> "api/v1/users"
     */
    private function pathToKey(string $path): string
    {
        // Remove .php extension
        $key = preg_replace('/\.php$/i', '', $path);

        // Replace directory separators with forward slashes
        return str_replace('\\', '/', $key);
    }

    /**
     * Count total routes across all files
     */
    private function countTotalRoutes(array $routes): int
    {
        $total = 0;
        foreach ($routes as $file) {
            $total += count($file['routes'] ?? []);
        }
        return $total;
    }
}
