<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Models\AgentConversation;
use App\Models\FileExecution;
use App\Models\Project;
use App\Services\AI\OrchestratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ConversationController extends Controller
{
    public function __construct(
        private readonly OrchestratorService $orchestrator
    ) {}

    /**
     * List all conversations for a project.
     */
    public function index(Project $project): JsonResponse
    {
        Gate::authorize('view', $project);

        $conversations = AgentConversation::forProject($project->id)
            ->forUser(auth()->id())
            ->with(['currentIntent', 'currentPlan'])
            ->latest()
            ->paginate(20);

        return response()->json([
            'data' => $conversations->map(fn($c) => $c->toStateArray()),
            'meta' => [
                'current_page' => $conversations->currentPage(),
                'last_page' => $conversations->lastPage(),
                'per_page' => $conversations->perPage(),
                'total' => $conversations->total(),
            ],
        ]);
    }

    /**
     * Start a new conversation.
     */
    public function store(Project $project, Request $request): JsonResponse
    {
        Gate::authorize('update', $project);

        $conversation = $this->orchestrator->startConversation(
            $project,
            $request->user()
        );

        return response()->json([
            'data' => $conversation->toStateArray(),
            'message' => 'Conversation started',
        ], 201);
    }

    /**
     * Get conversation details and state.
     */
    public function show(AgentConversation $conversation): JsonResponse
    {
        Gate::authorize('view', $conversation->project);

        $state = $this->orchestrator->getState($conversation);

        $messages = $conversation->messages()
            ->latest()
            ->take(50)
            ->get()
            ->reverse()
            ->values()
            ->map(fn($m) => $m->toApiFormat());

        return response()->json([
            'state' => $state->toArray(),
            'messages' => $messages,
        ]);
    }

    /**
     * Send a message to the conversation (streaming response).
     */
    public function sendMessage(AgentConversation $conversation, Request $request): StreamedResponse
    {
        Gate::authorize('update', $conversation->project);

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:10000'],
        ]);

        return response()->stream(function () use ($conversation, $validated) {
            foreach ($this->orchestrator->processMessage($conversation, $validated['message']) as $event) {
                echo "event: {$event->type}\n";
                echo "data: " . json_encode($event->toArray()) . "\n\n";
                ob_flush();
                flush();
            }

            echo "event: done\n";
            echo "data: {}\n\n";
            ob_flush();
            flush();
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
            'Connection' => 'keep-alive',
        ]);
    }

    /**
     * Handle plan approval or rejection.
     */
    public function handleApproval(AgentConversation $conversation, Request $request): StreamedResponse
    {
        Gate::authorize('update', $conversation->project);

        $validated = $request->validate([
            'approved' => ['required', 'boolean'],
            'feedback' => ['nullable', 'string', 'max:5000'],
        ]);

        return response()->stream(function () use ($conversation, $validated) {
            foreach ($this->orchestrator->handleApproval(
                $conversation,
                $validated['approved'],
                $validated['feedback'] ?? null
            ) as $event) {
                echo "event: {$event->type}\n";
                echo "data: " . json_encode($event->toArray()) . "\n\n";
                ob_flush();
                flush();
            }

            echo "event: done\n";
            echo "data: {}\n\n";
            ob_flush();
            flush();
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Handle file-level approval during execution.
     */
    public function handleFileApproval(
        AgentConversation $conversation,
        FileExecution $execution,
        Request $request
    ): StreamedResponse {
        Gate::authorize('update', $conversation->project);

        $validated = $request->validate([
            'approved' => ['required', 'boolean'],
        ]);

        return response()->stream(function () use ($conversation, $execution, $validated) {
            foreach ($this->orchestrator->handleFileApproval(
                $conversation,
                $execution->id,
                $validated['approved']
            ) as $event) {
                echo "event: {$event->type}\n";
                echo "data: " . json_encode($event->toArray()) . "\n\n";
                ob_flush();
                flush();
            }

            echo "event: done\n";
            echo "data: {}\n\n";
            ob_flush();
            flush();
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Cancel the current operation.
     */
    public function cancel(AgentConversation $conversation): JsonResponse
    {
        Gate::authorize('update', $conversation->project);

        foreach ($this->orchestrator->cancel($conversation) as $event) {
            broadcast($event);
        }

        return response()->json([
            'message' => 'Operation cancelled',
            'state' => $this->orchestrator->getState($conversation)->toArray(),
        ]);
    }

    /**
     * Resume a paused conversation.
     */
    public function resume(AgentConversation $conversation): JsonResponse
    {
        Gate::authorize('update', $conversation->project);

        $conversation->resume();

        return response()->json([
            'message' => 'Conversation resumed',
            'state' => $this->orchestrator->getState($conversation)->toArray(),
        ]);
    }

    /**
     * Get conversation messages with pagination.
     */
    public function messages(AgentConversation $conversation, Request $request): JsonResponse
    {
        Gate::authorize('view', $conversation->project);

        $messages = $conversation->messages()
            ->when($request->has('before'), function ($q) use ($request) {
                $q->where('created_at', '<', $request->input('before'));
            })
            ->latest()
            ->take($request->input('limit', 50))
            ->get()
            ->reverse()
            ->values()
            ->map(fn($m) => $m->toApiFormat());

        return response()->json([
            'data' => $messages,
            'has_more' => $messages->count() === (int) $request->input('limit', 50),
        ]);
    }

    /**
     * Delete a conversation.
     */
    public function destroy(AgentConversation $conversation): JsonResponse
    {
        Gate::authorize('delete', $conversation->project);

        $conversation->delete();

        return response()->json([
            'message' => 'Conversation deleted',
        ]);
    }
}
