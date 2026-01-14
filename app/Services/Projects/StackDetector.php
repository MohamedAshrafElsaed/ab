<?php

namespace App\Services\Projects;

use App\Models\Project;

class StackDetector
{
    public function detect(Project $project): array
    {
        $repoPath = $project->repo_path;
        $stack = [
            'framework' => null,
            'framework_version' => null,
            'php_version' => null,
            'frontend' => [],
            'css' => [],
            'build_tools' => [],
            'testing' => [],
            'database' => [],
            'features' => [],
        ];

        // Detect Laravel
        $this->detectLaravel($repoPath, $stack);

        // Detect Frontend
        $this->detectFrontend($repoPath, $stack);

        // Detect CSS frameworks
        $this->detectCss($repoPath, $stack);

        // Detect Build tools
        $this->detectBuildTools($repoPath, $stack);

        // Detect Testing frameworks
        $this->detectTesting($repoPath, $stack);

        // Detect Database
        $this->detectDatabase($repoPath, $stack);

        // Detect Features
        $this->detectFeatures($repoPath, $stack, $project);

        return $stack;
    }

    private function detectLaravel(string $repoPath, array &$stack): void
    {
        $composerJson = $this->readJson($repoPath . '/composer.json');
        $composerLock = $this->readJson($repoPath . '/composer.lock');

        if (!$composerJson) {
            return;
        }

        $require = $composerJson['require'] ?? [];

        if (isset($require['laravel/framework'])) {
            $stack['framework'] = 'laravel';
            $stack['framework_version'] = $this->extractVersion($require['laravel/framework']);

            // Try to get exact version from composer.lock
            if ($composerLock) {
                foreach ($composerLock['packages'] ?? [] as $package) {
                    if ($package['name'] === 'laravel/framework') {
                        $stack['framework_version'] = $package['version'] ?? $stack['framework_version'];
                        break;
                    }
                }
            }
        }

        // PHP version
        if (isset($require['php'])) {
            $stack['php_version'] = $this->extractVersion($require['php']);
        }

        // Livewire detection
        if (isset($require['livewire/livewire'])) {
            $stack['frontend'][] = 'livewire';
            $version = $this->getPackageVersion($composerLock, 'livewire/livewire');
            if ($version) {
                $stack['livewire_version'] = $version;
            }
        }

        // Inertia Laravel detection
        if (isset($require['inertiajs/inertia-laravel'])) {
            $stack['frontend'][] = 'inertia';
        }

        // Filament detection
        if (isset($require['filament/filament'])) {
            $stack['frontend'][] = 'filament';
        }

        // Nova detection
        if (isset($require['laravel/nova'])) {
            $stack['frontend'][] = 'nova';
        }
    }

    private function detectFrontend(string $repoPath, array &$stack): void
    {
        $packageJson = $this->readJson($repoPath . '/package.json');

        if (!$packageJson) {
            return;
        }

        $dependencies = array_merge(
            $packageJson['dependencies'] ?? [],
            $packageJson['devDependencies'] ?? []
        );

        // Vue detection
        if (isset($dependencies['vue'])) {
            $stack['frontend'][] = 'vue';
            $stack['vue_version'] = $this->extractVersion($dependencies['vue']);
        }

        // React detection
        if (isset($dependencies['react'])) {
            $stack['frontend'][] = 'react';
            $stack['react_version'] = $this->extractVersion($dependencies['react']);
        }

        // Alpine.js detection
        if (isset($dependencies['alpinejs'])) {
            $stack['frontend'][] = 'alpine';
        }

        // Svelte detection
        if (isset($dependencies['svelte'])) {
            $stack['frontend'][] = 'svelte';
        }

        // TypeScript detection
        if (isset($dependencies['typescript'])) {
            $stack['frontend'][] = 'typescript';
        }

        // Inertia frontend detection
        if (isset($dependencies['@inertiajs/vue3']) || isset($dependencies['@inertiajs/react'])) {
            if (!in_array('inertia', $stack['frontend'])) {
                $stack['frontend'][] = 'inertia';
            }
        }
    }

