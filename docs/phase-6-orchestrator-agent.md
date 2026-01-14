# Phase 6: Orchestrator Super Agent

## Overview

Phase 6 introduces the Orchestrator Super Agent - the master coordinator that manages the entire AI-assisted development workflow. It routes requests to appropriate subagents, manages conversation state, handles workflow transitions, and provides a unified interface for the frontend.

## Architecture

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                           User Message                                       │
└─────────────────────────────────────────────────────────────────────────────┘
                                    │
                                    ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                        OrchestratorService                                   │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐        │
│  │   Intake    │→ │  Discovery  │→ │  Planning   │→ │  Approval   │        │
│  │   Phase     │  │   Phase     │  │   Phase     │  │   Phase     │        │
│  └─────────────┘  └─────────────┘  └─────────────┘  └─────────────┘        │
│         │                │                │                │                 │
│         ▼                ▼                ▼                ▼                 │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐        │
│  │   Intent    │  │  Context    │  │  Planning   │  │  Execution  │        │
│  │  Analyzer   │  │ Retrieval   │  │   Agent     │  │   Agent     │        │
│  │  (Phase 1)  │  │  (Phase 3)  │  │  (Phase 4)  │  │  (Phase 5)  │        │
│  └─────────────┘  └─────────────┘  └─────────────┘  └─────────────┘        │
└─────────────────────────────────────────────────────────────────────────────┘
                                    │
                                    ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                      OrchestratorEvent Stream                                │
│            (Real-time updates via WebSocket/SSE)                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

## Workflow State Machine

```
                    ┌──────────────┐
                    │    INTAKE    │◄─────────────────┐
                    └──────┬───────┘                  │
                           │                          │
              ┌────────────┴────────────┐             │
              ▼                         ▼             │
    ┌──────────────────┐       ┌──────────────┐      │
    │  CLARIFICATION   │──────►│   DISCOVERY  │      │
    └──────────────────┘       └──────┬───────┘      │
                                      │              │
                                      ▼              │
                               ┌──────────────┐      │
                               │   PLANNING   │      │
                               └──────┬───────┘      │
                                      │              │
                                      ▼              │
                     ┌─────────────────────────┐     │
                     │        APPROVAL         │─────┘
                     └────────────┬────────────┘ (reject + refine)
                                  │
                                  ▼ (approve)
                          ┌──────────────┐
                          │  EXECUTING   │
                          └──────┬───────┘
                                 │
                    ┌────────────┴────────────┐
                    ▼                         ▼
            ┌──────────────┐          ┌──────────────┐
            │  COMPLETED   │          │    FAILED    │
            └──────────────┘          └──────────────┘
```

## Components

### 1. Database Schema

#### Table: `agent_conversations`

| Column | Type | Description |
|--------|------|-------------|
| id | uuid | Primary key |
| project_id | uuid | Foreign key to projects |
| user_id | int | Foreign key to users |
| title | string | Auto-generated from first message |
| status | enum | active, paused, completed, failed |
| current_phase | enum | Current workflow phase |
| current_intent_id | uuid | Current intent analysis |
| current_plan_id | uuid | Current execution plan |
| context_summary | json | Summary of retrieved context |
| metadata | json | Additional data |
| timestamps | | |

#### Table: `agent_messages`

| Column | Type | Description |
|--------|------|-------------|
| id | uuid | Primary key |
| conversation_id | uuid | Foreign key to conversations |
| role | enum | user, assistant, system |
| content | text | Message content |
| message_type | enum | Type of message |
| attachments | json | File refs, diffs, etc. |
| metadata | json | Tokens, timing, etc. |
| created_at | timestamp | |

### 2. Enums

#### ConversationPhase

```php
enum ConversationPhase: string
{
    case Intake = 'intake';           // Receiving user request
    case Clarification = 'clarification'; // Need more details
    case Discovery = 'discovery';     // Analyzing codebase
    case Planning = 'planning';       // Creating plan
    case Approval = 'approval';       // Awaiting user approval
    case Executing = 'executing';     // Applying changes
    case Completed = 'completed';     // Successfully done
    case Failed = 'failed';           // Error occurred
}
```

**Methods:**
- `label()` - Human-readable name
- `description()` - Detailed explanation
- `icon()` - Icon name for UI
- `color()` - UI color
- `isTerminal()` - Is this a final state?
- `isActive()` - Is processing active?
- `requiresUserAction()` - Does user need to act?
- `canTransitionTo(phase)` - Check valid transitions
- `nextPhases()` - Get possible next phases

