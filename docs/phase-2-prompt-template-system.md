# Phase 2: Prompt Template System

## Overview

The Prompt Template System is a dynamic, composable prompt management system for AIBuilder. It enables intelligent prompt selection based on user intent, automatic inclusion of stack-specific patterns, and structured composition of prompts for Claude API calls.

## Architecture

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                           Prompt Template System                             │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  ┌──────────────────┐    ┌──────────────────┐    ┌──────────────────┐       │
│  │ IntentAnalysis   │───▶│ PromptTemplate   │───▶│ PromptComposer   │       │
│  │ (from Phase 1)   │    │ Service          │    │                  │       │
│  └──────────────────┘    └──────────────────┘    └──────────────────┘       │
│           │                       │                       │                  │
│           │                       ▼                       ▼                  │
│           │              ┌──────────────────┐    ┌──────────────────┐       │
│           │              │ Template Files   │    │ ComposedPrompt   │       │
│           │              │ (resources/      │    │ DTO              │       │
│           │              │  prompts/)       │    └──────────────────┘       │
│           │              └──────────────────┘             │                  │
│           │                       │                       │                  │
│           ▼                       ▼                       ▼                  │
│  ┌──────────────────────────────────────────────────────────────────┐       │
│  │                        Claude API Call                            │       │
│  │  { system: "...", messages: [{ role: "user", content: "..." }] } │       │
│  └──────────────────────────────────────────────────────────────────┘       │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

## Components

### 1. Configuration (`config/prompts.php`)

Central configuration for all prompt-related settings.

```php
return [
    // Template caching
    'cache_templates' => env('CACHE_PROMPT_TEMPLATES', true),
    'cache_ttl' => env('PROMPT_CACHE_TTL', 3600),
    
    // Agent configurations
    'agents' => [
        'orchestrator' => [
            'system_prompt' => 'system/orchestrator.md',
            'model' => 'claude-sonnet-4-5-20250929',
            'max_tokens' => 4096,
            'temperature' => 0.3,
        ],
        'planner' => [
            'system_prompt' => 'system/code_planner.md',
            'max_tokens' => 8192,
            'temperature' => 0.4,
            'thinking_budget' => 16000,
        ],
        'executor' => [
            'system_prompt' => 'system/code_executor.md',
            'max_tokens' => 4096,
            'temperature' => 0.2,
        ],
    ],
    
    // Intent to template mapping
    'intent_templates' => [
        'feature_request' => [
            'primary' => 'tasks/feature_request.md',
            'includes' => ['partials/output_format.md', 'partials/file_change_format.md'],
        ],
        // ... other intents
    ],
    
    // Stack-specific templates
    'stack_templates' => [
        'laravel' => ['patterns' => 'stack/laravel/patterns.md', ...],
        'vue' => ['patterns' => 'stack/vue/patterns.md', ...],
    ],
];
```

### 2. PromptTemplateService (`app/Services/Prompts/PromptTemplateService.php`)

Core service for loading, caching, and composing templates.

#### Key Methods

| Method | Description |
|--------|-------------|
| `load(string $path)` | Load a template from disk with caching |
| `render(string $template, array $vars)` | Inject variables into template |
| `selectForIntent(IntentType, array $stack)` | Select templates based on intent + tech stack |
| `getAgentSystemPrompt(string $agent)` | Get system prompt for an agent |
| `getAgentConfig(string $agent)` | Get agent configuration (model, tokens, etc.) |
| `buildPrompt(...)` | Build complete ComposedPrompt |
| `compose(array $paths, array $vars)` | Compose multiple templates together |
| `listTemplates(string $dir)` | List available templates |
| `clearCache(?string $path)` | Clear template cache |

#### Usage Examples