    private function detectCss(string $repoPath, array &$stack): void
    {
        $packageJson = $this->readJson($repoPath . '/package.json');

        // Tailwind detection
        if (file_exists($repoPath . '/tailwind.config.js') ||
            file_exists($repoPath . '/tailwind.config.ts') ||
            file_exists($repoPath . '/tailwind.config.cjs')) {
            $stack['css'][] = 'tailwind';
        }

        if ($packageJson) {
            $dependencies = array_merge(
                $packageJson['dependencies'] ?? [],
                $packageJson['devDependencies'] ?? []
            );

            if (isset($dependencies['bootstrap'])) {
                $stack['css'][] = 'bootstrap';
            }

            if (isset($dependencies['sass']) || isset($dependencies['node-sass'])) {
                $stack['css'][] = 'sass';
            }

            if (isset($dependencies['postcss'])) {
                $stack['css'][] = 'postcss';
            }
        }
    }

    private function detectBuildTools(string $repoPath, array &$stack): void
    {
        // Vite detection
        if (file_exists($repoPath . '/vite.config.js') ||
            file_exists($repoPath . '/vite.config.ts')) {
            $stack['build_tools'][] = 'vite';
        }

        // Webpack/Mix detection
        if (file_exists($repoPath . '/webpack.mix.js')) {
            $stack['build_tools'][] = 'laravel-mix';
        }

        if (file_exists($repoPath . '/webpack.config.js')) {
            $stack['build_tools'][] = 'webpack';
        }

        // ESBuild detection
        $packageJson = $this->readJson($repoPath . '/package.json');
        if ($packageJson) {
            $dependencies = array_merge(
                $packageJson['dependencies'] ?? [],
                $packageJson['devDependencies'] ?? []
            );

            if (isset($dependencies['esbuild'])) {
                $stack['build_tools'][] = 'esbuild';
            }
        }
    }

    private function detectTesting(string $repoPath, array &$stack): void
    {
        $composerJson = $this->readJson($repoPath . '/composer.json');
        $packageJson = $this->readJson($repoPath . '/package.json');

        if ($composerJson) {
            $requireDev = $composerJson['require-dev'] ?? [];

            if (isset($requireDev['phpunit/phpunit'])) {
                $stack['testing'][] = 'phpunit';
            }

            if (isset($requireDev['pestphp/pest'])) {
                $stack['testing'][] = 'pest';
            }

            if (isset($requireDev['laravel/dusk'])) {
                $stack['testing'][] = 'dusk';
            }
        }

        if ($packageJson) {
            $dependencies = array_merge(
                $packageJson['dependencies'] ?? [],
                $packageJson['devDependencies'] ?? []
            );

            if (isset($dependencies['vitest'])) {
                $stack['testing'][] = 'vitest';
            }

            if (isset($dependencies['jest'])) {
                $stack['testing'][] = 'jest';
            }

            if (isset($dependencies['cypress'])) {
                $stack['testing'][] = 'cypress';
            }

            if (isset($dependencies['playwright'])) {
                $stack['testing'][] = 'playwright';
            }
        }
    }

    private function detectDatabase(string $repoPath, array &$stack): void
    {
        $envExample = @file_get_contents($repoPath . '/.env.example');

        if ($envExample) {
            if (str_contains($envExample, 'DB_CONNECTION=mysql')) {
                $stack['database'][] = 'mysql';
            }
            if (str_contains($envExample, 'DB_CONNECTION=pgsql')) {
                $stack['database'][] = 'postgresql';
            }
            if (str_contains($envExample, 'DB_CONNECTION=sqlite')) {
                $stack['database'][] = 'sqlite';
            }
            if (str_contains($envExample, 'REDIS_HOST')) {
                $stack['database'][] = 'redis';
            }
        }
    }

