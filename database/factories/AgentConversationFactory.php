<?php

namespace Database\Factories;

use App\Enums\ConversationPhase;
use App\Models\AgentConversation;
use App\Models\ExecutionPlan;
use App\Models\IntentAnalysis;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgentConversation>
 */
class AgentConversationFactory extends Factory
{
    protected $model = AgentConversation::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'user_id' => User::factory(),
            'title' => fake()->sentence(4),
            'status' => 'active',
            'current_phase' => ConversationPhase::Intake,
            'current_intent_id' => null,
            'current_plan_id' => null,
            'context_summary' => null,
            'metadata' => [
                'started_at' => now()->toIso8601String(),
            ],
        ];
    }

    public function forProject(Project $project): static
    {
        return $this->state(fn() => [
            'project_id' => $project->id,
        ]);
    }

    public function forUser(User $user): static
    {
        return $this->state(fn() => [
            'user_id' => $user->id,
        ]);
    }

    public function inPhase(ConversationPhase $phase): static
    {
        return $this->state(fn() => [
            'current_phase' => $phase,
            'status' => $phase->isTerminal()
                ? ($phase === ConversationPhase::Completed ? 'completed' : 'failed')
                : 'active',
        ]);
    }

    public function intake(): static
    {
        return $this->inPhase(ConversationPhase::Intake);
    }

    public function clarification(): static
    {
        return $this->inPhase(ConversationPhase::Clarification);
    }

    public function discovery(): static
    {
        return $this->inPhase(ConversationPhase::Discovery);
    }

    public function planning(): static
    {
        return $this->inPhase(ConversationPhase::Planning);
    }

    public function approval(): static
    {
        return $this->inPhase(ConversationPhase::Approval);
    }

    public function executing(): static
    {
        return $this->inPhase(ConversationPhase::Executing);
    }

    public function completed(): static
    {
        return $this->inPhase(ConversationPhase::Completed);
    }

    public function failed(): static
    {
        return $this->inPhase(ConversationPhase::Failed);
    }

    public function paused(): static
    {
        return $this->state(fn() => [
            'status' => 'paused',
        ]);
    }

    public function withIntent(IntentAnalysis $intent = null): static
    {
        return $this->state(function () use ($intent) {
            $intentModel = $intent ?? IntentAnalysis::factory()->create();
            return [
                'current_intent_id' => $intentModel->id,
            ];
        });
    }

    public function withPlan(ExecutionPlan $plan = null): static
    {
        return $this->state(function (array $attributes) use ($plan) {
            $planModel = $plan ?? ExecutionPlan::factory()->create([
                'project_id' => $attributes['project_id'],
            ]);
            return [
                'current_plan_id' => $planModel->id,
            ];
        });
    }

    public function withContextSummary(array $summary = null): static
    {
        return $this->state(fn() => [
            'context_summary' => $summary ?? [
                    'files_found' => fake()->numberBetween(5, 50),
                    'chunks_found' => fake()->numberBetween(10, 100),
                    'entry_points' => ['app/Http/Controllers/ExampleController.php'],
                ],
        ]);
    }
}
