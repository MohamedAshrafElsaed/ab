# Phase 1: Intent Classification System

## Overview

The Intent Classification System is the first component of AIBuilder's Subagent Delegation Pattern. It analyzes user messages and outputs structured intent data that guides downstream agents (Planning Agent, Execution Agent) in fulfilling user requests.

## Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                        User Message                              │
└─────────────────────────────────────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────┐
│                   IntentAnalyzerService                          │
│  ┌───────────────┐  ┌──────────────┐  ┌──────────────────────┐  │
│  │ Project       │  │ Claude API   │  │ IntentAnalysisResult │  │
│  │ Context       │──│ (Sonnet 4.5) │──│ DTO                  │  │
│  └───────────────┘  └──────────────┘  └──────────────────────┘  │
└─────────────────────────────────────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────┐
│                     IntentAnalysis Model                         │
│  • intent_type          • domain_classification                  │
│  • confidence_score     • complexity_estimate                    │
│  • extracted_entities   • clarification_questions                │
└─────────────────────────────────────────────────────────────────┘
                                │
                                ▼
                    ┌─────────────────────┐
                    │   Planning Agent    │
                    │     (Phase 2)       │
                    └─────────────────────┘
```

## Installation

### 1. Create Required Files

```
app/
├── DTOs/
│   └── IntentAnalysisResult.php
├── Enums/
│   ├── ComplexityLevel.php
│   └── IntentType.php
├── Models/
│   └── IntentAnalysis.php
├── Providers/
│   └── IntentAnalyzerServiceProvider.php
└── Services/
    └── AI/
        └── IntentAnalyzerService.php

config/
└── intent_analyzer.php

database/
├── factories/
│   └── IntentAnalysisFactory.php
└── migrations/
    └── xxxx_xx_xx_create_intent_analyses_table.php

resources/
└── prompts/
    └── intent_analyzer.md

tests/
└── Feature/
    └── IntentAnalyzerServiceTest.php
```

### 2. Register Service Provider

Add to `config/app.php` or `bootstrap/providers.php`:

```php
App\Providers\IntentAnalyzerServiceProvider::class,
```

### 3. Run Migration

```bash
php artisan migrate
```

### 4. Configure Environment

```env
# Required
ANTHROPIC_API_KEY=your-anthropic-api-key

# Optional (with defaults)
INTENT_ANALYZER_MODEL=claude-sonnet-4-5-20250929
INTENT_ANALYZER_MAX_TOKENS=1024
INTENT_CLARIFICATION_THRESHOLD=0.5
INTENT_ANALYZER_LOGGING=true
```

### 5. Publish Config (Optional)

```bash
php artisan vendor:publish --tag=intent-analyzer-config
php artisan vendor:publish --tag=intent-analyzer-prompts
```

---

## Components

### Enums

#### IntentType

Defines the types of user intents the system can classify.

```php
use App\Enums\IntentType;

IntentType::FeatureRequest  // User wants to add new functionality
IntentType::BugFix          // User wants to fix an existing issue
IntentType::TestWriting     // User wants to create or update tests
IntentType::UiComponent     // User wants to create/modify UI elements
IntentType::Refactoring     // User wants to improve code structure
IntentType::Question        // User is asking about the codebase
IntentType::Clarification   // User is providing additional context
IntentType::Unknown         // Intent cannot be determined
```

**Methods:**

| Method | Return Type | Description |
|--------|-------------|-------------|
| `label()` | `string` | Human-readable name |
| `description()` | `string` | Detailed description |
| `requiresCodeChanges()` | `bool` | Whether this intent modifies code |
| `defaultComplexity()` | `ComplexityLevel` | Default complexity for this type |
| `values()` | `array<string>` | All enum values as strings |

**Example:**

```php
$type = IntentType::FeatureRequest;

$type->label();              // "Feature Request"
$type->requiresCodeChanges(); // true
$type->defaultComplexity();  // ComplexityLevel::Medium
```

#### ComplexityLevel

Defines the estimated complexity of a requested change.

```php
use App\Enums\ComplexityLevel;

