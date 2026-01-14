# Phase 4: Planning Agent System

## Overview

Phase 4 introduces the Planning Agent - an intelligent system that analyzes user requests with full codebase context and creates detailed, reviewable execution plans before any code changes are made. This follows the **Subagent Delegation Pattern** where user requests flow through intent analysis, context retrieval, and planning before execution.

## Architecture

```
User Request
    → IntentAnalyzerService (Phase 1)
    → ContextRetrievalService (Phase 3)
    → PlanningAgentService (Phase 4)
        → Extended Thinking (16k tokens)
        → Plan Generation
        → Risk Assessment
        → Validation
    → User Review
    → Execution (Phase 5 - future)
```

## Components

### 1. Database Schema

**Table: `execution_plans`**

| Column | Type | Description |
|--------|------|-------------|
| id | uuid | Primary key |
| project_id | uuid | Foreign key to projects |
| conversation_id | uuid | Groups related plans |
| intent_analysis_id | uuid | Foreign key to intent_analyses |
| status | enum | Plan lifecycle status |
| title | string | Human-readable summary |
| description | text | Detailed explanation |
| plan_data | json | Approach, testing notes, etc. |
| file_operations | json | List of file changes |
| estimated_complexity | enum | Complexity level |
| estimated_files_affected | int | File count |
| risks | json | Potential issues |
| prerequisites | json | Requirements before execution |
| user_feedback | text | User modification requests |
| approved_at | timestamp | When approved |
| approved_by | foreign | Who approved |
| execution_started_at | timestamp | When execution began |
| execution_completed_at | timestamp | When execution finished |
| metadata | json | Stats, timing, model info |

### 2. Enums

#### PlanStatus

```php
enum PlanStatus: string
{
    case Draft = 'draft';           // Being generated/refined
    case PendingReview = 'pending_review';  // Ready for user review
    case Approved = 'approved';     // Approved for execution
    case Rejected = 'rejected';     // Rejected by user
    case Executing = 'executing';   // Currently executing
    case Completed = 'completed';   // Successfully completed
    case Failed = 'failed';         // Execution failed
}
```

**Valid Transitions:**
- Draft → PendingReview, Rejected
- PendingReview → Approved, Rejected, Draft
- Approved → Executing, Rejected
- Rejected → Draft
- Executing → Completed, Failed
- Failed → Draft, Approved

#### FileOperationType

```php
enum FileOperationType: string
{
    case Create = 'create';   // New file
    case Modify = 'modify';   // Change existing file
    case Delete = 'delete';   // Remove file
    case Rename = 'rename';   // Rename file
    case Move = 'move';       // Move to different directory
}
```

### 3. DTOs

#### FileOperation

Represents a single file operation in a plan.

```php
// Create a new file
$op = FileOperation::create(
    path: 'app/Services/PaymentService.php',
    content: '<?php class PaymentService { ... }',
    description: 'New payment processing service'
);

// Modify existing file
$op = FileOperation::modify(
    path: 'app/Models/User.php',
    changes: [
        PlannedChange::add('relationships', 'public function payments() {...}', 'Add payments relationship')
    ],
    description: 'Add payment relationship'
);

// Delete file
$op = FileOperation::delete(
    path: 'app/Services/DeprecatedService.php',
    reason: 'Replaced by NewService'
);
```

#### PlannedChange

Represents a specific change within a file modification.

```php
// Add new code
$change = PlannedChange::add(
    section: 'methods',
    content: 'public function newMethod() { ... }',
    explanation: 'Adding new feature method'
);

// Remove existing code
$change = PlannedChange::remove(
    section: 'deprecated',
    content: 'public function oldMethod() { ... }',
    explanation: 'Removing deprecated method'
);

// Replace code
$change = PlannedChange::replace(
    section: 'constructor',
    before: 'public function __construct() {}',
    after: 'public function __construct(private Service $s) {}',
    explanation: 'Add dependency injection'
);
```

#### RiskAssessment

Evaluates plan risks and requirements.

```php
$assessment = RiskAssessment::calculate(
    risks: [
        ['level' => 'medium', 'description' => 'Database migration required', 'mitigation' => 'Backup first'],
        ['level' => 'low', 'description' => 'Config changes needed', 'mitigation' => 'Clear cache after'],
    ],
    prerequisites: ['Run composer install', 'Configure environment'],
    manualSteps: ['Update .env file']
);

$assessment->overallLevel;        // 'medium'
$assessment->isSafeForAutoExecution();  // false
$assessment->getHighRisks();      // []
```

