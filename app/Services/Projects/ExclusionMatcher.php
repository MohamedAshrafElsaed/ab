<?php

namespace App\Services\Projects;

use App\Models\Project;

class ExclusionMatcher
{
    private array $directories = [];
    private array $patterns = [];
    private array $files = [];
    private array $extensions = [];
    private array $binaryExtensions = [];
    private string $rulesVersion;

    public function __construct(?Project $project = null)
    {
        $this->loadDefaultRules();

        if ($project && config('projects.exclusions.allow_project_overrides', true)) {
            $this->loadProjectOverrides($project);
        }

        $this->rulesVersion = $this->computeRulesVersion();
    }

    /**
     * Load default exclusion rules from config
     */
    private function loadDefaultRules(): void
    {
        $config = config('projects.exclusions', []);
        $toggles = $config['toggles'] ?? [];

        // Load base rules
        $this->directories = $config['directories'] ?? [
            '.git',
            '.svn',
            '.hg',
            'vendor',
            'node_modules',
            'bower_components',
            'storage',
            'bootstrap/cache',
            'public/build',
            'public/hot',
            'dist',
            'build',
            '.output',
            '.next',
            '.nuxt',
            '.idea',
            '.vscode',
            '.fleet',
            'cache',
            '.cache',
            '__pycache__',
            '.pytest_cache',
            '.mypy_cache',
            '.phpunit.cache',
            'coverage',
            '.nyc_output',
        ];

        $this->patterns = $config['patterns'] ?? [
            '**/node_modules/**',
            '**/vendor/**',
            '**/.git/**',
            '**/storage/logs/**',
            '**/storage/framework/**',
            '**/bootstrap/cache/**',
        ];

        $this->files = $config['files'] ?? [
            '.DS_Store',
            'Thumbs.db',
            '.gitkeep',
            '.gitignore',
            '.editorconfig',
        ];

        $this->extensions = $config['extensions'] ?? [
            'lock',
            'log',
            'map',
            'min.js',
            'min.css',
            'bundle.js',
            'chunk.js',
        ];

        $this->binaryExtensions = $config['binary_extensions'] ?? [
            // Images
            'png', 'jpg', 'jpeg', 'gif', 'bmp', 'ico', 'webp', 'svg', 'avif', 'tiff',
            // Audio/Video
            'mp3', 'mp4', 'wav', 'avi', 'mov', 'mkv', 'webm', 'ogg', 'flac',
            // Documents
            'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
            // Archives
            'zip', 'tar', 'gz', 'rar', '7z', 'bz2', 'xz',
            // Executables
            'exe', 'dll', 'so', 'dylib', 'bin', 'app',
            // Fonts
            'ttf', 'otf', 'woff', 'woff2', 'eot',
            // Databases
            'sqlite', 'db', 'sqlite3', 'mdb',
            // Other
            'phar', 'jar', 'war',
        ];

        // Apply toggles
        if ($toggles['include_vendor'] ?? false) {
            $this->directories = array_values(array_diff($this->directories, ['vendor']));
            $this->patterns = array_values(array_filter($this->patterns, fn($p) => !str_contains($p, 'vendor')));
        }

        if ($toggles['include_node_modules'] ?? false) {
            $this->directories = array_values(array_diff($this->directories, ['node_modules']));
            $this->patterns = array_values(array_filter($this->patterns, fn($p) => !str_contains($p, 'node_modules')));
        }

        if ($toggles['include_storage'] ?? false) {
            $this->directories = array_values(array_diff($this->directories, ['storage']));
            $this->patterns = array_values(array_filter($this->patterns, fn($p) => !str_contains($p, 'storage')));
        }

        if ($toggles['include_lock_files'] ?? false) {
            $this->extensions = array_values(array_diff($this->extensions, ['lock']));
        }

        if ($toggles['include_source_maps'] ?? false) {
            $this->extensions = array_values(array_diff($this->extensions, ['map']));
        }

        if ($toggles['include_minified'] ?? false) {
            $this->extensions = array_values(array_diff($this->extensions, ['min.js', 'min.css']));
        }
    }