```php
$service = app(PromptTemplateService::class);

// Load a single template
$template = $service->load('system/orchestrator.md');

// Render with variables
$rendered = $service->render($template, [
    'project_info' => $projectData,
    'tech_stack' => 'Laravel 11, Vue 3, Tailwind',
]);

// Select templates for an intent
$templates = $service->selectForIntent(
    IntentType::FeatureRequest,
    ['laravel', 'vue', 'tailwind']
);
// Returns: [
//   'tasks/feature_request.md',
//   'partials/output_format.md',
//   'partials/file_change_format.md',
//   'stack/laravel/patterns.md',
//   'stack/vue/patterns.md',
//   'stack/tailwind/patterns.md',
// ]

// Get agent config
$config = $service->getAgentConfig('planner');
// Returns: [
//   'system_prompt' => 'system/code_planner.md',
//   'max_tokens' => 8192,
//   'temperature' => 0.4,
//   'thinking_budget' => 16000,
// ]

// Build complete prompt
$composed = $service->buildPrompt(
    agentName: 'planner',
    intent: IntentType::FeatureRequest,
    techStack: ['laravel', 'vue'],
    context: [
        'project_info' => $projectInfo,
        'user_request' => 'Add dark mode toggle',
        'relevant_files' => $codeContext,
    ]
);
```

### 3. PromptComposer (`app/Services/Prompts/PromptComposer.php`)

High-level composition service that builds complete prompts with full context.

#### Key Methods

| Method | Description |
|--------|-------------|
| `buildContextSection(Project, array $chunks)` | Build project context with code chunks |
| `buildTaskSection(IntentAnalysis, string $message)` | Build task section from intent |
| `buildExamplesSection(IntentType)` | Extract examples from templates |
| `buildOutputSection(string $format)` | Build output format instructions |
| `compose(...)` | Full composition with all context |
| `composeQuick(...)` | Lightweight composition for simple queries |

#### Usage Examples

```php
$composer = app(PromptComposer::class);

// Full composition with intent analysis
$composed = $composer->compose(
    project: $project,
    intent: $intentAnalysis,
    userMessage: 'Add password reset functionality',
    relevantChunks: $retrievedChunks,
    options: ['agent' => 'planner', 'output_format' => 'json']
);

// Quick composition for simple questions
$composed = $composer->composeQuick(
    project: $project,
    userMessage: 'How does authentication work?',
    relevantChunks: $chunks
);

// Access composed prompt
$systemPrompt = $composed->systemPrompt;
$userPrompt = $composed->userPrompt;
$metadata = $composed->metadata;
```

### 4. ComposedPrompt DTO (`app/DTOs/ComposedPrompt.php`)

Immutable data transfer object representing a fully composed prompt.

#### Properties

```php
readonly class ComposedPrompt
{
    public function __construct(
        public string $systemPrompt,
        public string $userPrompt,
        public array $metadata = [],
    ) {}
}
```

#### Methods

| Method | Description |
|--------|-------------|
| `toMessages()` | Convert to Claude API format |
| `toMessagesWithHistory(array $history)` | Include conversation history |
| `estimateTokens()` | Estimate total token count |
| `getTokenBreakdown()` | Get per-component token estimates |
| `isWithinLimits(int $max)` | Check if within token limit |
| `getTemplatesUsed()` | Get list of templates used |
| `getAgentName()` | Get agent name from metadata |
| `getMeta(string $key)` | Get metadata value |
| `withMetadata(array $meta)` | Create copy with additional metadata |
| `jsonSerialize()` | Serialize to JSON |
| `fromArray(array $data)` | Create from array |

#### Usage Examples

```php
$composed = new ComposedPrompt(
    systemPrompt: 'You are an assistant...',
    userPrompt: 'Help me with this task...',
    metadata: ['agent' => 'planner', 'intent' => 'feature_request']
);

// Convert to Claude API format
$apiPayload = $composed->toMessages();
// Returns: [
//   'system' => 'You are an assistant...',
//   'messages' => [
//     ['role' => 'user', 'content' => 'Help me with this task...']
//   ]
// ]

// With conversation history
$apiPayload = $composed->toMessagesWithHistory([
    ['role' => 'user', 'content' => 'Previous question'],
    ['role' => 'assistant', 'content' => 'Previous answer'],
]);

// Token estimation
$tokens = $composed->estimateTokens(); // e.g., 2500
$breakdown = $composed->getTokenBreakdown();
// Returns: ['system' => 1500, 'user' => 1000, 'total' => 2500]

// Check limits
if ($composed->isWithinLimits(100000)) {
    // Safe to send
}

// Metadata access
$agent = $composed->getAgentName(); // 'planner'
$templates = $composed->getTemplatesUsed();
```

## Template Directory Structure