#### ValidationResult

Result of plan validation.

```php
$result = $planningAgent->validatePlan($plan);

$result->isValid;              // bool
$result->errors;               // ['Error message', ...]
$result->warnings;             // ['Warning message', ...]
$result->missingFiles;         // ['path/to/missing.php', ...]
$result->circularDependencies; // [['from' => 'A', 'to' => 'B', 'cycle' => [...]]]
```

### 4. PlanningAgentService

The core service for plan generation and management.

```php
use App\Services\AI\PlanningAgentService;

// Generate a new plan
$plan = $planningAgent->generatePlan(
    project: $project,
    intent: $intentAnalysis,
    userMessage: 'Add password reset with email verification',
    options: ['conversation_id' => $conversationId]
);

// Refine based on feedback
$refinedPlan = $planningAgent->refinePlan(
    plan: $plan,
    userFeedback: 'Also add rate limiting to prevent abuse'
);

// Validate before execution
$validation = $planningAgent->validatePlan($plan);
if (!$validation->isValid) {
    // Handle errors
}

// Check for missing context
$missingFiles = $planningAgent->identifyMissingContext($plan, $retrievalResult);

// Assess risk
$riskAssessment = $planningAgent->assessRisk($plan);
```

### 5. ExecutionPlan Model

```php
// Create and manage plans
$plan = ExecutionPlan::create([...]);

// Status transitions
$plan->submitForReview();     // Draft → PendingReview
$plan->approve($userId);      // PendingReview → Approved
$plan->reject('Reason');      // → Rejected
$plan->markExecuting();       // Approved → Executing
$plan->markCompleted();       // Executing → Completed
$plan->markFailed('Error');   // Executing → Failed
$plan->revertToDraft();       // → Draft

// Query scopes
ExecutionPlan::pendingReview()->get();
ExecutionPlan::forProject($projectId)->get();
ExecutionPlan::active()->get();

// Accessors
$plan->file_operations_dtos;  // Collection<FileOperation>
$plan->risk_assessment;       // RiskAssessment
$plan->is_modifiable;         // bool
$plan->can_execute;           // bool
$plan->getSummary();          // "2 creates, 1 modify"
```

## Prompt Templates

The Planning Agent uses the existing prompt template system with two templates:

### System Prompt: `resources/prompts/system/code_planner.md`

The system prompt (already exists) defines Claude's role and capabilities:
- Uses `{{PROJECT_INFO}}` and `{{TECH_STACK}}` placeholders
- Defines planning principles, output structure, thinking process
- Follows existing template conventions

### User Prompt: `resources/prompts/user/planning_request.md`

New user prompt template with:
- `{{USER_REQUEST}}` - The original user message
- `{{INTENT_TYPE}}`, `{{INTENT_CONFIDENCE}}`, etc. - Intent analysis data
- `{{CODEBASE_CONTEXT}}` - Retrieved code context
- JSON output schema with examples
- Critical rules for complete code, exact matches, etc.

## Claude Integration

### Extended Thinking

The Planning Agent uses Claude's extended thinking feature for complex planning:

```php
$response = Http::post('https://api.anthropic.com/v1/messages', [
    'model' => 'claude-sonnet-4-5-20250514',
    'max_tokens' => 8192,
    'thinking' => [
        'type' => 'enabled',
        'budget_tokens' => 16000,  // Extended thinking budget
    ],
    'system' => $systemPrompt,
    'messages' => [['role' => 'user', 'content' => $userPrompt]],
]);
```

### Expected Output Format

Claude returns a structured JSON plan:

