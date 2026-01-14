<?php

use App\Models\AgentConversation;
use App\Models\ExecutionPlan;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Add these channel authorizations to your existing routes/channels.php
|
*/

// AI Conversation channel - users can only listen to their own conversations
Broadcast::channel('conversation.{conversationId}', function ($user, string $conversationId) {
    $conversation = AgentConversation::find($conversationId);

    if (!$conversation) {
        return false;
    }

    return $user->id === $conversation->user_id;
});

// Execution channel - users can only listen to executions for their projects
Broadcast::channel('execution.{planId}', function ($user, string $planId) {
    $plan = ExecutionPlan::with('project')->find($planId);

    if (!$plan) {
        return false;
    }

    return $user->id === $plan->project->user_id;
});
