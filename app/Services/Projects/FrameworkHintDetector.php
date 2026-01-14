<?php

namespace App\Services\Projects;

class FrameworkHintDetector
{
    private array $pathPatterns;
    private array $contentMarkers;

    public function __construct()
    {
        $config = config('projects.framework_hints', []);
        $this->pathPatterns = $config['path_patterns'] ?? [];
        $this->contentMarkers = $config['content_markers'] ?? [];
    }

    /**
     * Detect framework hints for a file based on path and content
     */
    public function detect(string $path, ?string $content = null): array
    {
        $hints = [];

        // Check path patterns
        foreach ($this->pathPatterns as $framework => $patterns) {
            foreach ($patterns as $pattern) {
                if ($this->matchPattern($path, $pattern)) {
                    $hints[] = $framework;
                    break;
                }
            }
        }

        // Check content markers if content provided
        if ($content !== null) {
            foreach ($this->contentMarkers as $framework => $markers) {
                foreach ($markers as $marker) {
                    if (str_contains($content, $marker)) {
                        if (!in_array($framework, $hints)) {
                            $hints[] = $framework;
                        }
                        break;
                    }
                }
            }
        }

        // Additional detection for common patterns
        $hints = array_merge($hints, $this->detectAdditionalHints($path, $content));

        return array_unique($hints);
    }

    private function matchPattern(string $path, string $pattern): bool
    {
        // Convert glob pattern to regex
        $regex = str_replace(
            ['**/', '*', '?', '.'],
            ['.*/?', '[^/]*', '.', '\\.'],
            $pattern
        );

        return (bool)preg_match("#^{$regex}$#i", $path);
    }

    private function detectAdditionalHints(string $path, ?string $content): array
    {
        $hints = [];

        // Laravel specific
        if (str_contains($path, 'app/Http/Controllers/')) {
            $hints[] = 'laravel';
        }

        if (str_contains($path, 'app/Services/')) {
            $hints[] = 'services';
        }

        if (str_contains($path, 'app/Models/')) {
            $hints[] = 'eloquent';
        }
        if (preg_match('/database\/migrations\/\d+_.*\.php$/', $path)) {
            $hints[] = 'laravel-migrations';
        }

        // API detection
        if (str_contains($path, 'routes/api.php') || str_contains($path, 'app/Http/Controllers/Api/')) {
            $hints[] = 'api';
        }

        // Test detection
        if (str_contains($path, '/tests/') || str_ends_with($path, 'Test.php')) {
            $hints[] = 'testing';
            if ($content && str_contains($content, 'use Tests\\TestCase')) {
                $hints[] = 'laravel-testing';
            }
            if ($content && str_contains($content, 'use PHPUnit\\')) {
                $hints[] = 'phpunit';
            }
        }

        // Queue/Job detection
        if (str_contains($path, 'app/Jobs/')) {
            $hints[] = 'queues';
        }

        // Event detection
        if (str_contains($path, 'app/Events/') || str_contains($path, 'app/Listeners/')) {
            $hints[] = 'events';
        }

        if (str_contains($path, 'app/Listener/') || str_contains($path, 'app/Listeners/')) {
            $hints[] = 'listener';
        }

        return $hints;
    }
}