```
resources/prompts/
├── system/                          # Agent system prompts
│   ├── orchestrator.md              # Main coordinator agent
│   ├── intent_analyzer.md           # Intent classification (Phase 1)
│   ├── code_planner.md              # Implementation planning
│   └── code_executor.md             # Code generation
├── tasks/                           # Task-specific templates
│   ├── feature_request.md           # New feature implementation
│   ├── bug_fix.md                   # Bug diagnosis and fixing
│   ├── test_writing.md              # Test generation
│   ├── ui_component.md              # UI/frontend work
│   ├── refactoring.md               # Code improvement
│   └── question.md                  # Codebase questions
├── stack/                           # Stack-specific patterns
│   ├── laravel/
│   │   ├── patterns.md              # General Laravel conventions
│   │   ├── eloquent.md              # Model patterns
│   │   ├── controllers.md           # Controller patterns
│   │   └── migrations.md            # Migration patterns
│   └── vue/
│       ├── patterns.md              # Vue 3 conventions
│       ├── components.md            # Component patterns
│       └── composables.md           # Composable patterns
└── partials/                        # Reusable template fragments
    ├── output_format.md             # JSON output instructions
    ├── file_change_format.md        # File modification format
    └── error_handling.md            # Error handling guidelines
```

## Template Syntax

### Variable Placeholders

Templates use `{{VARIABLE_NAME}}` syntax for dynamic content:

```markdown
# {{FRAMEWORK}} Application

<project_info>
{{PROJECT_INFO}}
</project_info>

<tech_stack>
{{TECH_STACK}}
</tech_stack>

<user_request>
{{USER_REQUEST}}
</user_request>
```

### Available Variables

| Variable | Description |
|----------|-------------|
| `{{PROJECT_INFO}}` | Project metadata (name, files, lines) |
| `{{PROJECT_CONTEXT}}` | Full context with code chunks |
| `{{TECH_STACK}}` | Detected technology stack |
| `{{FRAMEWORK}}` | Primary framework name |
| `{{RELEVANT_FILES}}` | Formatted code chunks |
| `{{USER_REQUEST}}` | User's original message |
| `{{OUTPUT_FORMAT}}` | Output format instructions |
| `{{TASK_SECTION}}` | Intent analysis + user request |
| `{{EXAMPLES_SECTION}}` | Examples from template |

### XML Tag Structure

Templates follow Claude's prompt engineering best practices:

```markdown
<task_context>
Project and stack information here
</task_context>

<instructions>
Clear, specific instructions
</instructions>

<examples>
<example>
<request>Example user request</request>
<response>Example response</response>
</example>
</examples>

<output_format>
Expected output structure
</output_format>
```

## Agent Configurations

### Orchestrator
- **Purpose**: Main coordinator, routes tasks, synthesizes results
- **Temperature**: 0.3 (focused, consistent)
- **Max Tokens**: 4096
- **Use Cases**: Questions, task routing, high-level guidance

### Planner
- **Purpose**: Creates detailed implementation plans
- **Temperature**: 0.4 (balanced creativity/precision)
- **Max Tokens**: 8192
- **Thinking Budget**: 16000 (extended thinking enabled)
- **Use Cases**: Feature requests, bug fixes, refactoring

### Executor
- **Purpose**: Generates precise code changes
- **Temperature**: 0.2 (highly deterministic)
- **Max Tokens**: 4096
- **Use Cases**: File modifications, code generation

## Integration with Phase 1

The Prompt Template System integrates seamlessly with the Intent Classification System:

```php
use App\Services\AI\IntentAnalyzerService;
use App\Services\Prompts\PromptComposer;
use App\Services\AskAI\RetrievalService;

// Phase 1: Analyze intent
$analyzer = app(IntentAnalyzerService::class);
$intent = $analyzer->analyze($project, $userMessage);

// Retrieve relevant code
$retrieval = app(RetrievalService::class);
$chunks = $retrieval->retrieve($project, $userMessage);

// Phase 2: Compose prompt
$composer = app(PromptComposer::class);
$composed = $composer->compose(
    project: $project,
    intent: $intent,
    userMessage: $userMessage,
    relevantChunks: $chunks['chunks'],
);

// Ready for Claude API
$response = Http::withHeaders([
    'x-api-key' => config('services.anthropic.key'),
    'anthropic-version' => '2023-06-01',
])->post('https://api.anthropic.com/v1/messages', [
    'model' => config('prompts.agents.planner.model'),
    'max_tokens' => config('prompts.agents.planner.max_tokens'),
    'system' => $composed->systemPrompt,
    'messages' => $composed->toMessages()['messages'],
]);
```