#### AgentMessageType

```php
enum AgentMessageType: string
{
    case Text = 'text';
    case PlanPreview = 'plan_preview';
    case FileDiff = 'file_diff';
    case ApprovalRequest = 'approval_request';
    case ExecutionUpdate = 'execution_update';
    case Error = 'error';
    case Clarification = 'clarification';
    case CodeContext = 'code_context';
    case SystemNotice = 'system_notice';
}
```

### 3. Models

#### AgentConversation

```php
// Create conversation
$conversation = AgentConversation::create([...]);

// Add message
$message = $conversation->addMessage(
    role: 'user',
    content: 'Add password reset',
    type: AgentMessageType::Text
);

// Phase transitions
$conversation->transitionTo(ConversationPhase::Discovery);
$conversation->forcePhase(ConversationPhase::Failed);
$conversation->markCompleted();
$conversation->markFailed('Error message');

// State management
$conversation->pause();
$conversation->resume();
$conversation->setCurrentIntent($intent);
$conversation->setCurrentPlan($plan);

// Query history
$messages = $conversation->getContextMessages(20);
$lastMessage = $conversation->getLastUserMessage();

// Generate title
$title = $conversation->generateTitle();

// Query scopes
AgentConversation::active()->get();
AgentConversation::forProject($projectId)->get();
AgentConversation::inPhase(ConversationPhase::Approval)->get();
AgentConversation::requiringAction()->get();
```

#### AgentMessage

```php
// Create via conversation
$message = $conversation->addMessage('user', 'Hello', AgentMessageType::Text);

// Accessors
$message->is_user;           // bool
$message->is_assistant;      // bool
$message->requires_action;   // bool
$message->formatted_content; // string with formatting

// Attachments
$message->hasAttachment('plan_id');
$value = $message->getAttachment('diff');

// Query scopes
$conversation->messages()->fromUser()->get();
$conversation->messages()->ofType(AgentMessageType::PlanPreview)->get();
$conversation->messages()->requiringAction()->get();

// API format
$apiData = $message->toApiFormat();
```

### 4. OrchestratorService

The main entry point for all AI interactions.

```php
use App\Services\AI\OrchestratorService;

$orchestrator = app(OrchestratorService::class);

// Start new conversation
$conversation = $orchestrator->startConversation($project, $user);

// Process message (streaming)
foreach ($orchestrator->processMessage($conversation, $userMessage) as $event) {
    broadcast($event); // Real-time to frontend
}

// Handle plan approval
foreach ($orchestrator->handleApproval($conversation, true, $feedback) as $event) {
    broadcast($event);
}

// Handle file approval during execution
foreach ($orchestrator->handleFileApproval($conversation, $executionId, true) as $event) {
    broadcast($event);
}

// Cancel operation
foreach ($orchestrator->cancel($conversation) as $event) {
    broadcast($event);
}

// Get current state
$state = $orchestrator->getState($conversation);
```

### 5. ConversationState DTO

```php
$state = ConversationState::fromConversation($conversation);

$state->conversationId;      // string
$state->phase;               // ConversationPhase
$state->status;              // string
$state->title;               // ?string
$state->currentIntent;       // ?IntentAnalysis
$state->currentPlan;         // ?ExecutionPlan
$state->pendingExecutions;   // ?Collection<FileExecution>
$state->availableActions;    // array<string>
$state->metadata;            // array

// Helper methods
$state->isAwaitingApproval(); // bool
$state->isExecuting();        // bool
$state->isTerminal();         // bool
$state->hasPlan();            // bool
$state->hasIntent();          // bool
$state->canSendMessage();     // bool
$state->canApprove();         // bool

$array = $state->toArray();   // Full serialization
```

### 6. OrchestratorEvent

Broadcastable events for real-time updates.

