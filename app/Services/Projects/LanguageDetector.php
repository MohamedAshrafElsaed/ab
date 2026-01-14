<?php

namespace App\Services\Projects;

class LanguageDetector
{
    private array $extensionMap;
    private array $shebangMap = [
        '#!/usr/bin/env php' => 'php',
        '#!/usr/bin/php' => 'php',
        '#!/usr/bin/env node' => 'javascript',
        '#!/usr/bin/node' => 'javascript',
        '#!/bin/bash' => 'shell',
        '#!/bin/sh' => 'shell',
        '#!/usr/bin/env bash' => 'shell',
        '#!/usr/bin/env python' => 'python',
        '#!/usr/bin/python' => 'python',
    ];

    public function __construct()
    {
        $this->extensionMap = config('projects.languages.extension_map', [
            'php' => 'php',
            'blade.php' => 'blade',
            'js' => 'javascript',
            'mjs' => 'javascript',
            'cjs' => 'javascript',
            'ts' => 'typescript',
            'mts' => 'typescript',
            'tsx' => 'typescriptreact',
            'jsx' => 'javascriptreact',
            'vue' => 'vue',
            'svelte' => 'svelte',
            'css' => 'css',
            'scss' => 'scss',
            'sass' => 'sass',
            'less' => 'less',
            'json' => 'json',
            'yml' => 'yaml',
            'yaml' => 'yaml',
            'md' => 'markdown',
            'mdx' => 'mdx',
            'sql' => 'sql',
            'sh' => 'shell',
            'bash' => 'shell',
            'zsh' => 'shell',
            'xml' => 'xml',
            'html' => 'html',
            'twig' => 'twig',
            'env' => 'dotenv',
        ]);
    }

    public function detect(string $path, ?string $content = null): string
    {
        // Check compound extensions first
        if (preg_match('/\.blade\.php$/i', $path)) {
            return 'blade';
        }
        if (preg_match('/\.min\.js$/i', $path)) {
            return 'javascript';
        }
        if (preg_match('/\.min\.css$/i', $path)) {
            return 'css';
        }
        if (preg_match('/\.d\.ts$/i', $path)) {
            return 'typescript';
        }
        if (preg_match('/\.spec\.(ts|js)$/i', $path)) {
            return preg_match('/\.ts$/i', $path) ? 'typescript' : 'javascript';
        }
        if (preg_match('/\.test\.(ts|js)$/i', $path)) {
            return preg_match('/\.ts$/i', $path) ? 'typescript' : 'javascript';
        }

        // Check simple extension
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (isset($this->extensionMap[$extension])) {
            return $this->extensionMap[$extension];
        }

        // Check special filenames
        $filename = basename($path);
        $specialFiles = [
            'Dockerfile' => 'dockerfile',
            'Makefile' => 'makefile',
            'Vagrantfile' => 'ruby',
            'Gemfile' => 'ruby',
            'Rakefile' => 'ruby',
            '.gitignore' => 'gitignore',
            '.gitattributes' => 'gitattributes',
            '.editorconfig' => 'editorconfig',
            '.env' => 'dotenv',
            '.env.example' => 'dotenv',
            '.env.local' => 'dotenv',
            'composer.json' => 'json',
            'package.json' => 'json',
            'tsconfig.json' => 'json',
            'artisan' => 'php',
        ];

        if (isset($specialFiles[$filename])) {
            return $specialFiles[$filename];
        }

        // Check shebang if content provided
        if ($content !== null) {
            $firstLine = strtok($content, "\n");
            if ($firstLine && str_starts_with($firstLine, '#!')) {
                foreach ($this->shebangMap as $shebang => $lang) {
                    if (str_starts_with($firstLine, $shebang)) {
                        return $lang;
                    }
                }
            }
        }

        return 'plaintext';
    }

    public function getExtensionForLanguage(string $language): ?string
    {
        $reversed = array_flip($this->extensionMap);
        return $reversed[$language] ?? null;
    }
}