    /**
     * Load project-specific overrides from project_scan_config.json
     */
    private function loadProjectOverrides(Project $project): void
    {
        $configPath = $project->repo_path . '/project_scan_config.json';

        if (!file_exists($configPath)) {
            return;
        }

        $content = @file_get_contents($configPath);
        if ($content === false) {
            return;
        }

        $config = json_decode($content, true);

        if (!$config || !isset($config['exclusions'])) {
            return;
        }

        $overrides = $config['exclusions'];

        // Add additional exclusions
        if (isset($overrides['additional_directories']) && is_array($overrides['additional_directories'])) {
            $this->directories = array_unique(array_merge($this->directories, $overrides['additional_directories']));
        }

        if (isset($overrides['additional_patterns']) && is_array($overrides['additional_patterns'])) {
            $this->patterns = array_unique(array_merge($this->patterns, $overrides['additional_patterns']));
        }

        if (isset($overrides['additional_files']) && is_array($overrides['additional_files'])) {
            $this->files = array_unique(array_merge($this->files, $overrides['additional_files']));
        }

        if (isset($overrides['additional_extensions']) && is_array($overrides['additional_extensions'])) {
            $this->extensions = array_unique(array_merge($this->extensions, $overrides['additional_extensions']));
        }

        // Remove from defaults
        if (isset($overrides['remove_from_defaults']) && is_array($overrides['remove_from_defaults'])) {
            $remove = $overrides['remove_from_defaults'];

            if (isset($remove['directories']) && is_array($remove['directories'])) {
                $this->directories = array_values(array_diff($this->directories, $remove['directories']));
            }

            if (isset($remove['patterns']) && is_array($remove['patterns'])) {
                $this->patterns = array_values(array_diff($this->patterns, $remove['patterns']));
            }

            if (isset($remove['files']) && is_array($remove['files'])) {
                $this->files = array_values(array_diff($this->files, $remove['files']));
            }

            if (isset($remove['extensions']) && is_array($remove['extensions'])) {
                $this->extensions = array_values(array_diff($this->extensions, $remove['extensions']));
            }
        }

        // Apply project-level toggles
        if (isset($overrides['toggles']) && is_array($overrides['toggles'])) {
            foreach ($overrides['toggles'] as $toggle => $value) {
                if (!$value) {
                    continue;
                }

                switch ($toggle) {
                    case 'include_vendor':
                        $this->directories = array_values(array_diff($this->directories, ['vendor']));
                        break;
                    case 'include_node_modules':
                        $this->directories = array_values(array_diff($this->directories, ['node_modules']));
                        break;
                    case 'include_storage':
                        $this->directories = array_values(array_diff($this->directories, ['storage']));
                        break;
                    case 'include_lock_files':
                        $this->extensions = array_values(array_diff($this->extensions, ['lock']));
                        break;
                }
            }
        }
    }

    /**
     * Check if a file path should be excluded
     *
     * @param string $relativePath The relative path from repo root
     * @return array|false Returns exclusion info array if excluded, false otherwise
     */
    public function shouldExclude(string $relativePath): array|false
    {
        // Normalize path separators
        $relativePath = str_replace('\\', '/', $relativePath);
        $pathParts = explode('/', $relativePath);
        $fileName = end($pathParts);

        // Check directory exclusions (exact match against any path segment)
        foreach ($pathParts as $part) {
            if (in_array($part, $this->directories, true)) {
                return [
                    'excluded' => true,
                    'rule' => "directory:{$part}",
                    'matched_at' => $part,
                ];
            }
        }

        // Check file name exclusions (exact match)
        if (in_array($fileName, $this->files, true)) {
            return [
                'excluded' => true,
                'rule' => "file:{$fileName}",
                'matched_at' => $fileName,
            ];
        }

        // Check extension exclusions
        $extension = $this->getExtension($relativePath);
        if ($extension && in_array($extension, $this->extensions, true)) {
            return [
                'excluded' => true,
                'rule' => "extension:{$extension}",
                'matched_at' => $extension,
            ];
        }

        // Check pattern exclusions (glob-style)
        foreach ($this->patterns as $pattern) {
            if ($this->matchGlob($relativePath, $pattern)) {
                return [
                    'excluded' => true,
                    'rule' => "pattern:{$pattern}",
                    'matched_at' => $pattern,
                ];
            }
        }

        return false;
    }

