<?php

namespace App\Services\Projects;

class SymbolExtractor
{
    /**
     * Extract symbol declarations from content
     */
    public function extractDeclarations(string $content, ?string $extension): array
    {
        $language = $this->getLanguageFromExtension($extension);

        return match ($language) {
            'php', 'blade' => $this->extractPhpDeclarations($content),
            'javascript', 'typescript', 'typescriptreact', 'javascriptreact' => $this->extractJsDeclarations($content),
            'vue' => $this->extractVueDeclarations($content),
            default => [],
        };
    }

    /**
     * Extract symbol usages from content
     */
    public function extractUsages(string $content, ?string $extension): array
    {
        $language = $this->getLanguageFromExtension($extension);

        return match ($language) {
            'php', 'blade' => $this->extractPhpUsages($content),
            'javascript', 'typescript', 'typescriptreact', 'javascriptreact' => $this->extractJsUsages($content),
            'vue' => $this->extractVueUsages($content),
            default => [],
        };
    }

    /**
     * Extract import statements from content
     */
    public function extractImports(string $content, ?string $extension): array
    {
        $language = $this->getLanguageFromExtension($extension);

        return match ($language) {
            'php' => $this->extractPhpImports($content),
            'blade' => $this->extractBladeImports($content),
            'javascript', 'typescript', 'typescriptreact', 'javascriptreact' => $this->extractJsImports($content),
            'vue' => $this->extractVueImports($content),
            default => [],
        };
    }

    private function getLanguageFromExtension(?string $extension): string
    {
        if ($extension === null) {
            return 'unknown';
        }

        $map = [
            'php' => 'php',
            'blade.php' => 'blade',
            'js' => 'javascript',
            'mjs' => 'javascript',
            'cjs' => 'javascript',
            'ts' => 'typescript',
            'tsx' => 'typescriptreact',
            'jsx' => 'javascriptreact',
            'vue' => 'vue',
        ];

        return $map[strtolower($extension)] ?? 'unknown';
    }

    // -------------------------------------------------------------------------
    // PHP Extraction
    // -------------------------------------------------------------------------

    private function extractPhpDeclarations(string $content): array
    {
        $declarations = [];
        $lines = explode("\n", $content);

        foreach ($lines as $lineNum => $line) {
            $lineNumber = $lineNum + 1;

            // Class declaration
            if (preg_match('/^\s*(abstract\s+|final\s+)?(class|trait|interface|enum)\s+(\w+)/i', $line, $m)) {
                $declarations[] = [
                    'type' => strtolower($m[2]),
                    'name' => $m[3],
                    'line' => $lineNumber,
                ];
            }

            // Function/method declaration
            if (preg_match('/^\s*(public|private|protected|static|\s)*\s*function\s+(\w+)\s*\(/i', $line, $m)) {
                $modifier = trim($m[1]);
                $declarations[] = [
                    'type' => str_contains($modifier, 'public') || str_contains($modifier, 'private') || str_contains($modifier, 'protected')
                        ? 'method'
                        : 'function',
                    'name' => $m[2],
                    'line' => $lineNumber,
                ];
            }

            // Constant declaration
            if (preg_match('/^\s*const\s+(\w+)\s*=/i', $line, $m)) {
                $declarations[] = [
                    'type' => 'const',
                    'name' => $m[1],
                    'line' => $lineNumber,
                ];
            }

            // Property declaration
            if (preg_match('/^\s*(public|private|protected)\s+(static\s+)?(readonly\s+)?(\??\w+\s+)?\$(\w+)/i', $line, $m)) {
                $declarations[] = [
                    'type' => 'property',
                    'name' => $m[5],
                    'line' => $lineNumber,
                ];
            }
        }

        return $declarations;
    }

    private function extractPhpImports(string $content): array
    {
        $imports = [];
        $lines = explode("\n", $content);

        foreach ($lines as $lineNum => $line) {
            $lineNumber = $lineNum + 1;

            // Use statements
            if (preg_match('/^\s*use\s+([^;]+);/i', $line, $m)) {
                $path = trim($m[1]);
                $alias = null;

                // Check for alias
                if (preg_match('/(.+)\s+as\s+(\w+)$/i', $path, $aliasMatch)) {
                    $path = trim($aliasMatch[1]);
                    $alias = $aliasMatch[2];
                }

                // Handle grouped imports: use App\{Foo, Bar};
                if (preg_match('/^(.+)\\\{(.+)\}$/', $path, $groupMatch)) {
                    $base = $groupMatch[1];
                    $items = array_map('trim', explode(',', $groupMatch[2]));
                    foreach ($items as $item) {
                        $imports[] = [
                            'type' => 'use',
                            'path' => $base . '\\' . $item,
                            'line' => $lineNumber,
                        ];
                    }
                } else {
                    $imports[] = [
                        'type' => 'use',
                        'path' => $path,
                        'alias' => $alias,
                        'line' => $lineNumber,
                    ];
                }
            }

            // Namespace
            if (preg_match('/^\s*namespace\s+([^;]+);/i', $line, $m)) {
                $imports[] = [
                    'type' => 'namespace',
                    'path' => trim($m[1]),
                    'line' => $lineNumber,
                ];
            }
        }

        return $imports;
    }

