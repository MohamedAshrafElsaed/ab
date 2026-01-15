<?php

namespace Tests\Feature;

use App\DTOs\IntentAnalysisResult;
use App\Enums\ComplexityLevel;
use App\Enums\IntentType;
use App\Models\IntentAnalysis;
use App\Models\Project;
use App\Models\User;
use App\Services\AI\IntentAnalyzerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IntentAnalyzerServiceTest extends TestCase
{
    use RefreshDatabase;

    private IntentAnalyzerService $service;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(IntentAnalyzerService::class);

        $user = User::factory()->create();
        $this->project = Project::factory()->create([
            'user_id' => $user->id,
            'status' => 'ready',
            'stack_info' => [
                'framework' => 'laravel',
                'frontend' => ['vue', 'inertia'],
                'features' => ['authentication', 'api'],
            ],
        ]);
    }

    public function test_analyzes_clear_feature_request(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [
                    [
                        'type' => 'text',
                        'text' => json_encode([
                            'intent_type' => 'feature_request',
                            'confidence_score' => 0.95,
                            'extracted_entities' => [
                                'files' => ['UserController.php'],
                                'components' => ['export button'],
                                'features' => ['CSV export'],
                                'symbols' => [],
                            ],
                            'domain_classification' => [
                                'primary' => 'users',
                                'secondary' => ['api'],
                            ],
                            'complexity_estimate' => 'medium',
                            'requires_clarification' => false,
                            'clarification_questions' => [],
                        ]),
                    ],
                ],
                'usage' => ['input_tokens' => 100, 'output_tokens' => 50],
            ], 200),
        ]);

        $analysis = $this->service->analyze(
            $this->project,
            'Add a button to export users as CSV in the admin panel'
        );

        $this->assertInstanceOf(IntentAnalysis::class, $analysis);
        $this->assertEquals(IntentType::FeatureRequest, $analysis->intent_type);
        $this->assertGreaterThan(0.8, $analysis->confidence_score);
        $this->assertEquals(ComplexityLevel::Medium, $analysis->complexity_estimate);
        $this->assertFalse($analysis->requires_clarification);
        $this->assertContains('UserController.php', $analysis->mentioned_files);

        $this->assertDatabaseHas('intent_analyses', [
            'project_id' => $this->project->id,
            'intent_type' => 'feature_request',
        ]);
    }

    public function test_analyzes_ambiguous_bug_report(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [
                    [
                        'type' => 'text',
                        'text' => json_encode([
                            'intent_type' => 'bug_fix',
                            'confidence_score' => 0.65,
                            'extracted_entities' => [
                                'files' => [],
                                'components' => ['login form'],
                                'features' => [],
                                'symbols' => [],
                            ],
                            'domain_classification' => [
                                'primary' => 'auth',
                                'secondary' => [],
                            ],
                            'complexity_estimate' => 'simple',
                            'requires_clarification' => true,
                            'clarification_questions' => [
                                'What error message do you see?',
                                'What steps reproduce the issue?',
                            ],
                        ]),
                    ],
                ],
                'usage' => ['input_tokens' => 80, 'output_tokens' => 60],
            ], 200),
        ]);

        $analysis = $this->service->analyze(
            $this->project,
            'The login form is broken'
        );

        $this->assertEquals(IntentType::BugFix, $analysis->intent_type);
        $this->assertLessThan(0.8, $analysis->confidence_score);
        $this->assertTrue($analysis->requires_clarification);
        $this->assertNotEmpty($analysis->clarification_questions);
        $this->assertEquals('auth', $analysis->primary_domain);
    }

    public function test_handles_multi_intent_message(): void
    {
        $result = $this->service->detectMultipleIntents(
            'Fix the login bug and also add a new export feature for reports'
        );

        $this->assertTrue($result['is_multi_intent']);
        $this->assertContains('bug_fix', $result['detected_intents']);
        $this->assertContains('feature_request', $result['detected_intents']);
        $this->assertNotNull($result['suggestion']);
    }

    public function test_request_needing_clarification(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [
                    [
                        'type' => 'text',
                        'text' => json_encode([
                            'intent_type' => 'unknown',
                            'confidence_score' => 0.25,
                            'extracted_entities' => [
                                'files' => [],
                                'components' => [],
                                'features' => [],
                                'symbols' => [],
                            ],
                            'domain_classification' => [
                                'primary' => 'general',
                                'secondary' => [],
                            ],
                            'complexity_estimate' => 'medium',
                            'requires_clarification' => true,
                            'clarification_questions' => [
                                'What would you like me to improve?',
                                'Which file or component are you referring to?',
                            ],
                        ]),
                    ],
                ],
                'usage' => ['input_tokens' => 50, 'output_tokens' => 40],
            ], 200),
        ]);

        $analysis = $this->service->analyze(
            $this->project,
            'Can you make it better?'
        );

        $this->assertEquals(IntentType::Unknown, $analysis->intent_type);
        $this->assertTrue($this->service->needsClarification($analysis));
        $this->assertNotEmpty($this->service->generateClarificationQuestions($analysis));
    }

    public function test_handles_different_complexity_levels(): void
    {
        // Test trivial complexity
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => json_encode([
                    'intent_type' => 'feature_request',
                    'confidence_score' => 0.9,
                    'extracted_entities' => ['files' => [], 'components' => [], 'features' => [], 'symbols' => []],
                    'domain_classification' => ['primary' => 'general', 'secondary' => []],
                    'complexity_estimate' => 'trivial',
                    'requires_clarification' => false,
                    'clarification_questions' => [],
                ])]],
                'usage' => ['input_tokens' => 50, 'output_tokens' => 40],
            ], 200),
        ]);

        $analysis = $this->service->analyze($this->project, 'Fix the typo in the readme');
        $this->assertEquals('trivial', $analysis->complexity_estimate->value);
    }

    public function test_handles_simple_complexity(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => json_encode([
                    'intent_type' => 'feature_request',
                    'confidence_score' => 0.9,
                    'extracted_entities' => ['files' => [], 'components' => [], 'features' => [], 'symbols' => []],
                    'domain_classification' => ['primary' => 'general', 'secondary' => []],
                    'complexity_estimate' => 'simple',
                    'requires_clarification' => false,
                    'clarification_questions' => [],
                ])]],
                'usage' => ['input_tokens' => 50, 'output_tokens' => 40],
            ], 200),
        ]);

        $analysis = $this->service->analyze($this->project, 'Add validation to the email field');
        $this->assertEquals('simple', $analysis->complexity_estimate->value);
    }

    public function test_handles_major_complexity(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => json_encode([
                    'intent_type' => 'feature_request',
                    'confidence_score' => 0.9,
                    'extracted_entities' => ['files' => [], 'components' => [], 'features' => [], 'symbols' => []],
                    'domain_classification' => ['primary' => 'general', 'secondary' => []],
                    'complexity_estimate' => 'major',
                    'requires_clarification' => false,
                    'clarification_questions' => [],
                ])]],
                'usage' => ['input_tokens' => 50, 'output_tokens' => 40],
            ], 200),
        ]);

        $analysis = $this->service->analyze($this->project, 'Migrate the entire authentication system to use OAuth2');
        $this->assertEquals('major', $analysis->complexity_estimate->value);
    }

    public function test_high_confidence_does_not_need_clarification(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => json_encode([
                    'intent_type' => 'feature_request',
                    'confidence_score' => 0.95,
                    'extracted_entities' => ['files' => [], 'components' => [], 'features' => [], 'symbols' => []],
                    'domain_classification' => ['primary' => 'ui', 'secondary' => []],
                    'complexity_estimate' => 'simple',
                    'requires_clarification' => false,
                    'clarification_questions' => [],
                ])]],
                'usage' => ['input_tokens' => 50, 'output_tokens' => 40],
            ], 200),
        ]);

        $analysis = $this->service->analyze($this->project, 'Add a logout button to the navbar');
        $this->assertFalse($this->service->needsClarification($analysis));
    }

    public function test_low_confidence_needs_clarification(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => json_encode([
                    'intent_type' => 'unknown',
                    'confidence_score' => 0.3,
                    'extracted_entities' => ['files' => [], 'components' => [], 'features' => [], 'symbols' => []],
                    'domain_classification' => ['primary' => 'general', 'secondary' => []],
                    'complexity_estimate' => 'medium',
                    'requires_clarification' => true,
                    'clarification_questions' => ['What do you mean?'],
                ])]],
                'usage' => ['input_tokens' => 50, 'output_tokens' => 40],
            ], 200),
        ]);

        $analysis = $this->service->analyze($this->project, 'Make it better');
        $this->assertTrue($this->service->needsClarification($analysis));
    }

    public function test_model_scopes_for_feature_request(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => json_encode([
                    'intent_type' => 'feature_request',
                    'confidence_score' => 0.95,
                    'extracted_entities' => ['files' => [], 'components' => [], 'features' => [], 'symbols' => []],
                    'domain_classification' => ['primary' => 'ui', 'secondary' => []],
                    'complexity_estimate' => 'medium',
                    'requires_clarification' => false,
                    'clarification_questions' => [],
                ])]],
                'usage' => ['input_tokens' => 50, 'output_tokens' => 40],
            ], 200),
        ]);

        $this->service->analyze($this->project, 'Add a button');

        $this->assertEquals(1, IntentAnalysis::ofType(IntentType::FeatureRequest)->count());
        $this->assertEquals(1, IntentAnalysis::highConfidence(0.8)->count());

        // Use query() to explicitly use the scope
        $this->assertEquals(1, IntentAnalysis::query()->requiresCodeChanges()->count());
    }

    public function test_model_scopes_for_bug_fix(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => json_encode([
                    'intent_type' => 'bug_fix',
                    'confidence_score' => 0.85,
                    'extracted_entities' => ['files' => [], 'components' => [], 'features' => [], 'symbols' => []],
                    'domain_classification' => ['primary' => 'auth', 'secondary' => []],
                    'complexity_estimate' => 'simple',
                    'requires_clarification' => false,
                    'clarification_questions' => [],
                ])]],
                'usage' => ['input_tokens' => 50, 'output_tokens' => 40],
            ], 200),
        ]);

        $this->service->analyze($this->project, 'Fix login bug');

        $this->assertEquals(1, IntentAnalysis::ofType(IntentType::BugFix)->count());
        $this->assertEquals(1, IntentAnalysis::highConfidence(0.8)->count());
    }


    public function test_dto_creation_from_claude_response(): void
    {
        $responseData = [
            'intent_type' => 'test_writing',
            'confidence_score' => 0.92,
            'extracted_entities' => [
                'files' => ['UserService.php', 'UserServiceTest.php'],
                'components' => [],
                'features' => ['user registration'],
                'symbols' => ['UserService', 'registerUser'],
            ],
            'domain_classification' => [
                'primary' => 'testing',
                'secondary' => ['services', 'users'],
            ],
            'complexity_estimate' => 'medium',
            'requires_clarification' => false,
            'clarification_questions' => [],
        ];

        $result = IntentAnalysisResult::fromClaudeResponse($responseData);

        $this->assertEquals(IntentType::TestWriting, $result->intentType);
        $this->assertEquals(0.92, $result->confidenceScore);
        $this->assertEquals(ComplexityLevel::Medium, $result->complexityEstimate);
        $this->assertFalse($result->requiresClarification);
        $this->assertContains('UserService.php', $result->getMentionedFiles());
        $this->assertEquals('testing', $result->getPrimaryDomain());
        $this->assertContains('services', $result->getSecondaryDomains());
    }

    public function test_dto_handles_malformed_response(): void
    {
        $malformedData = [
            'intent_type' => 'invalid_type',
            'confidence_score' => 150,
            'extracted_entities' => 'not an array',
        ];

        $result = IntentAnalysisResult::fromClaudeResponse($malformedData);

        $this->assertEquals(IntentType::Unknown, $result->intentType);
        $this->assertEquals(1.0, $result->confidenceScore);
        $this->assertIsArray($result->extractedEntities);
    }

    public function test_handles_api_failure_gracefully(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response(['error' => 'Rate limited'], 429),
        ]);

        $analysis = $this->service->analyze(
            $this->project,
            'Add a feature'
        );

        $this->assertEquals(IntentType::Unknown, $analysis->intent_type);
        $this->assertTrue($analysis->requires_clarification);
        $this->assertNotEmpty($analysis->metadata['error'] ?? '');
    }

    public function test_handles_malformed_json_response(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [
                    [
                        'type' => 'text',
                        'text' => 'This is not valid JSON at all { broken',
                    ],
                ],
                'usage' => ['input_tokens' => 50, 'output_tokens' => 40],
            ], 200),
        ]);

        $analysis = $this->service->analyze(
            $this->project,
            'Add a feature'
        );

        $this->assertInstanceOf(IntentAnalysis::class, $analysis);
        $this->assertTrue($analysis->requires_clarification);
    }

    public function test_enum_values_and_methods(): void
    {
        $this->assertEquals('Feature Request', IntentType::FeatureRequest->label());
        $this->assertTrue(IntentType::FeatureRequest->requiresCodeChanges());
        $this->assertFalse(IntentType::Question->requiresCodeChanges());

        $this->assertEquals(3, ComplexityLevel::Medium->weight());
        $this->assertTrue(ComplexityLevel::Complex->isHigherThan(ComplexityLevel::Simple));
        $this->assertFalse(ComplexityLevel::Trivial->isHigherThan(ComplexityLevel::Medium));

        $this->assertEquals(ComplexityLevel::Trivial, ComplexityLevel::fromScore(0.1));
        $this->assertEquals(ComplexityLevel::Major, ComplexityLevel::fromScore(0.9));
    }

    public function test_conversation_history_formatting(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [
                    [
                        'type' => 'text',
                        'text' => json_encode([
                            'intent_type' => 'clarification',
                            'confidence_score' => 0.88,
                            'extracted_entities' => ['files' => ['UserController.php'], 'components' => [], 'features' => [], 'symbols' => []],
                            'domain_classification' => ['primary' => 'users', 'secondary' => []],
                            'complexity_estimate' => 'simple',
                            'requires_clarification' => false,
                            'clarification_questions' => [],
                        ]),
                    ],
                ],
                'usage' => ['input_tokens' => 100, 'output_tokens' => 50],
            ], 200),
        ]);

        $conversationHistory = [
            ['role' => 'user', 'content' => 'How do I add a new user?'],
            ['role' => 'assistant', 'content' => 'You can use the UserController...'],
        ];

        $analysis = $this->service->analyze(
            $this->project,
            'Yes, please add that to UserController.php',
            $conversationHistory
        );

        $this->assertEquals(IntentType::Clarification, $analysis->intent_type);
        $this->assertContains('UserController.php', $analysis->mentioned_files);
    }
}