```php
// Event types
OrchestratorEvent::TYPE_MESSAGE_RECEIVED
OrchestratorEvent::TYPE_PHASE_CHANGED
OrchestratorEvent::TYPE_ANALYZING_INTENT
OrchestratorEvent::TYPE_INTENT_ANALYZED
OrchestratorEvent::TYPE_CLARIFICATION_NEEDED
OrchestratorEvent::TYPE_RETRIEVING_CONTEXT
OrchestratorEvent::TYPE_CONTEXT_RETRIEVED
OrchestratorEvent::TYPE_GENERATING_PLAN
OrchestratorEvent::TYPE_PLAN_GENERATED
OrchestratorEvent::TYPE_AWAITING_APPROVAL
OrchestratorEvent::TYPE_PLAN_APPROVED
OrchestratorEvent::TYPE_PLAN_REJECTED
OrchestratorEvent::TYPE_EXECUTION_STARTED
OrchestratorEvent::TYPE_EXECUTION_PROGRESS
OrchestratorEvent::TYPE_FILE_APPROVAL_NEEDED
OrchestratorEvent::TYPE_EXECUTION_COMPLETED
OrchestratorEvent::TYPE_RESPONSE_CHUNK
OrchestratorEvent::TYPE_RESPONSE_COMPLETE
OrchestratorEvent::TYPE_ERROR
OrchestratorEvent::TYPE_CANCELLED

// Factory methods
OrchestratorEvent::messageReceived($convId, $message);
OrchestratorEvent::phaseChanged($convId, $from, $to);
OrchestratorEvent::analyzingIntent($convId);
OrchestratorEvent::planGenerated($convId, $planId, $title, $filesAffected);
OrchestratorEvent::executionProgress($convId, $completed, $total, $currentFile);
OrchestratorEvent::error($convId, $error, $phase);
```

## API Endpoints

### Routes

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/projects/{project}/ai/conversations` | List conversations |
| POST | `/projects/{project}/ai/conversations` | Start new conversation |
| GET | `/conversations/{conversation}` | Get conversation state |
| POST | `/conversations/{conversation}/messages` | Send message (streaming) |
| GET | `/conversations/{conversation}/messages` | Get paginated messages |
| POST | `/conversations/{conversation}/approve` | Approve/reject plan |
| POST | `/conversations/{conversation}/files/{execution}/approve` | Approve/skip file |
| POST | `/conversations/{conversation}/cancel` | Cancel operation |
| POST | `/conversations/{conversation}/resume` | Resume paused conversation |
| DELETE | `/conversations/{conversation}` | Delete conversation |

### Response Formats

#### Conversation State

```json
{
  "state": {
    "conversation_id": "uuid",
    "phase": {
      "value": "approval",
      "label": "Awaiting Approval",
      "description": "Review the plan before execution",
      "icon": "check-circle",
      "color": "amber",
      "is_terminal": false,
      "requires_action": true
    },
    "status": "active",
    "title": "Add password reset feature",
    "has_intent": true,
    "has_plan": true,
    "intent": { ... },
    "plan": {
      "id": "uuid",
      "title": "Implement Password Reset",
      "status": "pending_review",
      "files_affected": 5,
      "complexity": "medium"
    },
    "available_actions": ["approve_plan", "reject_plan", "request_changes", "cancel"]
  },
  "messages": [...]
}
```

#### SSE Event Stream

```
event: message_received
data: {"type":"message_received","data":{"message":"Add auth"},"conversation_id":"uuid"}

event: analyzing_intent
data: {"type":"analyzing_intent","data":{"status":"Analyzing..."},"conversation_id":"uuid"}

event: plan_generated
data: {"type":"plan_generated","data":{"plan_id":"uuid","title":"...","files_affected":3}}

event: done
data: {}
```

## Configuration

**config/orchestrator.php:**

```php
return [
    'claude' => [
        'model' => env('ORCHESTRATOR_MODEL', 'claude-sonnet-4-5-20250514'),
        'max_tokens' => 4096,
        'temperature' => 0.3,
        'timeout' => 120,
    ],
    'context' => [
        'max_chunks' => 60,
        'token_budget' => 80000,
    ],
    'execution' => [
        'auto_approve' => false,
        'stop_on_error' => true,
    ],
    'conversation' => [
        'max_history_messages' => 20,
        'auto_generate_title' => true,
    ],
];
```

## Usage Flow

### 1. Start Conversation

```php
$conversation = $orchestrator->startConversation($project, $user);
```

### 2. Send Message

```javascript
// Frontend: Connect to SSE endpoint
const eventSource = new EventSource(`/api/conversations/${id}/messages`, {
  method: 'POST',
  body: JSON.stringify({ message: userInput })
});