```json
{
  "title": "Add Password Reset with Email Verification",
  "summary": "Implement password reset flow with email verification...",
  "approach": "Create controller, add routes, create migration...",
  
  "file_operations": [
    {
      "type": "create",
      "path": "app/Http/Controllers/Auth/PasswordResetController.php",
      "priority": 1,
      "description": "Handle password reset requests",
      "template_content": "<?php\n\nnamespace App\\Http\\Controllers\\Auth;...",
      "dependencies": []
    },
    {
      "type": "modify",
      "path": "routes/web.php",
      "priority": 2,
      "description": "Add password reset routes",
      "changes": [
        {
          "section": "auth routes",
          "change_type": "add",
          "after": "Route::post('/password/reset', [...]);",
          "start_line": 45,
          "explanation": "Adding POST route for password reset"
        }
      ],
      "dependencies": ["app/Http/Controllers/Auth/PasswordResetController.php"]
    }
  ],
  
  "risks": [
    {
      "level": "low",
      "description": "Email configuration required",
      "mitigation": "Check .env for mail settings"
    }
  ],
  
  "prerequisites": [
    "Mail driver configured",
    "Users table has email column"
  ],
  
  "testing_notes": "Run: php artisan test --filter=PasswordReset",
  "estimated_time": "15-20 minutes"
}
```

## Configuration

**config/planning.php:**

```php
return [
    'claude' => [
        'model' => env('PLANNING_MODEL', 'claude-sonnet-4-5-20250514'),
        'max_tokens' => env('PLANNING_MAX_TOKENS', 8192),
        'thinking_budget' => env('PLANNING_THINKING_BUDGET', 16000),
    ],
    
    'context' => [
        'max_chunks' => 60,
        'token_budget' => 80000,
    ],
    
    'validation' => [
        'require_content_for_create' => true,
        'require_changes_for_modify' => true,
        'detect_circular_dependencies' => true,
    ],
    
    'risk' => [
        'high_delete_threshold' => 3,
        'high_modify_threshold' => 10,
    ],
];
```

## Usage Flow

### 1. Generate Plan

```php
// User: "Add a user profile page"
$intent = $intentAnalyzer->analyze($project, $message);
$plan = $planningAgent->generatePlan($project, $intent, $message);

// Plan is now in PendingReview status
```

### 2. User Reviews Plan

```php
// Show plan details to user
$plan->title;           // "Add User Profile Page"
$plan->description;     // Detailed explanation
$plan->file_operations; // Array of changes
$plan->risks;           // Potential issues
$plan->getSummary();    // "1 create, 1 modify"
```

### 3. User Provides Feedback (Optional)

```php
if ($userWantsChanges) {
    $plan = $planningAgent->refinePlan($plan, $userFeedback);
}
```

### 4. Approve or Reject

```php
if ($userApproves) {
    $plan->approve($userId);
} else {
    $plan->reject('Does not match requirements');
}
```

### 5. Execute (Phase 5)

```php
if ($plan->can_execute) {
    $plan->markExecuting();
    // Execute file operations...
    $plan->markCompleted();
}
```

## Testing

```bash
# Run all Phase 4 tests
php artisan test --filter=PlanningAgentServiceTest
php artisan test --filter=PlanningDTOsTest

# Run specific test groups
php artisan test --filter=test_generate_plan
php artisan test --filter=test_validate
php artisan test --filter=test_risk_assessment
```

## Files Created

| File | Purpose |
|------|---------|
| `database/migrations/..._create_execution_plans_table.php` | Database schema |
| `app/Enums/PlanStatus.php` | Plan lifecycle states |
| `app/Enums/FileOperationType.php` | File operation types |
| `app/Models/ExecutionPlan.php` | Eloquent model |
| `app/DTOs/FileOperation.php` | File operation DTO |
| `app/DTOs/PlannedChange.php` | Code change DTO |
| `app/DTOs/RiskAssessment.php` | Risk evaluation DTO |
| `app/DTOs/ValidationResult.php` | Validation result DTO |
| `app/Services/AI/PlanningAgentService.php` | Core planning service |
| `app/Providers/PlanningAgentServiceProvider.php` | Service registration |
| `config/planning.php` | Configuration |
| `resources/prompts/user/planning_request.md` | User prompt template (NEW) |
| `database/factories/ExecutionPlanFactory.php` | Test factory |
| `tests/Feature/PlanningAgentServiceTest.php` | Feature tests |
| `tests/Unit/PlanningDTOsTest.php` | DTO unit tests |

**Note**: The system prompt `resources/prompts/system/code_planner.md` already exists and is NOT modified.

## Next Steps: Phase 5

Phase 5 will implement the **Execution Agent** that:
- Takes approved plans and executes file operations
- Creates backups before modifications
- Handles rollback on failures
- Provides real-time execution progress
- Validates changes after execution
