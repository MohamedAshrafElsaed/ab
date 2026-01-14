<?php

namespace App\Services\Prompts;

use App\DTOs\ComposedPrompt;
use App\Enums\IntentType;
use App\Models\IntentAnalysis;
use App\Models\Project;
use App\Services\AskAI\DTO\RetrievedChunk;

class PromptComposer
{
    private PromptTemplateService $templateService;
    private array $config;

    public function __construct(PromptTemplateService $templateService)
    {
        $this->templateService = $templateService;
        $this->config = config('prompts', []);
    }

    /**
     * Build the full context section with knowledge base data.
     *
     * @param RetrievedChunk[] $relevantChunks
     */
    public function buildContextSection(Project $project, array $relevantChunks): string
    {
        $context = "<project_context>\n";
        $context .= $this->buildProjectInfo($project);
        $context .= "</project_context>\n\n";

        if (!empty($relevantChunks)) {
            $context .= "<relevant_code>\n";
            $context .= $this->formatChunksForPrompt($relevantChunks);
            $context .= "</relevant_code>\n";
        }

        return $context;
    }

    /**
     * Build the task section based on intent analysis.
     */
    public function buildTaskSection(IntentAnalysis $intent, string $userMessage): string
    {
        $section = "<task>\n";
        $section .= "<user_request>\n{$userMessage}\n</user_request>\n\n";
        $section .= "<intent_analysis>\n";
        $section .= "- Type: {$intent->intent_type->label()}\n";
        $section .= "- Confidence: {$intent->confidence_score}\n";
        $section .= "- Complexity: {$intent->complexity_estimate->value}\n";
        $section .= "- Domain: " . ($intent->domain_classification['primary'] ?? 'general') . "\n";

        if (!empty($intent->extracted_entities['files'])) {
            $section .= "- Mentioned Files: " . implode(', ', $intent->extracted_entities['files']) . "\n";
        }

        if (!empty($intent->extracted_entities['symbols'])) {
            $section .= "- Mentioned Symbols: " . implode(', ', $intent->extracted_entities['symbols']) . "\n";
        }

        $section .= "</intent_analysis>\n";
        $section .= "</task>";

        return $section;
    }

    /**
     * Build examples section from template.
     */
    public function buildExamplesSection(IntentType $intent): string
    {
        $intentTemplates = $this->config['intent_templates'] ?? [];
        $intentKey = $intent->value;

        if (!isset($intentTemplates[$intentKey])) {
            return '';
        }

        $primaryTemplate = $intentTemplates[$intentKey]['primary'] ?? null;

        if (!$primaryTemplate) {
            return '';
        }

        $template = $this->templateService->load($primaryTemplate);

        if (preg_match('/<examples>(.*?)<\/examples>/s', $template, $matches)) {
            return "<examples>\n" . trim($matches[1]) . "\n</examples>";
        }

        return '';
    }

    /**
     * Build output format section.
     */
    public function buildOutputSection(string $expectedFormat = 'json'): string
    {
        $template = $this->templateService->load('partials/output_format.md');

        if (empty($template)) {
            return $this->getDefaultOutputSection($expectedFormat);
        }

        return $this->templateService->render($template, ['expected_format' => $expectedFormat]);
    }

    /**
     * Compose everything into final prompt.
     *
     * @param RetrievedChunk[] $relevantChunks
     * @param array<string, mixed> $options
     */
    public function compose(
        Project $project,
        IntentAnalysis $intent,
        string $userMessage,
        array $relevantChunks,
        array $options = []
    ): ComposedPrompt {
        $agentName = $options['agent'] ?? $this->selectAgentForIntent($intent->intent_type);
        $techStack = $this->extractTechStack($project);

        $contextVariables = [
            'project_context' => $this->buildContextSection($project, $relevantChunks),
            'project_info' => $this->buildProjectInfo($project),
            'tech_stack' => $this->formatTechStack($project),
            'relevant_files' => $this->formatChunksForPrompt($relevantChunks),
            'user_request' => $userMessage,
            'framework' => $project->stack_info['framework'] ?? 'Unknown',
            'output_format' => $this->buildOutputSection($options['output_format'] ?? 'json'),
            'task_section' => $this->buildTaskSection($intent, $userMessage),
            'examples_section' => $this->buildExamplesSection($intent->intent_type),
        ];

        $systemPrompt = $this->templateService->getAgentSystemPrompt($agentName);
        $systemPrompt = $this->templateService->render($systemPrompt, $contextVariables);

        $taskTemplates = $this->templateService->selectForIntent($intent->intent_type, $techStack);
        $userPrompt = $this->templateService->compose($taskTemplates, $contextVariables);

        $userPrompt .= "\n\n" . $this->buildTaskSection($intent, $userMessage);

        return new ComposedPrompt(
            systemPrompt: $systemPrompt,
            userPrompt: $userPrompt,
            metadata: [
                'agent' => $agentName,
                'intent_type' => $intent->intent_type->value,
                'intent_id' => $intent->id,
                'complexity' => $intent->complexity_estimate->value,
                'tech_stack' => $techStack,
                'chunks_count' => count($relevantChunks),
                'templates_used' => $taskTemplates,
                'project_id' => $project->id,
                'built_at' => now()->toIso8601String(),
            ],
        );
    }

