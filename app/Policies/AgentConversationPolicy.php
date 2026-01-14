<?php

namespace App\Policies;

use App\Models\AgentConversation;
use App\Models\Project;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class AgentConversationPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any conversations for a project.
     */
    public function viewAny(User $user, Project $project): bool
    {
        return $user->id === $project->user_id;
    }

    /**
     * Determine whether the user can view the conversation.
     */
    public function view(User $user, AgentConversation $conversation): bool
    {
        return $user->id === $conversation->user_id;
    }

    /**
     * Determine whether the user can create a conversation for a project.
     */
    public function create(User $user, Project $project): bool
    {
        return $user->id === $project->user_id && $project->isReady();
    }

    /**
     * Determine whether the user can send a message to the conversation.
     */
    public function sendMessage(User $user, AgentConversation $conversation): bool
    {
        return $user->id === $conversation->user_id && $conversation->status === 'active';
    }

    /**
     * Determine whether the user can approve/reject plans in the conversation.
     */
    public function handleApproval(User $user, AgentConversation $conversation): bool
    {
        return $user->id === $conversation->user_id &&
               $conversation->status === 'active' &&
               $conversation->current_plan_id !== null;
    }

    /**
     * Determine whether the user can approve file executions.
     */
    public function handleFileApproval(User $user, AgentConversation $conversation): bool
    {
        return $user->id === $conversation->user_id && $conversation->status === 'active';
    }

    /**
     * Determine whether the user can cancel the conversation.
     */
    public function cancel(User $user, AgentConversation $conversation): bool
    {
        return $user->id === $conversation->user_id &&
               !$conversation->current_phase->isTerminal();
    }

    /**
     * Determine whether the user can resume the conversation.
     */
    public function resume(User $user, AgentConversation $conversation): bool
    {
        return $user->id === $conversation->user_id && $conversation->status === 'paused';
    }

    /**
     * Determine whether the user can delete the conversation.
     */
    public function delete(User $user, AgentConversation $conversation): bool
    {
        return $user->id === $conversation->user_id;
    }

    /**
     * Determine whether the user can view messages in the conversation.
     */
    public function viewMessages(User $user, AgentConversation $conversation): bool
    {
        return $user->id === $conversation->user_id;
    }
}