eventSource.onmessage = (event) => {
  const data = JSON.parse(event.data);
  handleEvent(data.type, data.data);
};
```

### 3. Handle Events

```javascript
function handleEvent(type, data) {
  switch (type) {
    case 'analyzing_intent':
      showLoading('Analyzing your request...');
      break;
    case 'plan_generated':
      showPlanPreview(data.plan_id);
      break;
    case 'awaiting_approval':
      showApprovalUI();
      break;
    case 'execution_progress':
      updateProgress(data.completed, data.total);
      break;
    case 'error':
      showError(data.error);
      break;
  }
}
```

### 4. Approve Plan

```javascript
await fetch(`/api/conversations/${id}/approve`, {
  method: 'POST',
  body: JSON.stringify({ approved: true })
});
```

## Integration with Previous Phases

```php
// Full pipeline orchestrated automatically:

// User: "Add password reset via email"
$conversation = $orchestrator->startConversation($project, $user);

foreach ($orchestrator->processMessage($conversation, $message) as $event) {
    // Phase 1: Intent Analysis
    // → OrchestratorEvent::analyzingIntent
    // → OrchestratorEvent::intentAnalyzed
    
    // Phase 3: Context Retrieval
    // → OrchestratorEvent::retrievingContext
    // → OrchestratorEvent::contextRetrieved
    
    // Phase 4: Planning
    // → OrchestratorEvent::generatingPlan
    // → OrchestratorEvent::planGenerated
    // → OrchestratorEvent::awaitingApproval
    
    broadcast($event);
}

// User approves
foreach ($orchestrator->handleApproval($conversation, true) as $event) {
    // Phase 5: Execution
    // → OrchestratorEvent::executionStarted
    // → OrchestratorEvent::executionProgress (repeated)
    // → OrchestratorEvent::executionCompleted
    
    broadcast($event);
}
```

## Testing

```bash
# Run all Phase 6 tests
php artisan test --filter=OrchestratorServiceTest

# Run specific test groups
php artisan test --filter=test_conversation
php artisan test --filter=test_phase
php artisan test --filter=test_event
```

## Files Created

| File | Purpose |
|------|---------|
| `database/migrations/..._create_agent_conversations_table.php` | Conversations schema |
| `database/migrations/..._create_agent_messages_table.php` | Messages schema |
| `app/Enums/ConversationPhase.php` | Workflow phases |
| `app/Enums/AgentMessageType.php` | Message types |
| `app/Models/AgentConversation.php` | Conversation model |
| `app/Models/AgentMessage.php` | Message model |
| `app/DTOs/ConversationState.php` | State DTO |
| `app/Events/OrchestratorEvent.php` | Broadcastable events |
| `app/Services/AI/OrchestratorService.php` | Main orchestrator |
| `app/Providers/OrchestratorServiceProvider.php` | Service provider |
| `app/Http/Controllers/AI/ConversationController.php` | API controller |
| `config/orchestrator.php` | Configuration |
| `routes/ai.php` | API routes |
| `routes/channels.php` | Broadcast channels |
| `resources/prompts/system/orchestrator.md` | System prompt |
| `database/factories/AgentConversationFactory.php` | Test factory |
| `database/factories/AgentMessageFactory.php` | Test factory |
| `tests/Feature/OrchestratorServiceTest.php` | Test suite |

## Setup

### 1. Run Migrations

```bash
php artisan migrate
```

### 2. Register Service Provider

Add to `bootstrap/providers.php`:

```php
return [
    // ...
    App\Providers\OrchestratorServiceProvider::class,
];
```

### 3. Add Routes

In `routes/api.php`:

```php
Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('projects/{project}/ai')
        ->group(base_path('routes/ai.php'));
});
```

### 4. Add Channel Authorization

In `routes/channels.php`:

```php
Broadcast::channel('conversation.{conversationId}', function ($user, $id) {
    $conversation = AgentConversation::find($id);
    return $conversation && $user->id === $conversation->user_id;
});
```

### 5. Configure Environment

```env
ORCHESTRATOR_MODEL=claude-sonnet-4-5-20250514
ORCHESTRATOR_MAX_TOKENS=4096
ORCHESTRATOR_AUTO_APPROVE=false
ORCHESTRATOR_LOGGING=true
```

## Changelog

### v1.0.0 (Initial Release)

- AgentConversation model with phase state machine
- AgentMessage model with type-based formatting
- ConversationPhase enum with transition validation
- AgentMessageType enum with UI metadata
- OrchestratorService as main coordinator
- ConversationState DTO for frontend state
- OrchestratorEvent for real-time streaming
- ConversationController with SSE endpoints
- Full integration with Phases 1-5
- Comprehensive test suite (30+ tests)