    /**
     * Build a quick prompt for simple queries (no full composition).
     *
     * @param RetrievedChunk[] $relevantChunks
     */
    public function composeQuick(
        Project $project,
        string $userMessage,
        array $relevantChunks
    ): ComposedPrompt {
        $systemPrompt = $this->buildQuickSystemPrompt($project);
        $userPrompt = $this->buildQuickUserPrompt($userMessage, $relevantChunks);

        return new ComposedPrompt(
            systemPrompt: $systemPrompt,
            userPrompt: $userPrompt,
            metadata: [
                'agent' => 'quick',
                'chunks_count' => count($relevantChunks),
                'project_id' => $project->id,
                'built_at' => now()->toIso8601String(),
            ],
        );
    }

    private function buildProjectInfo(Project $project): string
    {
        $stack = $project->stack_info ?? [];
        $info = "Repository: {$project->repo_full_name}\n";
        $info .= "Branch: {$project->default_branch}\n";
        $info .= "Files: {$project->total_files}\n";
        $info .= "Lines: {$project->total_lines}\n";

        if (!empty($stack['framework'])) {
            $info .= "Framework: {$stack['framework']}";
            if (!empty($stack['framework_version'])) {
                $info .= " {$stack['framework_version']}";
            }
            $info .= "\n";
        }

        if (!empty($stack['frontend'])) {
            $info .= "Frontend: " . implode(', ', $stack['frontend']) . "\n";
        }

        if (!empty($stack['database'])) {
            $info .= "Database: " . implode(', ', $stack['database']) . "\n";
        }

        return $info;
    }

    private function formatTechStack(Project $project): string
    {
        $stack = $project->stack_info ?? [];
        $parts = [];

        if (!empty($stack['framework'])) {
            $parts[] = $stack['framework'] . ($stack['framework_version'] ?? '');
        }

        if (!empty($stack['frontend'])) {
            $parts[] = implode(', ', $stack['frontend']);
        }

        if (!empty($stack['css'])) {
            $parts[] = implode(', ', $stack['css']);
        }

        if (!empty($stack['testing'])) {
            $parts[] = 'Testing: ' . implode(', ', $stack['testing']);
        }

        return implode(' | ', $parts) ?: 'Unknown stack';
    }

    /**
     * @param RetrievedChunk[] $chunks
     */
    private function formatChunksForPrompt(array $chunks): string
    {
        if (empty($chunks)) {
            return "No relevant code chunks available.";
        }

        $maxLines = $this->config['variables']['max_code_preview_lines'] ?? 100;
        $formatted = '';

        foreach ($chunks as $i => $chunk) {
            $num = $i + 1;
            $formatted .= "<chunk id=\"{$num}\" path=\"{$chunk->path}\" lines=\"{$chunk->startLine}-{$chunk->endLine}\">\n";

            if (!empty($chunk->symbolsDeclared)) {
                $symbols = array_slice($chunk->symbolsDeclared, 0, 5);
                $formatted .= "<!-- Declares: " . implode(', ', $symbols) . " -->\n";
            }

            $lines = explode("\n", $chunk->content);
            if (count($lines) > $maxLines) {
                $lines = array_slice($lines, 0, $maxLines);
                $lines[] = "// ... truncated ({$chunk->endLine} - {$chunk->startLine} total lines)";
            }

            $formatted .= "```" . ($chunk->language ?? '') . "\n";
            $formatted .= implode("\n", $lines);
            $formatted .= "\n```\n";
            $formatted .= "</chunk>\n\n";
        }

        return $formatted;
    }

    /**
     * @return array<string>
     */
    private function extractTechStack(Project $project): array
    {
        $stack = $project->stack_info ?? [];
        $techs = [];

        if (!empty($stack['framework'])) {
            $techs[] = strtolower($stack['framework']);
        }

        if (!empty($stack['frontend'])) {
            foreach ($stack['frontend'] as $frontend) {
                $techs[] = strtolower($frontend);
            }
        }

        if (!empty($stack['css'])) {
            foreach ($stack['css'] as $css) {
                $techs[] = strtolower($css);
            }
        }

        return array_unique($techs);
    }

    private function selectAgentForIntent(IntentType $intent): string
    {
        return match ($intent) {
            IntentType::FeatureRequest,
            IntentType::BugFix,
            IntentType::Refactoring => 'planner',
            IntentType::UiComponent => 'planner',
            IntentType::TestWriting => 'planner',
            IntentType::Question => 'orchestrator',
            default => 'orchestrator',
        };
    }

    private function getDefaultOutputSection(string $format): string
    {
        return <<<OUTPUT
            <output_format>
            Respond with a valid JSON object containing:
            - "success": boolean
            - "result": your analysis/plan/answer
            - "files_affected": array of file paths that would be modified
            - "confidence": "high" | "medium" | "low"
            </output_format>
        OUTPUT;
    }

    private function buildQuickSystemPrompt(Project $project): string
    {
        $stack = $project->stack_info ?? [];
        $framework = $stack['framework'] ?? 'Unknown';
        return <<<PROMPT
            You are a code analysis assistant for {$project->repo_full_name}.
            Framework: {$framework}
            Stack: {$this->formatTechStack($project)}

            Rules:
            1. Answer ONLY based on provided code chunks
            2. Cite file paths and line numbers
            3. Say "NOT ENOUGH CONTEXT" if information is missing
            4. Never fabricate code or function names
        PROMPT;
    }

    /**
     * @param RetrievedChunk[] $chunks
     */
    private function buildQuickUserPrompt(string $message, array $chunks): string
    {
        $prompt = "## Question\n{$message}\n\n## Code Context\n\n";
        $prompt .= $this->formatChunksForPrompt($chunks);
        return $prompt;
    }
}