## Service Provider Registration

Add to `config/app.php`:

```php
'providers' => [
    // ...
    App\Providers\PromptServiceProvider::class,
],
```

Or with auto-discovery in `composer.json`:

```json
{
    "extra": {
        "laravel": {
            "providers": [
                "App\\Providers\\PromptServiceProvider"
            ]
        }
    }
}
```

## Artisan Commands

### Clear Template Cache

```bash
# Clear all template caches
php artisan prompts:cache-clear --all

# Clear specific template
php artisan prompts:cache-clear system/orchestrator.md

# Clear in-memory cache only
php artisan prompts:cache-clear
```

## Testing

### Run Tests

```bash
# Run all prompt template tests
php artisan test --filter=PromptTemplateServiceTest

# Run with coverage
php artisan test --filter=PromptTemplateServiceTest --coverage
```

### Test Coverage

| Test | Assertions |
|------|------------|
| Template loading | File loading, caching, missing files |
| Variable rendering | String vars, array vars, JSON encoding |
| Intent selection | All intent types, stack combinations |
| Agent configuration | System prompts, configs, unknown agents |
| Prompt building | Full composition, metadata |
| Template management | Listing, existence, cache clearing |
| PromptComposer | Context, task, examples, output sections |
| ComposedPrompt DTO | Messages, history, tokens, serialization |

**Results**: 32 tests, 80 assertions, all passing

## File Reference

### Core Files

| File | Purpose |
|------|---------|
| `config/prompts.php` | Configuration |
| `app/DTOs/ComposedPrompt.php` | Prompt DTO |
| `app/Services/Prompts/PromptTemplateService.php` | Template service |
| `app/Services/Prompts/PromptComposer.php` | Composition service |
| `app/Providers/PromptServiceProvider.php` | Service provider |
| `app/Console/Commands/PromptCacheClearCommand.php` | Cache command |
| `tests/Feature/PromptTemplateServiceTest.php` | Test suite |

### Template Files

| File | Purpose |
|------|---------|
| `resources/prompts/system/orchestrator.md` | Orchestrator agent |
| `resources/prompts/system/code_planner.md` | Planner agent |
| `resources/prompts/system/code_executor.md` | Executor agent |
| `resources/prompts/tasks/feature_request.md` | Feature requests |
| `resources/prompts/tasks/bug_fix.md` | Bug fixes |
| `resources/prompts/tasks/test_writing.md` | Test generation |
| `resources/prompts/tasks/ui_component.md` | UI components |
| `resources/prompts/tasks/refactoring.md` | Refactoring |
| `resources/prompts/tasks/question.md` | Questions |
| `resources/prompts/partials/output_format.md` | Output format |
| `resources/prompts/partials/file_change_format.md` | File changes |
| `resources/prompts/partials/error_handling.md` | Error handling |
| `resources/prompts/stack/laravel/patterns.md` | Laravel patterns |
| `resources/prompts/stack/laravel/eloquent.md` | Eloquent patterns |
| `resources/prompts/stack/laravel/controllers.md` | Controller patterns |
| `resources/prompts/stack/laravel/migrations.md` | Migration patterns |
| `resources/prompts/stack/vue/patterns.md` | Vue patterns |
| `resources/prompts/stack/vue/components.md` | Component patterns |
| `resources/prompts/stack/vue/composables.md` | Composable patterns |

## Next Steps (Phase 3)

With the Prompt Template System complete, the next phase can implement:

1. **Planning Agent Service** - Uses planner templates to create implementation plans
2. **Execution Agent Service** - Uses executor templates to generate code changes
3. **Orchestrator Service** - Coordinates the full pipeline
4. **Agent Communication Protocol** - Structured handoff between agents

## Changelog

### v1.0.0 (Current)
- Initial implementation
- PromptTemplateService with caching
- PromptComposer for full composition
- ComposedPrompt DTO
- 19 prompt templates (system, tasks, stack, partials)
- Full test coverage (32 tests, 80 assertions)
- Integration with Phase 1 Intent Classification
