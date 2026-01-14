<?php

namespace App\Policies;

use App\Models\ExecutionPlan;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ExecutionPlanPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view the execution plan.
     */
    public function view(User $user, ExecutionPlan $plan): bool
    {
        return $user->id === $plan->project->user_id;
    }

    /**
     * Determine whether the user can approve the execution plan.
     */
    public function approve(User $user, ExecutionPlan $plan): bool
    {
        return $user->id === $plan->project->user_id &&
               $plan->status->isModifiable();
    }

    /**
     * Determine whether the user can reject the execution plan.
     */
    public function reject(User $user, ExecutionPlan $plan): bool
    {
        return $user->id === $plan->project->user_id &&
               $plan->status->isModifiable();
    }

    /**
     * Determine whether the user can execute the plan.
     */
    public function execute(User $user, ExecutionPlan $plan): bool
    {
        return $user->id === $plan->project->user_id &&
               $plan->status->canExecute();
    }

    /**
     * Determine whether the user can rollback the execution.
     */
    public function rollback(User $user, ExecutionPlan $plan): bool
    {
        return $user->id === $plan->project->user_id &&
               in_array($plan->status->value, ['executing', 'completed', 'failed']);
    }

    /**
     * Determine whether the user can modify the execution plan.
     */
    public function update(User $user, ExecutionPlan $plan): bool
    {
        return $user->id === $plan->project->user_id &&
               $plan->status->isModifiable();
    }

    /**
     * Determine whether the user can delete the execution plan.
     */
    public function delete(User $user, ExecutionPlan $plan): bool
    {
        return $user->id === $plan->project->user_id &&
               $plan->status->isModifiable();
    }
}
