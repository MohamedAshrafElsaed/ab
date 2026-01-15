<?php

namespace Tests\Feature;

use App\DTOs\FileOperation;
use App\DTOs\PlannedChange;
use App\DTOs\RetrievalResult;
use App\DTOs\RiskAssessment;
use App\DTOs\ValidationResult;
use App\Enums\ComplexityLevel;
use App\Enums\FileOperationType;
use App\Enums\IntentType;
use App\Enums\PlanStatus;
use App\Models\ExecutionPlan;
use App\Models\IntentAnalysis;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\User;
use App\Services\AI\PlanningAgentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PlanningAgentServiceTest extends TestCase
{
    use RefreshDatabase;

    private PlanningAgentService $service;
    private Project $project;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(PlanningAgentService::class);
        $this->user = User::factory()->create();
        $this->project = Project::factory()->create(['user_id' => $this->user->id]);

        // Seed some project files
        $this->seedProjectFiles();
    }

    private function seedProjectFiles(): void
    {
        ProjectFile::factory()->create([
            'project_id' => $this->project->id,
            'path' => 'app/Http/Controllers/UserController.php',
            'extension' => 'php',
            'language' => 'php',
        ]);

        ProjectFile::factory()->create([
            'project_id' => $this->project->id,
            'path' => 'routes/web.php',
            'extension' => 'php',
            'language' => 'php',
        ]);

        ProjectFile::factory()->create([
            'project_id' => $this->project->id,
            'path' => 'app/Models/User.php',
            'extension' => 'php',
            'language' => 'php',
        ]);
    }

    private function createIntent(
        IntentType $type = IntentType::FeatureRequest,
        array $entities = [],
        string $domain = 'general'
    ): IntentAnalysis {
        return IntentAnalysis::factory()->create([
            'project_id' => $this->project->id,
            'intent_type' => $type,
            'extracted_entities' => array_merge([
                'files' => [],
                'components' => [],
                'features' => [],
                'symbols' => [],
            ], $entities),
            'domain_classification' => ['primary' => $domain, 'secondary' => []],
            'confidence_score' => 0.9,
            'complexity_estimate' => ComplexityLevel::Medium,
        ]);
    }

    private function mockClaudeResponse(array $planData): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [
                    [
                        'type' => 'thinking',
                        'thinking' => 'Analyzing the request...',
                    ],
                    [
                        'type' => 'text',
                        'text' => json_encode($planData),
                    ],
                ],
                'usage' => ['input_tokens' => 1000, 'output_tokens' => 500],
            ], 200),
        ]);
    }

    // =========================================================================
    // Plan Generation Tests
    // =========================================================================

    public function test_generate_plan_for_simple_feature(): void
    {
        $this->mockClaudeResponse([
            'title' => 'Add User Profile Page',
            'summary' => 'Create a new profile page showing user details',
            'approach' => 'Create a new controller and view',
            'file_operations' => [
                [
                    'type' => 'create',
                    'path' => 'app/Http/Controllers/ProfileController.php',
                    'priority' => 1,
                    'description' => 'New profile controller',
                    'template_content' => "<?php\n\nnamespace App\\Http\\Controllers;\n\nclass ProfileController extends Controller\n{\n    public function show()\n    {\n        return inertia('Profile/Show');\n    }\n}",
                    'dependencies' => [],
                ],
                [
                    'type' => 'modify',
                    'path' => 'routes/web.php',
                    'priority' => 2,
                    'description' => 'Add profile route',
                    'changes' => [
                        [
                            'section' => 'routes',
                            'change_type' => 'add',
                            'before' => null,
                            'after' => "Route::get('/profile', [ProfileController::class, 'show']);",
                            'start_line' => 25,
                            'end_line' => 25,
                            'explanation' => 'Adding profile route',
                        ],
                    ],
                    'dependencies' => ['app/Http/Controllers/ProfileController.php'],
                ],
            ],
            'risks' => [],
            'prerequisites' => [],
            'testing_notes' => 'Visit /profile to test',
            'estimated_time' => '10 minutes',
        ]);

        $intent = $this->createIntent(IntentType::FeatureRequest, [], 'users');

        $plan = $this->service->generatePlan(
            $this->project,
            $intent,
            'Add a user profile page'
        );

        $this->assertInstanceOf(ExecutionPlan::class, $plan);
        $this->assertEquals('Add User Profile Page', $plan->title);
        $this->assertEquals(PlanStatus::PendingReview, $plan->status);
        $this->assertEquals(2, count($plan->file_operations));
        $this->assertEquals($this->project->id, $plan->project_id);
        $this->assertEquals($intent->id, $plan->intent_analysis_id);
    }

    public function test_generate_plan_for_complex_feature(): void
    {
        $this->mockClaudeResponse([
            'title' => 'Implement User Authentication',
            'summary' => 'Full authentication system with login, register, password reset',
            'approach' => 'Use Laravel Fortify with custom controllers',
            'file_operations' => array_map(fn($i) => [
                'type' => 'create',
                'path' => "app/Http/Controllers/Auth/Controller{$i}.php",
                'priority' => $i,
                'description' => "Auth controller {$i}",
                'template_content' => "<?php class Controller{$i} {}",
                'dependencies' => [],
            ], range(1, 8)),
            'risks' => [
                ['level' => 'medium', 'description' => 'Session configuration required', 'mitigation' => 'Check config/session.php'],
            ],
            'prerequisites' => ['Configure mail driver', 'Set up database'],
            'testing_notes' => 'Run full auth test suite',
            'estimated_time' => '2-3 hours',
        ]);

        $intent = $this->createIntent(IntentType::FeatureRequest, [], 'auth');
        $intent->update(['complexity_estimate' => ComplexityLevel::Complex]);

        $plan = $this->service->generatePlan(
            $this->project,
            $intent,
            'Implement full user authentication'
        );

        $this->assertEquals(8, $plan->estimated_files_affected);
        $this->assertTrue($plan->estimated_complexity->weight() >= ComplexityLevel::Medium->weight());
        $this->assertNotEmpty($plan->risks);
        $this->assertNotEmpty($plan->prerequisites);
    }

    public function test_generate_plan_handles_api_error(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response(['error' => 'Rate limited'], 429),
        ]);

        $intent = $this->createIntent();

        $plan = $this->service->generatePlan(
            $this->project,
            $intent,
            'Add feature'
        );

        // Should create a draft plan with error info
        $this->assertEquals(PlanStatus::Draft, $plan->status);
        $this->assertStringContainsString('Failed', $plan->title);
        $this->assertArrayHasKey('error', $plan->metadata);
    }

    // =========================================================================
    // Plan Refinement Tests
    // =========================================================================

    public function test_refine_plan_with_user_feedback(): void
    {
        // First, create an initial plan
        $plan = ExecutionPlan::factory()
            ->forProject($this->project)
            ->withIntent($this->createIntent())
            ->pendingReview()
            ->create([
                'title' => 'Initial Plan',
                'file_operations' => [[
                    'type' => 'create',
                    'path' => 'app/Services/TestService.php',
                    'priority' => 1,
                    'description' => 'Test service',
                    'template_content' => '<?php class TestService {}',
                    'dependencies' => [],
                ]],
            ]);

        // Mock the refinement response
        $this->mockClaudeResponse([
            'title' => 'Refined Plan',
            'summary' => 'Updated based on feedback',
            'approach' => 'Added error handling as requested',
            'file_operations' => [
                [
                    'type' => 'create',
                    'path' => 'app/Services/TestService.php',
                    'priority' => 1,
                    'description' => 'Test service with error handling',
                    'template_content' => "<?php\n\nclass TestService {\n    public function run(): void {\n        try {\n            // implementation\n        } catch (\\Exception \$e) {\n            throw \$e;\n        }\n    }\n}",
                    'dependencies' => [],
                ],
            ],
            'risks' => [],
            'prerequisites' => [],
        ]);

        $refinedPlan = $this->service->refinePlan(
            $plan,
            'Please add error handling to the service'
        );

        $this->assertEquals('Refined Plan', $refinedPlan->title);
        $this->assertEquals(PlanStatus::PendingReview, $refinedPlan->status);
        $this->assertStringContainsString('error handling', $refinedPlan->user_feedback);
    }

    // =========================================================================
    // Plan Validation Tests
    // =========================================================================

    public function test_validate_valid_plan(): void
    {
        $plan = ExecutionPlan::factory()
            ->forProject($this->project)
            ->create([
                'file_operations' => [
                    [
                        'type' => 'create',
                        'path' => 'app/Services/NewService.php',
                        'priority' => 1,
                        'description' => 'New service',
                        'template_content' => '<?php class NewService {}',
                        'dependencies' => [],
                    ],
                    [
                        'type' => 'modify',
                        'path' => 'routes/web.php',
                        'priority' => 2,
                        'description' => 'Add route',
                        'changes' => [[
                            'section' => 'routes',
                            'change_type' => 'add',
                            'after' => "Route::get('/new', fn() => 'new');",
                            'explanation' => 'Add route',
                        ]],
                        'dependencies' => [],
                    ],
                ],
            ]);

        $result = $this->service->validatePlan($plan);

        $this->assertInstanceOf(ValidationResult::class, $result);
        $this->assertTrue($result->isValid);
        $this->assertEmpty($result->errors);
    }

    public function test_validate_catches_empty_plan(): void
    {
        $plan = ExecutionPlan::factory()
            ->forProject($this->project)
            ->create(['file_operations' => []]);

        $result = $this->service->validatePlan($plan);

        $this->assertFalse($result->isValid);
        $this->assertNotEmpty($result->errors);
        $this->assertStringContainsString('no file operations', $result->errors[0]);
    }

    public function test_validate_catches_missing_content_for_create(): void
    {
        $plan = ExecutionPlan::factory()
            ->forProject($this->project)
            ->create([
                'file_operations' => [[
                    'type' => 'create',
                    'path' => 'app/Services/Test.php',
                    'priority' => 1,
                    'description' => 'Test',
                    'template_content' => '', // Empty!
                    'dependencies' => [],
                ]],
            ]);

        $result = $this->service->validatePlan($plan);

        $this->assertFalse($result->isValid);
        $this->assertTrue($result->hasErrors());
    }

    public function test_validate_catches_missing_changes_for_modify(): void
    {
        $plan = ExecutionPlan::factory()
            ->forProject($this->project)
            ->create([
                'file_operations' => [[
                    'type' => 'modify',
                    'path' => 'routes/web.php',
                    'priority' => 1,
                    'description' => 'Modify routes',
                    'changes' => [], // Empty!
                    'dependencies' => [],
                ]],
            ]);

        $result = $this->service->validatePlan($plan);

        $this->assertFalse($result->isValid);
    }

    public function test_validate_detects_missing_files(): void
    {
        $plan = ExecutionPlan::factory()
            ->forProject($this->project)
            ->create([
                'file_operations' => [[
                    'type' => 'modify',
                    'path' => 'app/Services/NonExistent.php', // Doesn't exist!
                    'priority' => 1,
                    'description' => 'Modify service',
                    'changes' => [[
                        'section' => 'methods',
                        'change_type' => 'add',
                        'after' => 'public function test() {}',
                        'explanation' => 'Add method',
                    ]],
                    'dependencies' => [],
                ]],
            ]);

        $result = $this->service->validatePlan($plan);

        $this->assertFalse($result->isValid);
        $this->assertTrue($result->hasMissingFiles());
        $this->assertContains('app/Services/NonExistent.php', $result->missingFiles);
    }

    public function test_validate_detects_circular_dependencies(): void
    {
        $plan = ExecutionPlan::factory()
            ->forProject($this->project)
            ->create([
                'file_operations' => [
                    [
                        'type' => 'create',
                        'path' => 'app/A.php',
                        'priority' => 1,
                        'template_content' => '<?php class A {}',
                        'dependencies' => ['app/B.php'], // A depends on B
                    ],
                    [
                        'type' => 'create',
                        'path' => 'app/B.php',
                        'priority' => 2,
                        'template_content' => '<?php class B {}',
                        'dependencies' => ['app/A.php'], // B depends on A - circular!
                    ],
                ],
            ]);

        $result = $this->service->validatePlan($plan);

        $this->assertFalse($result->isValid);
        $this->assertTrue($result->hasCircularDependencies());
    }

    // =========================================================================
    // Missing Context Detection Tests
    // =========================================================================

    public function test_identify_missing_context(): void
    {
        $plan = ExecutionPlan::factory()
            ->forProject($this->project)
            ->create([
                'file_operations' => [
                    [
                        'type' => 'modify',
                        'path' => 'app/Services/PaymentService.php',
                        'priority' => 1,
                        'changes' => [['section' => 'test', 'change_type' => 'add', 'after' => 'code', 'explanation' => 'test']],
                        'dependencies' => ['app/Models/Payment.php'],
                    ],
                ],
            ]);

        $context = RetrievalResult::empty();

        $missing = $this->service->identifyMissingContext($plan, $context);

        $this->assertContains('app/Services/PaymentService.php', $missing);
        $this->assertContains('app/Models/Payment.php', $missing);
    }

    // =========================================================================
    // Risk Assessment Tests
    // =========================================================================

    public function test_assess_risk_low_for_simple_plan(): void
    {
        $plan = ExecutionPlan::factory()
            ->simple()
            ->forProject($this->project)
            ->create();

        $risk = $this->service->assessRisk($plan);

        $this->assertInstanceOf(RiskAssessment::class, $risk);
        $this->assertEquals('low', $risk->overallLevel);
    }

    public function test_assess_risk_high_for_multiple_deletes(): void
    {
        $plan = ExecutionPlan::factory()
            ->forProject($this->project)
            ->create([
                'file_operations' => array_map(fn($i) => [
                    'type' => 'delete',
                    'path' => "app/Services/Delete{$i}.php",
                    'priority' => $i,
                    'description' => "Delete service {$i}",
                    'dependencies' => [],
                ], range(1, 5)),
            ]);

        $risk = $this->service->assessRisk($plan);

        $this->assertEquals('high', $risk->overallLevel);
        $this->assertTrue($risk->hasRisks());
    }

    // =========================================================================
    // Model Tests
    // =========================================================================

    public function test_execution_plan_status_transitions(): void
    {
        $plan = ExecutionPlan::factory()
            ->forProject($this->project)
            ->create(['status' => PlanStatus::Draft]);

        // Draft -> PendingReview
        $plan->submitForReview();
        $this->assertEquals(PlanStatus::PendingReview, $plan->fresh()->status);

        // PendingReview -> Approved
        $plan->approve($this->user->id);
        $plan = $plan->fresh();
        $this->assertEquals(PlanStatus::Approved, $plan->status);
        $this->assertNotNull($plan->approved_at);
        $this->assertEquals($this->user->id, $plan->approved_by);

        // Approved -> Executing
        $plan->markExecuting();
        $this->assertEquals(PlanStatus::Executing, $plan->fresh()->status);

        // Executing -> Completed
        $plan->markCompleted();
        $plan = $plan->fresh();
        $this->assertEquals(PlanStatus::Completed, $plan->status);
        $this->assertNotNull($plan->execution_completed_at);
    }

    public function test_execution_plan_reject_flow(): void
    {
        $plan = ExecutionPlan::factory()
            ->pendingReview()
            ->forProject($this->project)
            ->create();

        $plan->reject('Does not meet requirements');

        $plan = $plan->fresh();
        $this->assertEquals(PlanStatus::Rejected, $plan->status);
        $this->assertEquals('Does not meet requirements', $plan->user_feedback);
    }

    public function test_execution_plan_invalid_transition_throws(): void
    {
        $plan = ExecutionPlan::factory()
            ->completed()
            ->forProject($this->project)
            ->create();

        $this->expectException(\InvalidArgumentException::class);
        $plan->approve();
    }

    public function test_execution_plan_accessors(): void
    {
        $plan = ExecutionPlan::factory()
            ->forProject($this->project)
            ->create([
                'file_operations' => [
                    ['type' => 'create', 'path' => 'a.php', 'template_content' => '<?php', 'priority' => 1, 'dependencies' => []],
                    ['type' => 'modify', 'path' => 'b.php', 'changes' => [], 'priority' => 2, 'dependencies' => []],
                    ['type' => 'delete', 'path' => 'c.php', 'priority' => 3, 'dependencies' => []],
                ],
            ]);

        $this->assertEquals(3, $plan->total_files);
        $this->assertInstanceOf(RiskAssessment::class, $plan->risk_assessment);

        $byType = $plan->getFilesByOperationType();
        $this->assertArrayHasKey('create', $byType);
        $this->assertArrayHasKey('modify', $byType);
        $this->assertArrayHasKey('delete', $byType);
    }

    // =========================================================================
    // DTO Tests
    // =========================================================================

    public function test_file_operation_dto_create(): void
    {
        $op = FileOperation::create(
            path: 'app/Test.php',
            content: '<?php class Test {}',
            description: 'New test file'
        );

        $this->assertEquals(FileOperationType::Create, $op->type);
        $this->assertEquals('app/Test.php', $op->path);
        $this->assertNull($op->newPath);
        $this->assertEquals('<?php class Test {}', $op->templateContent);
    }

    public function test_file_operation_dto_modify(): void
    {
        $changes = [
            PlannedChange::add('methods', 'public function test() {}', 'Add test method'),
        ];

        $op = FileOperation::modify(
            path: 'app/Test.php',
            changes: $changes,
            description: 'Add method'
        );

        $this->assertEquals(FileOperationType::Modify, $op->type);
        $this->assertCount(1, $op->changes);
    }

    public function test_file_operation_dto_validation(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        // Create without content should fail
        new FileOperation(
            type: FileOperationType::Create,
            path: 'app/Test.php',
            newPath: null,
            description: 'Test',
            changes: null,
            templateContent: null, // Missing!
            priority: 1,
            dependencies: [],
        );
    }

    public function test_planned_change_dto(): void
    {
        $change = PlannedChange::replace(
            section: 'constructor',
            before: 'public function __construct() {}',
            after: 'public function __construct(private Service $service) {}',
            explanation: 'Add dependency injection'
        );

        $this->assertEquals('replace', $change->changeType);
        $this->assertNotNull($change->before);
        $this->assertNotNull($change->after);
    }

    public function test_risk_assessment_calculation(): void
    {
        $risks = [
            ['level' => 'high', 'description' => 'Breaking change', 'mitigation' => 'Test'],
            ['level' => 'medium', 'description' => 'Performance', 'mitigation' => null],
        ];

        $assessment = RiskAssessment::calculate($risks, ['Run migrations'], ['Update config']);

        $this->assertEquals('high', $assessment->overallLevel);
        $this->assertTrue($assessment->requiresManualSteps);
        $this->assertCount(2, $assessment->risks);
    }

    public function test_validation_result_merge(): void
    {
        $r1 = ValidationResult::invalid(['Error 1']);
        $r2 = ValidationResult::validWithWarnings(['Warning 1']);

        $merged = $r1->merge($r2);

        $this->assertFalse($merged->isValid);
        $this->assertContains('Error 1', $merged->errors);
        $this->assertContains('Warning 1', $merged->warnings);
    }

    // =========================================================================
    // Enum Tests
    // =========================================================================

    public function test_plan_status_transitions(): void
    {
        $this->assertTrue(PlanStatus::Draft->canTransitionTo(PlanStatus::PendingReview));
        $this->assertTrue(PlanStatus::PendingReview->canTransitionTo(PlanStatus::Approved));
        $this->assertFalse(PlanStatus::Completed->canTransitionTo(PlanStatus::Draft));
        $this->assertTrue(PlanStatus::Draft->isModifiable());
        $this->assertFalse(PlanStatus::Executing->isModifiable());
    }

    public function test_file_operation_type_properties(): void
    {
        $this->assertTrue(FileOperationType::Create->requiresContent());
        $this->assertFalse(FileOperationType::Delete->requiresContent());
        $this->assertTrue(FileOperationType::Rename->requiresNewPath());
        $this->assertTrue(FileOperationType::Delete->isDestructive());
    }
}
