<?php

namespace Tests\Feature;

use App\DTOs\ComposedPrompt;
use App\Enums\ComplexityLevel;
use App\Enums\IntentType;
use App\Models\IntentAnalysis;
use App\Models\Project;
use App\Models\User;
use App\Services\AskAI\DTO\RetrievedChunk;
use App\Services\Prompts\PromptComposer;
use App\Services\Prompts\PromptTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PromptTemplateServiceTest extends TestCase
{
    use RefreshDatabase;

    private PromptTemplateService $templateService;
    private PromptComposer $composer;
    private Project $project;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->templateService = app(PromptTemplateService::class);
        $this->composer = app(PromptComposer::class);

        $this->user = User::factory()->create();
        $this->project = Project::factory()->create([
            'user_id' => $this->user->id,
            'repo_full_name' => 'test/repository',
            'default_branch' => 'main',
            'total_files' => 150,
            'total_lines' => 25000,
            'stack_info' => [
                'framework' => 'laravel',
                'framework_version' => '11.0',
                'frontend' => ['vue', 'inertia'],
                'css' => ['tailwind'],
                'database' => ['mysql'],
                'testing' => ['phpunit', 'pest'],
            ],
        ]);
    }

    public function test_loads_template_from_disk(): void
    {
        $template = $this->templateService->load('system/orchestrator.md');

        $this->assertNotEmpty($template);
        $this->assertStringContainsString('Orchestrator Agent', $template);
    }

    public function test_returns_empty_string_for_missing_template(): void
    {
        $template = $this->templateService->load('nonexistent/template.md');

        $this->assertEquals('', $template);
    }

    public function test_caches_loaded_templates(): void
    {
        config(['prompts.cache_templates' => true]);

        $template1 = $this->templateService->load('system/orchestrator.md');
        $template2 = $this->templateService->load('system/orchestrator.md');

        $this->assertEquals($template1, $template2);
    }

    public function test_renders_variables_into_template(): void
    {
        $template = "Hello {{NAME}}, your project is {{PROJECT}}.";
        $rendered = $this->templateService->render($template, [
            'name' => 'John',
            'project' => 'AIBuilder',
        ]);

        $this->assertEquals('Hello John, your project is AIBuilder.', $rendered);
    }

    public function test_renders_array_variables_as_json(): void
    {
        $template = "Stack: {{TECH_STACK}}";
        $rendered = $this->templateService->render($template, [
            'tech_stack' => ['laravel', 'vue', 'tailwind'],
        ]);

        $this->assertStringContainsString('laravel', $rendered);
        $this->assertStringContainsString('vue', $rendered);
    }

    public function test_selects_templates_for_feature_request_intent(): void
    {
        $templates = $this->templateService->selectForIntent(
            IntentType::FeatureRequest,
            ['laravel', 'vue']
        );

        $this->assertContains('tasks/feature_request.md', $templates);
        $this->assertContains('stack/laravel/patterns.md', $templates);
        $this->assertContains('stack/vue/patterns.md', $templates);
    }

    public function test_selects_templates_for_bug_fix_intent(): void
    {
        $templates = $this->templateService->selectForIntent(
            IntentType::BugFix,
            ['laravel']
        );

        $this->assertContains('tasks/bug_fix.md', $templates);
        $this->assertContains('stack/laravel/patterns.md', $templates);
    }

    public function test_selects_templates_for_test_writing_intent(): void
    {
        $templates = $this->templateService->selectForIntent(
            IntentType::TestWriting,
            []
        );

        $this->assertContains('tasks/test_writing.md', $templates);
    }

    public function test_gets_agent_system_prompt(): void
    {
        $prompt = $this->templateService->getAgentSystemPrompt('orchestrator');

        $this->assertNotEmpty($prompt);
        $this->assertStringContainsString('Orchestrator', $prompt);
    }

    public function test_gets_agent_config(): void
    {
        $config = $this->templateService->getAgentConfig('planner');

        $this->assertArrayHasKey('max_tokens', $config);
        $this->assertArrayHasKey('temperature', $config);
        $this->assertArrayHasKey('thinking_budget', $config);
        $this->assertEquals(8192, $config['max_tokens']);
    }

    public function test_returns_empty_for_unknown_agent(): void
    {
        $prompt = $this->templateService->getAgentSystemPrompt('unknown_agent');

        $this->assertEquals('', $prompt);
    }

    public function test_builds_complete_prompt_for_agent(): void
    {
        $composed = $this->templateService->buildPrompt(
            agentName: 'planner',
            intent: IntentType::FeatureRequest,
            techStack: ['laravel', 'vue'],
            context: [
                'project_info' => 'Test Project',
                'user_request' => 'Add dark mode',
            ]
        );

        $this->assertInstanceOf(ComposedPrompt::class, $composed);
        $this->assertNotEmpty($composed->systemPrompt);
        $this->assertNotEmpty($composed->userPrompt);
        $this->assertEquals('planner', $composed->getAgentName());
        $this->assertContains('tasks/feature_request.md', $composed->getTemplatesUsed());
    }

    public function test_composes_multiple_templates(): void
    {
        $composed = $this->templateService->compose([
            'partials/output_format.md',
            'partials/file_change_format.md',
        ], [
            'expected_format' => 'json',
        ]);

        $this->assertStringContainsString('output', strtolower($composed));
        $this->assertStringContainsString('file', strtolower($composed));
    }

    public function test_lists_available_templates(): void
    {
        $templates = $this->templateService->listTemplates();

        $this->assertIsArray($templates);
        $this->assertContains('system/orchestrator.md', $templates);
        $this->assertContains('tasks/feature_request.md', $templates);
    }

    public function test_lists_templates_in_subdirectory(): void
    {
        $templates = $this->templateService->listTemplates('stack/laravel');

        $this->assertContains('stack/laravel/patterns.md', $templates);
        $this->assertContains('stack/laravel/eloquent.md', $templates);
    }

    public function test_checks_template_exists(): void
    {
        $this->assertTrue($this->templateService->exists('system/orchestrator.md'));
        $this->assertFalse($this->templateService->exists('nonexistent.md'));
    }

    public function test_clears_specific_template_cache(): void
    {
        config(['prompts.cache_templates' => true]);

        $this->templateService->load('system/orchestrator.md');
        $this->templateService->clearCache('system/orchestrator.md');

        $this->assertNull(Cache::get('prompt_template:' . md5('system/orchestrator.md')));
    }

    public function test_clears_all_template_cache(): void
    {
        config(['prompts.cache_templates' => true]);

        $this->templateService->load('system/orchestrator.md');
        $this->templateService->load('system/code_planner.md');
        $this->templateService->clearCache();

        // Memory cache should be cleared
        $this->assertTrue(true);
    }

    // PromptComposer Tests

    public function test_composer_builds_context_section(): void
    {
        $chunks = [
            new RetrievedChunk(
                chunkId: 'chunk-1',
                path: 'app/Models/User.php',
                startLine: 1,
                endLine: 50,
                sha1: 'abc123',
                content: '<?php class User extends Model {}',
                relevanceScore: 0.95,
                symbolsDeclared: ['User'],
            ),
        ];

        $context = $this->composer->buildContextSection($this->project, $chunks);

        $this->assertStringContainsString('<project_context>', $context);
        $this->assertStringContainsString('test/repository', $context);
        $this->assertStringContainsString('<relevant_code>', $context);
        $this->assertStringContainsString('User.php', $context);
    }

    public function test_composer_builds_task_section(): void
    {
        $intent = IntentAnalysis::factory()->create([
            'project_id' => $this->project->id,
            'intent_type' => IntentType::FeatureRequest->value,
            'confidence_score' => 0.92,
            'complexity_estimate' => ComplexityLevel::Medium->value,
            'domain_classification' => ['primary' => 'ui', 'secondary' => ['frontend']],
            'extracted_entities' => ['files' => ['Settings.vue'], 'symbols' => ['toggleDarkMode']],
        ]);

        $taskSection = $this->composer->buildTaskSection($intent, 'Add dark mode toggle');

        $this->assertStringContainsString('<task>', $taskSection);
        $this->assertStringContainsString('Add dark mode toggle', $taskSection);
        $this->assertStringContainsString('Feature Request', $taskSection);
        $this->assertStringContainsString('0.92', $taskSection);
        $this->assertStringContainsString('Settings.vue', $taskSection);
    }

    public function test_composer_builds_examples_section(): void
    {
        $examples = $this->composer->buildExamplesSection(IntentType::FeatureRequest);

        $this->assertStringContainsString('<examples>', $examples);
        $this->assertStringContainsString('<example>', $examples);
    }

    public function test_composer_builds_output_section(): void
    {
        $output = $this->composer->buildOutputSection('json');

        $this->assertStringContainsString('JSON', $output);
        $this->assertStringContainsString('success', $output);
    }

    public function test_composer_composes_full_prompt(): void
    {
        $intent = IntentAnalysis::factory()->create([
            'project_id' => $this->project->id,
            'intent_type' => IntentType::FeatureRequest->value,
            'confidence_score' => 0.9,
            'complexity_estimate' => ComplexityLevel::Medium->value,
            'domain_classification' => ['primary' => 'ui', 'secondary' => []],
        ]);

        $chunks = [
            new RetrievedChunk(
                chunkId: 'chunk-1',
                path: 'app/Http/Controllers/SettingsController.php',
                startLine: 1,
                endLine: 30,
                sha1: 'def456',
                content: '<?php class SettingsController {}',
                relevanceScore: 0.88,
            ),
        ];

        $composed = $this->composer->compose(
            $this->project,
            $intent,
            'Add dark mode to settings',
            $chunks,
            ['agent' => 'planner']
        );

        $this->assertInstanceOf(ComposedPrompt::class, $composed);
        $this->assertNotEmpty($composed->systemPrompt);
        $this->assertNotEmpty($composed->userPrompt);
        $this->assertEquals('planner', $composed->getMeta('agent'));
        $this->assertEquals(1, $composed->getMeta('chunks_count'));
        $this->assertStringContainsString('dark mode', $composed->userPrompt);
    }

    public function test_composer_creates_quick_prompt(): void
    {
        $chunks = [
            new RetrievedChunk(
                chunkId: 'chunk-1',
                path: 'routes/web.php',
                startLine: 1,
                endLine: 20,
                sha1: 'xyz789',
                content: "Route::get('/settings', [SettingsController::class, 'index']);",
                relevanceScore: 0.75,
            ),
        ];

        $composed = $this->composer->composeQuick(
            $this->project,
            'What routes are defined?',
            $chunks
        );

        $this->assertInstanceOf(ComposedPrompt::class, $composed);
        $this->assertEquals('quick', $composed->getMeta('agent'));
        $this->assertStringContainsString('routes', strtolower($composed->userPrompt));
    }

    // ComposedPrompt DTO Tests

    public function test_composed_prompt_converts_to_messages(): void
    {
        $prompt = new ComposedPrompt(
            systemPrompt: 'You are an assistant.',
            userPrompt: 'Help me with this task.',
            metadata: ['agent' => 'test'],
        );

        $messages = $prompt->toMessages();

        $this->assertArrayHasKey('system', $messages);
        $this->assertArrayHasKey('messages', $messages);
        $this->assertEquals('You are an assistant.', $messages['system']);
        $this->assertEquals('user', $messages['messages'][0]['role']);
    }

    public function test_composed_prompt_includes_history(): void
    {
        $prompt = new ComposedPrompt(
            systemPrompt: 'System prompt',
            userPrompt: 'Current message',
        );

        $history = [
            ['role' => 'user', 'content' => 'Previous question'],
            ['role' => 'assistant', 'content' => 'Previous answer'],
        ];

        $messages = $prompt->toMessagesWithHistory($history);

        $this->assertCount(3, $messages['messages']);
        $this->assertEquals('Previous question', $messages['messages'][0]['content']);
        $this->assertEquals('Current message', $messages['messages'][2]['content']);
    }

    public function test_composed_prompt_estimates_tokens(): void
    {
        $prompt = new ComposedPrompt(
            systemPrompt: str_repeat('a', 400),
            userPrompt: str_repeat('b', 400),
        );

        $tokens = $prompt->estimateTokens();

        $this->assertEquals(200, $tokens);
    }

    public function test_composed_prompt_token_breakdown(): void
    {
        $prompt = new ComposedPrompt(
            systemPrompt: str_repeat('a', 400),
            userPrompt: str_repeat('b', 200),
        );

        $breakdown = $prompt->getTokenBreakdown();

        $this->assertEquals(100, $breakdown['system']);
        $this->assertEquals(50, $breakdown['user']);
        $this->assertEquals(150, $breakdown['total']);
    }

    public function test_composed_prompt_checks_token_limits(): void
    {
        $prompt = new ComposedPrompt(
            systemPrompt: str_repeat('a', 1000),
            userPrompt: str_repeat('b', 1000),
        );

        $this->assertTrue($prompt->isWithinLimits(1000));
        $this->assertFalse($prompt->isWithinLimits(400));
    }

    public function test_composed_prompt_with_additional_metadata(): void
    {
        $prompt = new ComposedPrompt(
            systemPrompt: 'System',
            userPrompt: 'User',
            metadata: ['original' => 'value'],
        );

        $newPrompt = $prompt->withMetadata(['added' => 'new_value']);

        $this->assertEquals('value', $newPrompt->getMeta('original'));
        $this->assertEquals('new_value', $newPrompt->getMeta('added'));
    }

    public function test_composed_prompt_serializes_to_json(): void
    {
        $prompt = new ComposedPrompt(
            systemPrompt: 'System prompt',
            userPrompt: 'User prompt',
            metadata: ['agent' => 'test'],
        );

        $json = json_encode($prompt);
        $decoded = json_decode($json, true);

        $this->assertEquals('System prompt', $decoded['system_prompt']);
        $this->assertEquals('User prompt', $decoded['user_prompt']);
        $this->assertEquals('test', $decoded['metadata']['agent']);
        $this->assertArrayHasKey('token_estimate', $decoded);
    }

    public function test_composed_prompt_creates_from_array(): void
    {
        $data = [
            'system_prompt' => 'System',
            'user_prompt' => 'User',
            'metadata' => ['key' => 'value'],
        ];

        $prompt = ComposedPrompt::fromArray($data);

        $this->assertEquals('System', $prompt->systemPrompt);
        $this->assertEquals('User', $prompt->userPrompt);
        $this->assertEquals('value', $prompt->getMeta('key'));
    }
}