ComplexityLevel::Trivial  // Single file, few lines, minimal risk
ComplexityLevel::Simple   // 1-3 files, straightforward, low risk
ComplexityLevel::Medium   // 3-10 files, moderate effort
ComplexityLevel::Complex  // 10-25 files, significant effort
ComplexityLevel::Major    // 25+ files, architectural impact
```

**Methods:**

| Method | Return Type | Description |
|--------|-------------|-------------|
| `label()` | `string` | Human-readable name |
| `description()` | `string` | Detailed description |
| `estimatedHours()` | `array{min, max}` | Time range in hours |
| `estimatedFilesAffected()` | `array{min, max}` | File count range |
| `weight()` | `int` | Numeric weight (1-5) |
| `isHigherThan(ComplexityLevel)` | `bool` | Compare complexity |
| `fromScore(float)` | `ComplexityLevel` | Create from 0-1 score |

**Example:**

```php
$complexity = ComplexityLevel::Medium;

$complexity->estimatedHours();        // ['min' => 2.0, 'max' => 8.0]
$complexity->estimatedFilesAffected(); // ['min' => 3, 'max' => 10]
$complexity->weight();                // 3
$complexity->isHigherThan(ComplexityLevel::Simple); // true

ComplexityLevel::fromScore(0.75); // ComplexityLevel::Complex
```

---

### Model: IntentAnalysis

Eloquent model for persisted intent analyses.

#### Table Schema

```sql
CREATE TABLE intent_analyses (
    id UUID PRIMARY KEY,
    project_id UUID NOT NULL REFERENCES projects(id),
    conversation_id UUID NOT NULL,
    message_id UUID NOT NULL,
    raw_input TEXT NOT NULL,
    intent_type VARCHAR(50) NOT NULL,
    confidence_score DECIMAL(3,2) DEFAULT 0.00,
    extracted_entities JSON,
    domain_classification JSON,
    complexity_estimate VARCHAR(20) DEFAULT 'medium',
    requires_clarification BOOLEAN DEFAULT FALSE,
    clarification_questions JSON,
    metadata JSON,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

#### Relationships

```php
$analysis->project; // BelongsTo Project
```

#### Accessors

```php
$analysis->primary_domain;      // string - Primary domain from classification
$analysis->secondary_domains;   // array  - Secondary domains
$analysis->mentioned_files;     // array  - Files from extracted_entities
$analysis->mentioned_components; // array  - Components from extracted_entities
$analysis->mentioned_features;  // array  - Features from extracted_entities
$analysis->processing_time;     // float  - Processing time in ms
$analysis->tokens_used;         // int    - Total tokens used
```

#### Scopes

```php
// Filter by conversation
IntentAnalysis::forConversation($conversationId)->get();

// Filter by intent type
IntentAnalysis::ofType(IntentType::FeatureRequest)->get();
IntentAnalysis::ofTypes([IntentType::FeatureRequest, IntentType::BugFix])->get();

// Filter by complexity
IntentAnalysis::withComplexity(ComplexityLevel::Medium)->get();

// Filter by confidence
IntentAnalysis::highConfidence(0.8)->get();  // >= 0.8
IntentAnalysis::lowConfidence(0.5)->get();   // < 0.5

// Filter by clarification status
IntentAnalysis::needingClarification()->get();

// Filter by code change requirement
IntentAnalysis::query()->requiresCodeChanges()->get();

// Filter by domain
IntentAnalysis::inDomain('auth')->get();
```

#### Helper Methods

```php
$analysis->isHighConfidence(0.8);     // bool
$analysis->isLowConfidence(0.5);      // bool
$analysis->doesRequireCodeChanges(); // bool
$analysis->hasMentionedFiles();       // bool
$analysis->hasMentionedComponents();  // bool
$analysis->toSummaryArray();          // Condensed array representation
```

---

### DTO: IntentAnalysisResult

Immutable data transfer object for analysis results before persistence.

#### Properties

| Property | Type | Description |
|----------|------|-------------|
| `intentType` | `IntentType` | Classified intent |
| `confidenceScore` | `float` | 0.0 to 1.0 confidence |
| `extractedEntities` | `array` | Files, components, features, symbols |
| `domainClassification` | `array` | Primary and secondary domains |
| `complexityEstimate` | `ComplexityLevel` | Estimated complexity |
| `requiresClarification` | `bool` | Whether clarification needed |
| `clarificationQuestions` | `array<string>` | Questions to ask user |
| `metadata` | `array` | Processing metadata |

#### Factory Methods

```php
// From Claude API response
$result = IntentAnalysisResult::fromClaudeResponse($jsonData, $metadata);

// For ambiguous requests
$result = IntentAnalysisResult::needsClarification(
    questions: ['What file should I modify?'],
    primaryDomain: 'general'
);

// For failed analysis
$result = IntentAnalysisResult::failed('API timeout');
```

#### Methods

```php
$result->isHighConfidence(0.8);  // bool
$result->isFailed();             // bool
$result->getPrimaryDomain();     // string
$result->getSecondaryDomains();  // array<string>
$result->getMentionedFiles();    // array<string>
$result->getMentionedComponents(); // array<string>
$result->toArray();              // Full array representation
```

---

### Service: IntentAnalyzerService

Core service for analyzing user messages.

#### Basic Usage

```php
use App\Services\AI\IntentAnalyzerService;
use App\Models\Project;

$analyzer = app(IntentAnalyzerService::class);

// Analyze a message
$analysis = $analyzer->analyze(
    project: $project,
    userMessage: 'Add a dark mode toggle to the settings page'
);

// With conversation history
$analysis = $analyzer->analyze(
    project: $project,
    userMessage: 'Yes, add it to that component',
    conversationHistory: [
        ['role' => 'user', 'content' => 'Where is the theme switcher?'],
        ['role' => 'assistant', 'content' => 'It is in SettingsPanel.vue'],
    ],
    conversationId: 'conv-123',
    messageId: 'msg-456'
);
```

#### Methods

##### analyze()

Analyzes a user message and returns a persisted `IntentAnalysis`.

```php
public function analyze(
    Project $project,
    string $userMessage,
    array $conversationHistory = [],
    ?string $conversationId = null,
    ?string $messageId = null
): IntentAnalysis
```

##### needsClarification()

Determines if an analysis requires user clarification.

```php
public function needsClarification(IntentAnalysis $analysis): bool
```

Returns `true` if:
- `requires_clarification` is true
- `confidence_score` < threshold (default 0.5)
- `intent_type` is `Unknown`

##### generateClarificationQuestions()

Generates helpful clarification questions.

```php
public function generateClarificationQuestions(IntentAnalysis $analysis): array
```

Returns an array of 1-3 contextual questions based on what's missing.

##### reanalyzeWithClarification()

Re-analyzes with additional context from user clarification.

```php
public function reanalyzeWithClarification(
    IntentAnalysis $originalAnalysis,
    string $clarificationMessage
): IntentAnalysis
```

##### detectMultipleIntents()

Detects if a message contains multiple intents.

```php
public function detectMultipleIntents(string $userMessage): array
```

Returns:
```php
[
    'is_multi_intent' => true,
    'detected_intents' => ['feature_request', 'bug_fix'],
    'suggestion' => 'Consider breaking into separate messages...'
]
```

---

## Configuration

### config/intent_analyzer.php

```php
return [
    // AI Provider
    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model' => env('INTENT_ANALYZER_MODEL', 'claude-sonnet-4-5-20250929'),
        'max_tokens' => env('INTENT_ANALYZER_MAX_TOKENS', 1024),
    ],

    // When to require clarification
    'clarification_threshold' => env('INTENT_CLARIFICATION_THRESHOLD', 0.5),
    'max_clarification_questions' => 3,

    // Confidence level thresholds
    'confidence' => [
        'high' => 0.8,
        'medium' => 0.5,
        'low' => 0.3,
    ],

    // Multi-intent detection
    'multi_intent' => [
        'enabled' => true,
        'suggest_split' => true,
    ],

    // Logging
    'logging' => [
        'enabled' => env('INTENT_ANALYZER_LOGGING', true),
        'log_raw_responses' => env('INTENT_ANALYZER_LOG_RAW', false),
    ],
];
```

---

## Prompt Template

Located at `resources/prompts/intent_analyzer.md`, the prompt template uses:

- **XML tags** for structured input (context, examples, instructions)
- **Multishot examples** (4 examples covering different scenarios)
- **Strict JSON schema** for output format
- **Project context** (tech stack, file count, framework)
- **Conversation history** for context-aware analysis

### Template Variables

| Variable | Description |
|----------|-------------|
| `{{PROJECT_INFO}}` | JSON with project name, branch, file count |
| `{{TECH_STACK}}` | Framework, frontend, features list |
| `{{CONVERSATION_HISTORY}}` | Recent conversation messages |
| `{{USER_MESSAGE}}` | The message to analyze |

---

## Domain Categories

The system recognizes these domain categories:

| Domain | Description |
|--------|-------------|
| `auth` | Authentication, authorization, sessions, guards |
| `users` | User management, profiles, settings |
| `api` | API endpoints, REST, GraphQL, webhooks |
| `database` | Models, migrations, relationships, queries |
| `ui` | Components, views, layouts, styling |
| `testing` | Unit tests, feature tests, integration |
| `config` | Configuration, environment, settings |
| `services` | Business logic, services, actions |
| `jobs` | Queues, jobs, scheduled tasks |
| `events` | Events, listeners, notifications |
| `general` | Default when no specific domain applies |

---

## Usage Examples

### Basic Analysis

```php
$analyzer = app(IntentAnalyzerService::class);

$analysis = $analyzer->analyze(
    $project,
    'Add pagination to the users table'
);

echo $analysis->intent_type->label();     // "Feature Request"
echo $analysis->confidence_score;          // 0.92
echo $analysis->complexity_estimate->label(); // "Simple"
echo $analysis->primary_domain;            // "ui"
```

### Handling Clarification

```php
$analysis = $analyzer->analyze($project, 'Make it faster');

if ($analyzer->needsClarification($analysis)) {
    $questions = $analyzer->generateClarificationQuestions($analysis);
    
    // Ask user and get response...
    $userClarification = 'I mean the dashboard page load time';
    
    $newAnalysis = $analyzer->reanalyzeWithClarification(
        $analysis,
        $userClarification
    );
}
```

### Multi-Intent Detection

```php
$result = $analyzer->detectMultipleIntents(
    'Fix the login bug and add a forgot password feature'
);

if ($result['is_multi_intent']) {
    // Suggest user split the request
    echo $result['suggestion'];
    echo "Detected: " . implode(', ', $result['detected_intents']);
}
```

### Querying Past Analyses

```php
use App\Models\IntentAnalysis;
use App\Enums\IntentType;

// Get all feature requests for a project
$features = IntentAnalysis::where('project_id', $projectId)
    ->ofType(IntentType::FeatureRequest)
    ->highConfidence()
    ->latest()
    ->get();

// Get analyses needing clarification
$pending = IntentAnalysis::forConversation($conversationId)
    ->needingClarification()
    ->get();

// Get complex tasks in auth domain
$authTasks = IntentAnalysis::inDomain('auth')
    ->withComplexity(ComplexityLevel::Complex)
    ->get();
```

---

## Testing

### Run Tests

```bash
# Run all Intent Analyzer tests
php artisan test --filter=IntentAnalyzerServiceTest

# Run with verbose output
php artisan test --filter=IntentAnalyzerServiceTest -v
```

### Test Coverage

| Test | Description |
|------|-------------|
| `analyzes_clear_feature_request` | Verifies clear intent classification |
| `analyzes_ambiguous_bug_report` | Tests low-confidence scenarios |
| `handles_multi_intent_message` | Tests multi-intent detection |
| `request_needing_clarification` | Tests clarification flow |
| `handles_different_complexity_levels` | Tests complexity estimation |
| `dto_creation_from_claude_response` | Tests DTO parsing |
| `dto_handles_malformed_response` | Tests error recovery |
| `high_confidence_does_not_need_clarification` | Tests confidence threshold |
| `low_confidence_needs_clarification` | Tests clarification trigger |
| `model_scopes_for_feature_request` | Tests Eloquent scopes |
| `model_scopes_for_bug_fix` | Tests Eloquent scopes |
| `handles_api_failure_gracefully` | Tests error handling |
| `handles_malformed_json_response` | Tests JSON parsing errors |
| `enum_values_and_methods` | Tests enum functionality |
| `conversation_history_formatting` | Tests context handling |

### Using the Factory

```php
use App\Models\IntentAnalysis;

// Create with defaults
$analysis = IntentAnalysis::factory()->create();

// Specific states
$analysis = IntentAnalysis::factory()
    ->featureRequest()
    ->highConfidence()
    ->create();

$analysis = IntentAnalysis::factory()
    ->bugFix()
    ->inDomain('auth')
    ->create();

$analysis = IntentAnalysis::factory()
    ->needingClarification()
    ->create();
```

---

## Error Handling

The service handles errors gracefully:

```php
// API failures return a valid IntentAnalysis with:
// - intent_type: Unknown
// - requires_clarification: true
// - metadata['error']: Error message
// - metadata['failed']: true

$analysis = $analyzer->analyze($project, 'Some message');

if ($analysis->metadata['failed'] ?? false) {
    Log::error('Analysis failed', [
        'error' => $analysis->metadata['error']
    ]);
}
```

---

## Integration with Phase 2

The Intent Classification System provides structured data for the Planning Agent:

```php
// Phase 1: Classify Intent
$analysis = $analyzer->analyze($project, $userMessage);

// Phase 2: Create Plan (upcoming)
if (!$analyzer->needsClarification($analysis)) {
    $plan = $planningAgent->createPlan(
        project: $project,
        intentAnalysis: $analysis
    );
}
```

### Data Flow to Planning Agent

```php
[
    'intent_type' => 'feature_request',
    'complexity' => 'medium',
    'primary_domain' => 'ui',
    'secondary_domains' => ['api'],
    'mentioned_files' => ['UserController.php'],
    'mentioned_components' => ['user table', 'pagination'],
    'confidence' => 0.92,
]
```

---

## File Reference

| File | Purpose |
|------|---------|
| `app/Enums/IntentType.php` | Intent type enum |
| `app/Enums/ComplexityLevel.php` | Complexity level enum |
| `app/Models/IntentAnalysis.php` | Eloquent model |
| `app/DTOs/IntentAnalysisResult.php` | Data transfer object |
| `app/Services/AI/IntentAnalyzerService.php` | Core service |
| `app/Providers/IntentAnalyzerServiceProvider.php` | Service provider |
| `config/intent_analyzer.php` | Configuration |
| `resources/prompts/intent_analyzer.md` | Claude prompt template |
| `database/migrations/xxxx_create_intent_analyses_table.php` | Database schema |
| `database/factories/IntentAnalysisFactory.php` | Test factory |
| `tests/Feature/IntentAnalyzerServiceTest.php` | Test suite |

---

## Changelog

### v1.0.0 (Initial Release)

- Intent classification with 8 intent types
- 5-level complexity estimation
- Domain classification (primary + secondary)
- Entity extraction (files, components, features, symbols)
- Clarification detection and question generation
- Multi-intent detection
- Conversation history support
- Comprehensive test suite (17 tests, 54 assertions)