    private function extractPhpUsages(string $content): array
    {
        $usages = [];
        $lines = explode("\n", $content);

        foreach ($lines as $lineNum => $line) {
            $lineNumber = $lineNum + 1;

            // Static method calls: ClassName::method()
            if (preg_match_all('/([A-Z]\w+)::(\w+)\s*\(/i', $line, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $m) {
                    $usages[] = [
                        'symbol' => $m[1] . '::' . $m[2],
                        'line' => $lineNumber,
                    ];
                }
            }

            // New instantiation: new ClassName
            if (preg_match_all('/new\s+([A-Z]\w+)/i', $line, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $m) {
                    $usages[] = [
                        'symbol' => $m[1],
                        'line' => $lineNumber,
                    ];
                }
            }

            // Type hints in function parameters
            if (preg_match_all('/:\s*\??([A-Z]\w+)/', $line, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $m) {
                    if (!in_array(strtolower($m[1]), ['string', 'int', 'float', 'bool', 'array', 'object', 'mixed', 'void', 'null', 'self', 'static'])) {
                        $usages[] = [
                            'symbol' => $m[1],
                            'line' => $lineNumber,
                        ];
                    }
                }
            }
        }

        return array_slice($usages, 0, 50); // Limit to prevent oversized output
    }

    private function extractBladeImports(string $content): array
    {
        $imports = [];
        $lines = explode("\n", $content);

        foreach ($lines as $lineNum => $line) {
            $lineNumber = $lineNum + 1;

            // @extends
            if (preg_match('/@extends\s*\(\s*[\'"]([^\'"]+)[\'"]\s*\)/i', $line, $m)) {
                $imports[] = [
                    'type' => 'extends',
                    'path' => $m[1],
                    'line' => $lineNumber,
                ];
            }

            // @include / @includeIf / @includeWhen
            if (preg_match('/@include\w*\s*\(\s*[\'"]([^\'"]+)[\'"]/i', $line, $m)) {
                $imports[] = [
                    'type' => 'include',
                    'path' => $m[1],
                    'line' => $lineNumber,
                ];
            }

            // @component
            if (preg_match('/@component\s*\(\s*[\'"]([^\'"]+)[\'"]/i', $line, $m)) {
                $imports[] = [
                    'type' => 'component',
                    'path' => $m[1],
                    'line' => $lineNumber,
                ];
            }

            // <x-component-name>
            if (preg_match('/<x-([a-z0-9\-\.]+)/i', $line, $m)) {
                $imports[] = [
                    'type' => 'component',
                    'path' => str_replace(['-', '.'], ['_', '.'], $m[1]),
                    'line' => $lineNumber,
                ];
            }

            // @livewire
            if (preg_match('/@livewire\s*\(\s*[\'"]([^\'"]+)[\'"]/i', $line, $m)) {
                $imports[] = [
                    'type' => 'livewire',
                    'path' => $m[1],
                    'line' => $lineNumber,
                ];
            }

            // <livewire:component-name>
            if (preg_match('/<livewire:([a-z0-9\-\.]+)/i', $line, $m)) {
                $imports[] = [
                    'type' => 'livewire',
                    'path' => $m[1],
                    'line' => $lineNumber,
                ];
            }
        }

        return $imports;
    }

    // -------------------------------------------------------------------------
    // JavaScript/TypeScript Extraction
    // -------------------------------------------------------------------------

    private function extractJsDeclarations(string $content): array
    {
        $declarations = [];
        $lines = explode("\n", $content);

        foreach ($lines as $lineNum => $line) {
            $lineNumber = $lineNum + 1;

            // Function declaration: function name()
            if (preg_match('/^\s*(export\s+)?(async\s+)?function\s+(\w+)/i', $line, $m)) {
                $declarations[] = [
                    'type' => 'function',
                    'name' => $m[3],
                    'line' => $lineNumber,
                ];
            }

            // Arrow function assigned to const: const name = () =>
            if (preg_match('/^\s*(export\s+)?(const|let|var)\s+(\w+)\s*=\s*(async\s+)?\(/i', $line, $m)) {
                $declarations[] = [
                    'type' => 'function',
                    'name' => $m[3],
                    'line' => $lineNumber,
                ];
            }

            // Class declaration
            if (preg_match('/^\s*(export\s+)?(default\s+)?(abstract\s+)?class\s+(\w+)/i', $line, $m)) {
                $declarations[] = [
                    'type' => 'class',
                    'name' => $m[4],
                    'line' => $lineNumber,
                ];
            }

            // Interface (TypeScript)
            if (preg_match('/^\s*(export\s+)?interface\s+(\w+)/i', $line, $m)) {
                $declarations[] = [
                    'type' => 'interface',
                    'name' => $m[2],
                    'line' => $lineNumber,
                ];
            }

            // Type alias (TypeScript)
            if (preg_match('/^\s*(export\s+)?type\s+(\w+)\s*=/i', $line, $m)) {
                $declarations[] = [
                    'type' => 'type',
                    'name' => $m[2],
                    'line' => $lineNumber,
                ];
            }

            // Enum (TypeScript)
            if (preg_match('/^\s*(export\s+)?(const\s+)?enum\s+(\w+)/i', $line, $m)) {
                $declarations[] = [
                    'type' => 'enum',
                    'name' => $m[3],
                    'line' => $lineNumber,
                ];
            }
        }

        return $declarations;
    }

