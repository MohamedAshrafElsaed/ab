<?php

namespace App\Providers;

use App\Models\AgentConversation;
use App\Models\ExecutionPlan;
use App\Models\Project;
use App\Policies\AgentConversationPolicy;
use App\Policies\ExecutionPlanPolicy;
use App\Policies\ProjectPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerPolicies();
    }

    /**
     * Register authorization policies.
     */
    protected function registerPolicies(): void
    {
        Gate::policy(Project::class, ProjectPolicy::class);
        Gate::policy(AgentConversation::class, AgentConversationPolicy::class);
        Gate::policy(ExecutionPlan::class, ExecutionPlanPolicy::class);
    }
}