    private function detectFeatures(string $repoPath, array &$stack, Project $project): void
    {
        // Blade templates
        if (is_dir($repoPath . '/resources/views')) {
            $bladeFiles = glob($repoPath . '/resources/views/**/*.blade.php', GLOB_BRACE);
            if (!empty($bladeFiles) || count(glob($repoPath . '/resources/views/*.blade.php')) > 0) {
                $stack['features'][] = 'blade';
            }
        }

        // Livewire components
        if (is_dir($repoPath . '/app/Livewire') ||
            is_dir($repoPath . '/app/Http/Livewire') ||
            is_dir($repoPath . '/resources/views/livewire')) {
            if (!in_array('livewire', $stack['frontend'])) {
                $stack['frontend'][] = 'livewire';
            }
        }

        // API routes
        if (file_exists($repoPath . '/routes/api.php')) {
            $stack['features'][] = 'api';
        }

        // Broadcasting
        if (file_exists($repoPath . '/routes/channels.php')) {
            $content = @file_get_contents($repoPath . '/routes/channels.php');
            if ($content && strlen(trim($content)) > 50) {
                $stack['features'][] = 'broadcasting';
            }
        }

        // Queue jobs
        if (is_dir($repoPath . '/app/Jobs')) {
            $jobs = glob($repoPath . '/app/Jobs/*.php');
            if (!empty($jobs)) {
                $stack['features'][] = 'queues';
            }
        }

        // Events
        if (is_dir($repoPath . '/app/Events')) {
            $events = glob($repoPath . '/app/Events/*.php');
            if (!empty($events)) {
                $stack['features'][] = 'events';
            }
        }

        // Notifications
        if (is_dir($repoPath . '/app/Notifications')) {
            $notifications = glob($repoPath . '/app/Notifications/*.php');
            if (!empty($notifications)) {
                $stack['features'][] = 'notifications';
            }
        }

        // Sanctum/Passport
        $composerJson = $this->readJson($repoPath . '/composer.json');
        if ($composerJson) {
            $require = $composerJson['require'] ?? [];
            if (isset($require['laravel/sanctum'])) {
                $stack['features'][] = 'sanctum';
            }
            if (isset($require['laravel/passport'])) {
                $stack['features'][] = 'passport';
            }
            if (isset($require['laravel/socialite'])) {
                $stack['features'][] = 'socialite';
            }
            if (isset($require['laravel/horizon'])) {
                $stack['features'][] = 'horizon';
            }
            if (isset($require['laravel/telescope'])) {
                $stack['features'][] = 'telescope';
            }
        }

        // Check for .vue files in resources/js
        $vueFiles = $project->files()
            ->where('extension', 'vue')
            ->exists();
        if ($vueFiles && !in_array('vue', $stack['frontend'])) {
            $stack['frontend'][] = 'vue';
        }

        // Check for .jsx/.tsx files
        $reactFiles = $project->files()
            ->whereIn('extension', ['jsx', 'tsx'])
            ->exists();
        if ($reactFiles && !in_array('react', $stack['frontend'])) {
            $stack['frontend'][] = 'react';
        }
    }

    private function readJson(string $path): ?array
    {
        if (!file_exists($path)) {
            return null;
        }

        $content = @file_get_contents($path);
        if (!$content) {
            return null;
        }

        return json_decode($content, true);
    }

    private function extractVersion(string $constraint): string
    {
        // Remove common constraint prefixes
        $version = preg_replace('/^[\^~>=<]+/', '', $constraint);
        return explode(' ', $version)[0] ?? $constraint;
    }

    private function getPackageVersion(array $composerLock, string $packageName): ?string
    {
        foreach ($composerLock['packages'] ?? [] as $package) {
            if ($package['name'] === $packageName) {
                return $package['version'] ?? null;
            }
        }
        return null;
    }

    public function saveStackJson(Project $project, array $stack): void
    {
        $path = $project->knowledge_path . '/stack.json';
        file_put_contents($path, json_encode($stack, JSON_PRETTY_PRINT));
    }
}