    /**
     * Check if a file is binary based on extension
     */
    public function isBinary(string $relativePath): bool
    {
        $extension = $this->getExtension($relativePath);

        if (!$extension) {
            return false;
        }

        return in_array(strtolower($extension), $this->binaryExtensions, true);
    }

    /**
     * Get file extension, handling compound extensions
     */
    private function getExtension(string $path): ?string
    {
        // Handle compound extensions
        $compoundPatterns = [
            '/\.blade\.php$/i' => 'blade.php',
            '/\.min\.js$/i' => 'min.js',
            '/\.min\.css$/i' => 'min.css',
            '/\.d\.ts$/i' => 'd.ts',
            '/\.spec\.ts$/i' => 'spec.ts',
            '/\.spec\.js$/i' => 'spec.js',
            '/\.test\.ts$/i' => 'test.ts',
            '/\.test\.js$/i' => 'test.js',
            '/\.bundle\.js$/i' => 'bundle.js',
            '/\.chunk\.js$/i' => 'chunk.js',
        ];

        foreach ($compoundPatterns as $pattern => $ext) {
            if (preg_match($pattern, $path)) {
                return $ext;
            }
        }

        $extension = pathinfo($path, PATHINFO_EXTENSION);
        return $extension ?: null;
    }

    /**
     * Match a path against a glob pattern
     */
    private function matchGlob(string $path, string $pattern): bool
    {
        // Convert glob pattern to regex
        $regex = $pattern;

        // Escape regex special chars except * and ?
        $regex = preg_quote($regex, '#');

        // Restore * and ? and convert to regex
        $regex = str_replace(
            ['\*\*/', '\*\*', '\*', '\?'],
            ['.*/?', '.*', '[^/]*', '.'],
            $regex
        );

        return (bool) preg_match("#^{$regex}$#i", $path);
    }

    /**
     * Compute a version hash of all active rules for cache invalidation
     */
    private function computeRulesVersion(): string
    {
        $rules = [
            'directories' => $this->directories,
            'patterns' => $this->patterns,
            'files' => $this->files,
            'extensions' => $this->extensions,
            'binary' => $this->binaryExtensions,
        ];

        // Sort for deterministic output
        ksort($rules);
        foreach ($rules as &$arr) {
            sort($arr);
        }

        return sha1(json_encode($rules));
    }

    /**
     * Get the rules version hash
     */
    public function getRulesVersion(): string
    {
        return $this->rulesVersion;
    }

    /**
     * Get all active rules for debugging/display
     */
    public function getActiveRules(): array
    {
        return [
            'directories' => array_values($this->directories),
            'patterns' => array_values($this->patterns),
            'files' => array_values($this->files),
            'extensions' => array_values($this->extensions),
            'binary_extensions' => array_values($this->binaryExtensions),
            'version' => $this->rulesVersion,
        ];
    }

    /**
     * Get excluded directories list
     */
    public function getExcludedDirectories(): array
    {
        return $this->directories;
    }

    /**
     * Get excluded patterns list
     */
    public function getExcludedPatterns(): array
    {
        return $this->patterns;
    }

    /**
     * Get binary extensions list
     */
    public function getBinaryExtensions(): array
    {
        return $this->binaryExtensions;
    }

    /**
     * Check if a specific directory is excluded
     */
    public function isDirectoryExcluded(string $directory): bool
    {
        $directory = trim($directory, '/');
        return in_array($directory, $this->directories, true);
    }

    /**
     * Add a directory to exclusions at runtime
     */
    public function addExcludedDirectory(string $directory): void
    {
        $directory = trim($directory, '/');
        if (!in_array($directory, $this->directories, true)) {
            $this->directories[] = $directory;
            $this->rulesVersion = $this->computeRulesVersion();
        }
    }

    /**
     * Remove a directory from exclusions at runtime
     */
    public function removeExcludedDirectory(string $directory): void
    {
        $directory = trim($directory, '/');
        $this->directories = array_values(array_diff($this->directories, [$directory]));
        $this->rulesVersion = $this->computeRulesVersion();
    }
}
