<?php

namespace Tests\Unit;

use App\DTOs\FileOperation;
use App\DTOs\PlannedChange;
use App\DTOs\RiskAssessment;
use App\DTOs\ValidationResult;
use App\Enums\FileOperationType;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class PlanningDTOsTest extends TestCase
{
    // =========================================================================
    // PlannedChange Tests
    // =========================================================================

    public function test_planned_change_add(): void
    {
        $change = PlannedChange::add(
            section: 'methods',
            content: 'public function newMethod() {}',
            explanation: 'Adding new method',
            afterLine: 50
        );

        $this->assertEquals('add', $change->changeType);
        $this->assertEquals('methods', $change->section);
        $this->assertNull($change->before);
        $this->assertEquals('public function newMethod() {}', $change->after);
        $this->assertEquals(50, $change->startLine);
    }

    public function test_planned_change_remove(): void
    {
        $change = PlannedChange::remove(
            section: 'deprecated',
            content: 'public function oldMethod() {}',
            explanation: 'Removing deprecated method',
            startLine: 30,
            endLine: 35
        );

        $this->assertEquals('remove', $change->changeType);
        $this->assertEquals('public function oldMethod() {}', $change->before);
        $this->assertEquals('', $change->after);
        $this->assertEquals(30, $change->startLine);
        $this->assertEquals(35, $change->endLine);
    }

    public function test_planned_change_replace(): void
    {
        $change = PlannedChange::replace(
            section: 'constructor',
            before: 'public function __construct() {}',
            after: 'public function __construct(private Service $s) {}',
            explanation: 'Add dependency injection'
        );

        $this->assertEquals('replace', $change->changeType);
        $this->assertNotNull($change->before);
        $this->assertNotNull($change->after);
    }

    public function test_planned_change_from_array(): void
    {
        $data = [
            'section' => 'imports',
            'change_type' => 'add',
            'before' => null,
            'after' => 'use App\\Services\\NewService;',
            'start_line' => 5,
            'end_line' => 5,
            'explanation' => 'Add import',
        ];

        $change = PlannedChange::fromArray($data);

        $this->assertEquals('imports', $change->section);
        $this->assertEquals('add', $change->changeType);
        $this->assertEquals(5, $change->startLine);
    }

    public function test_planned_change_invalid_type_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new PlannedChange(
            section: 'test',
            changeType: 'invalid', // Not valid!
            before: null,
            after: 'code',
            startLine: null,
            endLine: null,
            explanation: 'test',
        );
    }

    public function test_planned_change_replace_requires_before(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new PlannedChange(
            section: 'test',
            changeType: 'replace',
            before: null, // Required for replace!
            after: 'new code',
            startLine: null,
            endLine: null,
            explanation: 'test',
        );
    }

    public function test_planned_change_estimated_lines(): void
    {
        $change = PlannedChange::replace(
            section: 'method',
            before: "line1\nline2\nline3",
            after: "line1\nline2\nline3\nline4\nline5",
            explanation: 'Expand method'
        );

        $this->assertEquals(5, $change->getEstimatedLinesChanged());
        $this->assertFalse($change->isSignificant()); // < 5 lines
    }

    public function test_planned_change_to_array(): void
    {
        $change = PlannedChange::add('test', 'code', 'explanation', 10);
        $array = $change->toArray();

        $this->assertArrayHasKey('section', $array);
        $this->assertArrayHasKey('change_type', $array);
        $this->assertArrayHasKey('after', $array);
        $this->assertArrayHasKey('explanation', $array);
    }

    // =========================================================================
    // FileOperation Tests
    // =========================================================================

    public function test_file_operation_create(): void
    {
        $op = FileOperation::create(
            path: 'app/Services/TestService.php',
            content: '<?php class TestService {}',
            description: 'New service'
        );

        $this->assertEquals(FileOperationType::Create, $op->type);
        $this->assertEquals('app/Services/TestService.php', $op->path);
        $this->assertEquals('<?php class TestService {}', $op->templateContent);
        $this->assertNull($op->changes);
        $this->assertEquals(1, $op->priority);
    }

    public function test_file_operation_modify(): void
    {
        $changes = [
            PlannedChange::add('test', 'code', 'explanation'),
        ];

        $op = FileOperation::modify(
            path: 'app/Models/User.php',
            changes: $changes,
            description: 'Add relationship',
            priority: 2,
            dependencies: ['app/Models/Post.php']
        );

        $this->assertEquals(FileOperationType::Modify, $op->type);
        $this->assertCount(1, $op->changes);
        $this->assertEquals(2, $op->priority);
        $this->assertContains('app/Models/Post.php', $op->dependencies);
    }

    public function test_file_operation_delete(): void
    {
        $op = FileOperation::delete(
            path: 'app/Services/OldService.php',
            reason: 'Deprecated service'
        );

        $this->assertEquals(FileOperationType::Delete, $op->type);
        $this->assertEquals('Deprecated service', $op->description);
    }

    public function test_file_operation_rename(): void
    {
        $op = FileOperation::rename(
            path: 'app/Services/OldName.php',
            newPath: 'app/Services/NewName.php',
            description: 'Rename service'
        );

        $this->assertEquals(FileOperationType::Rename, $op->type);
        $this->assertEquals('app/Services/OldName.php', $op->path);
        $this->assertEquals('app/Services/NewName.php', $op->newPath);
    }

    public function test_file_operation_move(): void
    {
        $op = FileOperation::move(
            path: 'app/OldDir/Service.php',
            newPath: 'app/NewDir/Service.php',
            description: 'Move to new directory'
        );

        $this->assertEquals(FileOperationType::Move, $op->type);
    }

    public function test_file_operation_from_array(): void
    {
        $data = [
            'type' => 'create',
            'path' => 'app/Test.php',
            'priority' => 1,
            'description' => 'Test file',
            'template_content' => '<?php class Test {}',
            'dependencies' => [],
        ];

        $op = FileOperation::fromArray($data);

        $this->assertEquals(FileOperationType::Create, $op->type);
        $this->assertEquals('app/Test.php', $op->path);
    }

    public function test_file_operation_from_array_with_changes(): void
    {
        $data = [
            'type' => 'modify',
            'path' => 'app/Test.php',
            'priority' => 1,
            'description' => 'Modify test',
            'changes' => [
                [
                    'section' => 'methods',
                    'change_type' => 'add',
                    'after' => 'public function test() {}',
                    'explanation' => 'Add method',
                ],
            ],
            'dependencies' => [],
        ];

        $op = FileOperation::fromArray($data);

        $this->assertCount(1, $op->changes);
        $this->assertInstanceOf(PlannedChange::class, $op->changes[0]);
    }

    public function test_file_operation_create_requires_content(): void
    {
        $this->expectException(InvalidArgumentException::class);

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

    public function test_file_operation_modify_requires_changes(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new FileOperation(
            type: FileOperationType::Modify,
            path: 'app/Test.php',
            newPath: null,
            description: 'Test',
            changes: null, // Missing!
            templateContent: null,
            priority: 1,
            dependencies: [],
        );
    }

    public function test_file_operation_rename_requires_new_path(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new FileOperation(
            type: FileOperationType::Rename,
            path: 'app/Old.php',
            newPath: null, // Missing!
            description: 'Rename',
            changes: null,
            templateContent: null,
            priority: 1,
            dependencies: [],
        );
    }

    public function test_file_operation_helper_methods(): void
    {
        $op = FileOperation::create(
            path: 'app/Services/Payment/StripeService.php',
            content: "<?php\n\nclass StripeService\n{\n    // ...\n}",
            description: 'Stripe integration'
        );

        $this->assertEquals('php', $op->getExtension());
        $this->assertEquals('app/Services/Payment', $op->getDirectory());
        $this->assertEquals('StripeService', $op->getBasename());
        $this->assertEquals(6, $op->getEstimatedLinesAffected());
    }

    public function test_file_operation_depends_on(): void
    {
        $op = FileOperation::modify(
            path: 'routes/web.php',
            changes: [PlannedChange::add('routes', 'Route::get(...)', 'Add route')],
            description: 'Add routes',
            dependencies: ['app/Http/Controllers/TestController.php']
        );

        $this->assertTrue($op->dependsOn('app/Http/Controllers/TestController.php'));
        $this->assertFalse($op->dependsOn('app/Models/User.php'));
    }

    public function test_file_operation_to_array(): void
    {
        $op = FileOperation::create('app/Test.php', '<?php', 'Test');
        $array = $op->toArray();

        $this->assertEquals('create', $array['type']);
        $this->assertEquals('app/Test.php', $array['path']);
        $this->assertEquals('<?php', $array['template_content']);
    }

    // =========================================================================
    // RiskAssessment Tests
    // =========================================================================

    public function test_risk_assessment_low(): void
    {
        $risk = RiskAssessment::low(['Run migrations']);

        $this->assertEquals('low', $risk->overallLevel);
        $this->assertEmpty($risk->risks);
        $this->assertContains('Run migrations', $risk->prerequisites);
        $this->assertFalse($risk->requiresManualSteps);
    }

    public function test_risk_assessment_calculate_from_risks(): void
    {
        $risks = [
            ['level' => 'low', 'description' => 'Minor change', 'mitigation' => null],
            ['level' => 'medium', 'description' => 'Database change', 'mitigation' => 'Backup first'],
        ];

        $assessment = RiskAssessment::calculate($risks);

        $this->assertEquals('medium', $assessment->overallLevel);
        $this->assertCount(2, $assessment->risks);
    }

    public function test_risk_assessment_high_with_high_risk(): void
    {
        $risks = [
            ['level' => 'high', 'description' => 'Breaking change', 'mitigation' => 'Full testing'],
        ];

        $assessment = RiskAssessment::calculate($risks);

        $this->assertEquals('high', $assessment->overallLevel);
    }

    public function test_risk_assessment_high_with_multiple_medium(): void
    {
        $risks = [
            ['level' => 'medium', 'description' => 'Risk 1', 'mitigation' => null],
            ['level' => 'medium', 'description' => 'Risk 2', 'mitigation' => null],
        ];

        $assessment = RiskAssessment::calculate($risks);

        $this->assertEquals('high', $assessment->overallLevel);
    }

    public function test_risk_assessment_get_high_risks(): void
    {
        $risks = [
            ['level' => 'high', 'description' => 'High risk', 'mitigation' => null],
            ['level' => 'low', 'description' => 'Low risk', 'mitigation' => null],
        ];

        $assessment = RiskAssessment::calculate($risks);
        $highRisks = $assessment->getHighRisks();

        $this->assertCount(1, $highRisks);
    }

    public function test_risk_assessment_counts(): void
    {
        $risks = [
            ['level' => 'high', 'description' => 'H1', 'mitigation' => null],
            ['level' => 'medium', 'description' => 'M1', 'mitigation' => null],
            ['level' => 'low', 'description' => 'L1', 'mitigation' => null],
            ['level' => 'low', 'description' => 'L2', 'mitigation' => null],
        ];

        $assessment = RiskAssessment::calculate($risks);
        $counts = $assessment->getRiskCounts();

        $this->assertEquals(1, $counts['high']);
        $this->assertEquals(1, $counts['medium']);
        $this->assertEquals(2, $counts['low']);
    }

    public function test_risk_assessment_safe_for_auto_execution(): void
    {
        $safeAssessment = RiskAssessment::low();
        $this->assertTrue($safeAssessment->isSafeForAutoExecution());

        $unsafeAssessment = RiskAssessment::calculate(
            [['level' => 'medium', 'description' => 'Risk', 'mitigation' => null]]
        );
        $this->assertFalse($unsafeAssessment->isSafeForAutoExecution());
    }

    public function test_risk_assessment_summary(): void
    {
        $assessment = RiskAssessment::low();
        $this->assertEquals('No risks identified', $assessment->getSummary());

        $riskyAssessment = RiskAssessment::calculate([
            ['level' => 'high', 'description' => 'Test', 'mitigation' => null],
        ]);
        $this->assertStringContainsString('high', $riskyAssessment->getSummary());
    }

    public function test_risk_assessment_from_array(): void
    {
        $data = [
            'overall_level' => 'medium',
            'risks' => [['level' => 'medium', 'description' => 'Test', 'mitigation' => null]],
            'prerequisites' => ['Backup database'],
            'requires_manual_steps' => true,
            'manual_steps' => ['Run seed'],
        ];

        $assessment = RiskAssessment::fromArray($data);

        $this->assertEquals('medium', $assessment->overallLevel);
        $this->assertTrue($assessment->requiresManualSteps);
    }

    // =========================================================================
    // ValidationResult Tests
    // =========================================================================

    public function test_validation_result_valid(): void
    {
        $result = ValidationResult::valid();

        $this->assertTrue($result->isValid);
        $this->assertEmpty($result->errors);
        $this->assertEmpty($result->warnings);
    }

    public function test_validation_result_valid_with_warnings(): void
    {
        $result = ValidationResult::validWithWarnings(['Check config']);

        $this->assertTrue($result->isValid);
        $this->assertEmpty($result->errors);
        $this->assertContains('Check config', $result->warnings);
    }

    public function test_validation_result_invalid(): void
    {
        $result = ValidationResult::invalid(
            errors: ['Missing file'],
            warnings: ['Consider refactoring'],
            missingFiles: ['app/Test.php'],
            circularDependencies: []
        );

        $this->assertFalse($result->isValid);
        $this->assertTrue($result->hasErrors());
        $this->assertTrue($result->hasWarnings());
        $this->assertTrue($result->hasMissingFiles());
    }

    public function test_validation_result_with_circular_dependencies(): void
    {
        $result = ValidationResult::invalid(
            errors: ['Circular dependency detected'],
            circularDependencies: [
                ['from' => 'A.php', 'to' => 'B.php', 'cycle' => ['A.php', 'B.php']],
            ]
        );

        $this->assertTrue($result->hasCircularDependencies());
    }

    public function test_validation_result_merge(): void
    {
        $r1 = ValidationResult::invalid(['Error 1'], ['Warning 1']);
        $r2 = ValidationResult::validWithWarnings(['Warning 2']);

        $merged = $r1->merge($r2);

        $this->assertFalse($merged->isValid);
        $this->assertContains('Error 1', $merged->errors);
        $this->assertContains('Warning 1', $merged->warnings);
        $this->assertContains('Warning 2', $merged->warnings);
    }

    public function test_validation_result_with_error(): void
    {
        $result = ValidationResult::valid();
        $withError = $result->withError('New error');

        $this->assertTrue($result->isValid); // Original unchanged
        $this->assertFalse($withError->isValid);
        $this->assertContains('New error', $withError->errors);
    }

    public function test_validation_result_with_warning(): void
    {
        $result = ValidationResult::valid();
        $withWarning = $result->withWarning('New warning');

        $this->assertTrue($withWarning->isValid); // Still valid
        $this->assertContains('New warning', $withWarning->warnings);
    }

    public function test_validation_result_summary(): void
    {
        $valid = ValidationResult::valid();
        $this->assertStringContainsString('valid', $valid->getSummary());

        $invalid = ValidationResult::invalid(['Error'], [], ['missing.php']);
        $this->assertStringContainsString('invalid', $invalid->getSummary());
        $this->assertStringContainsString('error', $invalid->getSummary());
    }

    public function test_validation_result_total_issue_count(): void
    {
        $result = ValidationResult::invalid(
            errors: ['E1', 'E2'],
            missingFiles: ['M1'],
            circularDependencies: [
                ['from' => 'A', 'to' => 'B', 'cycle' => []],
            ]
        );

        $this->assertEquals(4, $result->getTotalIssueCount());
    }

    public function test_validation_result_from_array(): void
    {
        $data = [
            'is_valid' => false,
            'errors' => ['Test error'],
            'warnings' => [],
            'missing_files' => [],
            'circular_dependencies' => [],
        ];

        $result = ValidationResult::fromArray($data);

        $this->assertFalse($result->isValid);
        $this->assertContains('Test error', $result->errors);
    }
}