    private function extractJsImports(string $content): array
    {
        $imports = [];
        $lines = explode("\n", $content);

        foreach ($lines as $lineNum => $line) {
            $lineNumber = $lineNum + 1;

            // ES6 import: import ... from '...'
            if (preg_match('/^\s*import\s+.*\s+from\s+[\'"]([^\'"]+)[\'"]/i', $line, $m)) {
                $imports[] = [
                    'type' => 'import',
                    'path' => $m[1],
                    'line' => $lineNumber,
                ];
            }

            // Dynamic import: import('...')
            if (preg_match('/import\s*\(\s*[\'"]([^\'"]+)[\'"]\s*\)/i', $line, $m)) {
                $imports[] = [
                    'type' => 'import',
                    'path' => $m[1],
                    'line' => $lineNumber,
                ];
            }

            // CommonJS require: require('...')
            if (preg_match('/require\s*\(\s*[\'"]([^\'"]+)[\'"]\s*\)/i', $line, $m)) {
                $imports[] = [
                    'type' => 'require',
                    'path' => $m[1],
                    'line' => $lineNumber,
                ];
            }
        }

        return $imports;
    }

    private function extractJsUsages(string $content): array
    {
        $usages = [];
        $lines = explode("\n", $content);

        foreach ($lines as $lineNum => $line) {
            $lineNumber = $lineNum + 1;

            // React hooks
            if (preg_match_all('/(use\w+)\s*\(/i', $line, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $m) {
                    $usages[] = [
                        'symbol' => $m[1],
                        'line' => $lineNumber,
                    ];
                }
            }

            // Component usage in JSX: <ComponentName
            if (preg_match_all('/<([A-Z]\w+)/i', $line, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $m) {
                    $usages[] = [
                        'symbol' => $m[1],
                        'line' => $lineNumber,
                    ];
                }
            }
        }

        return array_slice($usages, 0, 50);
    }

    // -------------------------------------------------------------------------
    // Vue Extraction
    // -------------------------------------------------------------------------

    private function extractVueDeclarations(string $content): array
    {
        $declarations = [];

        // Extract component name from defineComponent or export default
        if (preg_match('/defineComponent\s*\(\s*\{[^}]*name\s*:\s*[\'"](\w+)[\'"]/is', $content, $m)) {
            $declarations[] = [
                'type' => 'component',
                'name' => $m[1],
                'line' => 1,
            ];
        }

        // Extract from <script setup> - composables and refs
        if (preg_match('/<script\s+setup[^>]*>(.*?)<\/script>/is', $content, $scriptMatch)) {
            $scriptContent = $scriptMatch[1];

            // const declarations in script setup
            if (preg_match_all('/const\s+(\w+)\s*=\s*(ref|reactive|computed)/i', $scriptContent, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $m) {
                    $declarations[] = [
                        'type' => 'ref',
                        'name' => $m[1],
                        'line' => 1,
                    ];
                }
            }
        }

        // Also extract regular JS declarations from script section
        if (preg_match('/<script[^>]*>(.*?)<\/script>/is', $content, $scriptMatch)) {
            $jsDeclarations = $this->extractJsDeclarations($scriptMatch[1]);
            $declarations = array_merge($declarations, $jsDeclarations);
        }

        return $declarations;
    }

    private function extractVueImports(string $content): array
    {
        $imports = [];

        // Extract imports from script section
        if (preg_match('/<script[^>]*>(.*?)<\/script>/is', $content, $scriptMatch)) {
            $imports = $this->extractJsImports($scriptMatch[1]);
        }

        return $imports;
    }

    private function extractVueUsages(string $content): array
    {
        $usages = [];

        // Component usage in template
        if (preg_match('/<template[^>]*>(.*?)<\/template>/is', $content, $templateMatch)) {
            $template = $templateMatch[1];

            // PascalCase components
            if (preg_match_all('/<([A-Z]\w+)/i', $template, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $m) {
                    $usages[] = [
                        'symbol' => $m[1],
                        'line' => 1,
                    ];
                }
            }

            // kebab-case components that look custom (contain dash)
            if (preg_match_all('/<([a-z]+-[a-z0-9-]+)/i', $template, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $m) {
                    $usages[] = [
                        'symbol' => $m[1],
                        'line' => 1,
                    ];
                }
            }
        }

        return array_unique($usages, SORT_REGULAR);
    }
}
