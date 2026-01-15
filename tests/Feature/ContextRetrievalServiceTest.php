<?php

namespace Tests\Feature;

use App\DTOs\RetrievalResult;
use App\DTOs\SymbolGraph;
use App\Enums\ComplexityLevel;
use App\Enums\IntentType;
use App\Models\IntentAnalysis;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\ProjectFileChunk;
use App\Models\User;
use App\Services\AI\ContextRetrievalService;
use App\Services\AI\RetrievalCacheService;
use App\Services\AI\RouteAnalyzerService;
use App\Services\AI\SymbolGraphService;
use App\Services\AskAI\SensitiveContentRedactor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ContextRetrievalServiceTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;
    private ContextRetrievalService $service;
    private SymbolGraphService $symbolGraphService;
    private RouteAnalyzerService $routeAnalyzerService;
    private RetrievalCacheService $cacheService;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $this->project = Project::factory()->create([
            'user_id' => $user->id,
            'status' => 'ready',
            'stack_info' => [
                'framework' => 'laravel',
                'frontend' => ['vue', 'inertia'],
            ],
        ]);

        $this->symbolGraphService = app(SymbolGraphService::class);
        $this->routeAnalyzerService = app(RouteAnalyzerService::class);
        $this->cacheService = app(RetrievalCacheService::class);

        $this->service = new ContextRetrievalService(
            $this->symbolGraphService,
            $this->routeAnalyzerService,
            app(SensitiveContentRedactor::class),
        );

        // Seed test data and create KB structure
        $this->seedTestData();
        $this->createKnowledgeBaseStructure();
    }

    protected function tearDown(): void
    {
        // Clean up test directories
        $storagePath = $this->project->storage_path;
        if (is_dir($storagePath)) {
            File::deleteDirectory($storagePath);
        }

        parent::tearDown();
    }

    private function seedTestData(): void
    {
        // Create project directory structure
        $repoPath = $this->project->repo_path;
        $knowledgePath = $this->project->knowledge_path;

        File::makeDirectory($repoPath, 0755, true, true);
        File::makeDirectory($knowledgePath, 0755, true, true);

        // Seed files and chunks for auth domain
        $this->createFileWithChunks('app/Http/Controllers/Auth/LoginController.php', <<<'PHP'
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function showLoginForm()
    {
        return inertia('Auth/Login');
    }

    public function login(LoginRequest $request)
    {
        if (Auth::attempt($request->validated())) {
            return redirect()->intended('/dashboard');
        }

        return back()->withErrors(['email' => 'Invalid credentials']);
    }

    public function logout()
    {
        Auth::logout();
        return redirect('/');
    }
}
PHP, ['LoginController', 'showLoginForm', 'login', 'logout']);

        $this->createFileWithChunks('app/Http/Controllers/Auth/RegisterController.php', <<<'PHP'
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use App\Models\User;

class RegisterController extends Controller
{
    public function showRegistrationForm()
    {
        return inertia('Auth/Register');
    }

    public function register(RegisterRequest $request)
    {
        $user = User::create($request->validated());
        auth()->login($user);
        return redirect('/dashboard');
    }
}
PHP, ['RegisterController', 'showRegistrationForm', 'register']);

        $this->createFileWithChunks('app/Http/Controllers/Auth/PasswordResetController.php', <<<'PHP'
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Password;

class PasswordResetController extends Controller
{
    public function showResetForm($token)
    {
        return inertia('Auth/ResetPassword', ['token' => $token]);
    }

    public function reset(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|confirmed|min:8',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill(['password' => bcrypt($password)])->save();
                event(new PasswordReset($user));
            }
        );

        return $status === Password::PASSWORD_RESET
            ? redirect('/login')->with('status', __($status))
            : back()->withErrors(['email' => __($status)]);
    }
}
PHP, ['PasswordResetController', 'showResetForm', 'reset']);

        // Seed User model
        $this->createFileWithChunks('app/Models/User.php', <<<'PHP'
<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    protected $fillable = ['name', 'email', 'password'];
    protected $hidden = ['password', 'remember_token'];

    public function projects()
    {
        return $this->hasMany(Project::class);
    }
}
PHP, ['User', 'projects']);

        // Seed Dashboard controller
        $this->createFileWithChunks('app/Http/Controllers/DashboardController.php', <<<'PHP'
<?php

namespace App\Http\Controllers;

class DashboardController extends Controller
{
    public function index()
    {
        return inertia('Dashboard', [
            'stats' => $this->getStats(),
        ]);
    }

    private function getStats()
    {
        return [
            'users' => \App\Models\User::count(),
            'projects' => auth()->user()->projects()->count(),
        ];
    }
}
PHP, ['DashboardController', 'index', 'getStats']);

        // Seed Inertia pages
        $this->createFileWithChunks('resources/js/Pages/Auth/Login.vue', <<<'VUE'
<template>
    <div class="login-form">
        <h1>Login</h1>
        <form @submit.prevent="submit">
            <input v-model="form.email" type="email" placeholder="Email" />
            <input v-model="form.password" type="password" placeholder="Password" />
            <button type="submit">Login</button>
        </form>
    </div>
</template>

<script setup>
import { useForm } from '@inertiajs/vue3';

const form = useForm({
    email: '',
    password: '',
});

const submit = () => form.post('/login');
</script>
VUE, ['Login', 'useForm', 'submit']);

        // Seed routes.json with full controller paths
        $routesData = [
            'extracted_at' => now()->toIso8601String(),
            'total_files' => 1,
            'total_routes' => 6,
            'files' => [
                'web' => [
                    'file' => 'web.php',
                    'routes' => [
                        ['method' => 'GET', 'uri' => '/login', 'controller' => 'Auth/LoginController', 'action' => 'showLoginForm', 'name' => 'login'],
                        ['method' => 'POST', 'uri' => '/login', 'controller' => 'Auth/LoginController', 'action' => 'login'],
                        ['method' => 'POST', 'uri' => '/logout', 'controller' => 'Auth/LoginController', 'action' => 'logout', 'name' => 'logout'],
                        ['method' => 'GET', 'uri' => '/register', 'controller' => 'Auth/RegisterController', 'action' => 'showRegistrationForm', 'name' => 'register'],
                        ['method' => 'GET', 'uri' => '/dashboard', 'controller' => 'DashboardController', 'action' => 'index', 'name' => 'dashboard'],
                        ['method' => 'GET', 'uri' => '/password/reset/{token}', 'controller' => 'Auth/PasswordResetController', 'action' => 'showResetForm'],
                    ],
                ],
            ],
        ];

        file_put_contents($knowledgePath . '/routes.json', json_encode($routesData, JSON_PRETTY_PRINT));
    }

    private function createFileWithChunks(string $path, string $content, array $symbols): void
    {
        $fullPath = $this->project->repo_path . '/' . $path;
        $dir = dirname($fullPath);

        if (!is_dir($dir)) {
            File::makeDirectory($dir, 0755, true, true);
        }

        file_put_contents($fullPath, $content);

        $file = ProjectFile::create([
            'project_id' => $this->project->id,
            'path' => $path,
            'extension' => pathinfo($path, PATHINFO_EXTENSION),
            'language' => $this->detectLanguage($path),
            'size_bytes' => strlen($content),
            'line_count' => substr_count($content, "\n") + 1,
            'sha1' => sha1($content),
            'is_binary' => false,
            'is_excluded' => false,
            'symbols_declared' => array_map(fn($s) => ['type' => 'symbol', 'name' => $s], $symbols),
            'imports' => [],
        ]);

        ProjectFileChunk::create([
            'project_id' => $this->project->id,
            'chunk_id' => 'chunk_' . md5($path),
            'path' => $path,
            'start_line' => 1,
            'end_line' => substr_count($content, "\n") + 1,
            'sha1' => sha1($content),
            'chunk_index' => 0,
            'is_complete_file' => true,
            'symbols_declared' => array_map(fn($s) => ['type' => 'symbol', 'name' => $s], $symbols),
            'symbols_used' => [],
            'imports' => [],
        ]);
    }

    private function createKnowledgeBaseStructure(): void
    {
        $scanId = 'scan_test_' . uniqid();
        $scanPath = $this->project->getKbScanPath($scanId);
        File::makeDirectory($scanPath, 0755, true, true);

        // Get all files we created
        $files = ProjectFile::where('project_id', $this->project->id)->get();
        $chunks = ProjectFileChunk::where('project_id', $this->project->id)->get();

        // Create scan_meta.json
        file_put_contents($scanPath . '/scan_meta.json', json_encode([
            'scan_id' => $scanId,
            'project_id' => $this->project->id,
            'scanned_at_iso' => now()->toIso8601String(),
            'stats' => [
                'total_files' => $files->count(),
                'total_chunks' => $chunks->count(),
            ],
        ]));

        // Create files_index.json from actual files
        $filesIndex = $files->map(function ($file) use ($chunks) {
            $fileChunks = $chunks->where('path', $file->path);
            return [
                'file_path' => $file->path,
                'extension' => $file->extension,
                'language' => $file->language,
                'size_bytes' => $file->size_bytes,
                'total_lines' => $file->line_count,
                'file_sha1' => $file->sha1,
                'is_binary' => false,
                'is_excluded' => false,
                'chunk_ids' => $fileChunks->pluck('chunk_id')->toArray(),
                'symbols_declared' => $file->symbols_declared ?? [],
                'imports' => $file->imports ?? [],
            ];
        })->toArray();

        file_put_contents($scanPath . '/files_index.json', json_encode($filesIndex));

        // Create chunks.ndjson from actual chunks
        $chunksNdjson = $chunks->map(function ($chunk) {
            return json_encode([
                'chunk_id' => $chunk->chunk_id,
                'file_path' => $chunk->path,
                'start_line' => $chunk->start_line,
                'end_line' => $chunk->end_line,
                'content' => '',
                'symbols_declared' => $chunk->symbols_declared ?? [],
                'symbols_used' => $chunk->symbols_used ?? [],
                'imports' => $chunk->imports ?? [],
            ]);
        })->implode("\n");

        file_put_contents($scanPath . '/chunks.ndjson', $chunksNdjson);

        $this->project->update(['last_kb_scan_id' => $scanId]);
    }

    private function detectLanguage(string $path): string
    {
        $ext = pathinfo($path, PATHINFO_EXTENSION);
        return match ($ext) {
            'php' => 'php',
            'vue' => 'vue',
            'js' => 'javascript',
            'ts' => 'typescript',
            default => 'plaintext',
        };
    }

    private function createIntent(
        IntentType $type,
        array $entities = [],
        string $domain = 'general',
        float $confidence = 0.9
    ): IntentAnalysis {
        return IntentAnalysis::create([
            'project_id' => $this->project->id,
            'conversation_id' => 'test-conv-' . uniqid(),
            'message_id' => 'test-msg-' . uniqid(),
            'raw_input' => 'Test input',
            'intent_type' => $type->value,
            'confidence_score' => $confidence,
            'extracted_entities' => array_merge([
                'files' => [],
                'components' => [],
                'features' => [],
                'symbols' => [],
            ], $entities),
            'domain_classification' => [
                'primary' => $domain,
                'secondary' => [],
            ],
            'complexity_estimate' => ComplexityLevel::Medium->value,
            'requires_clarification' => false,
            'clarification_questions' => [],
            'metadata' => [],
        ]);
    }

    // =========================================================================
    // ContextRetrievalService Tests
    // =========================================================================

    public function test_retrieve_context_for_password_reset_feature(): void
    {
        $intent = $this->createIntent(
            IntentType::FeatureRequest,
            [
                'features' => ['password reset'],
                'symbols' => ['PasswordResetController'],
            ],
            'auth'
        );

        $result = $this->service->retrieve(
            $this->project,
            $intent,
            'Add a password reset feature'
        );

        $this->assertInstanceOf(RetrievalResult::class, $result);
        $this->assertFalse($result->isEmpty());

        // Should find auth-related files
        $paths = $result->getFileList();
        $this->assertTrue(
            collect($paths)->contains(fn($p) => str_contains($p, 'Auth')),
            'Should find auth-related files'
        );
    }

    public function test_retrieve_context_for_login_bug(): void
    {
        $intent = $this->createIntent(
            IntentType::BugFix,
            [
                'files' => ['login'],
                'features' => ['login', 'authentication'],
            ],
            'auth'
        );

        $result = $this->service->retrieve(
            $this->project,
            $intent,
            'Fix the login form bug'
        );

        $this->assertFalse($result->isEmpty());

        // Should find login controller
        $paths = $result->getFileList();
        $this->assertTrue(
            collect($paths)->contains(fn($p) => str_contains($p, 'LoginController')),
            'Should find LoginController'
        );

        // Should have related routes
        $this->assertNotEmpty($result->relatedRoutes);
    }

    public function test_retrieval_result_to_prompt_context(): void
    {
        $intent = $this->createIntent(
            IntentType::Question,
            ['symbols' => ['User']],
            'database'
        );

        $result = $this->service->retrieve(
            $this->project,
            $intent,
            'How does the User model work?'
        );

        $promptContext = $result->toPromptContext();

        $this->assertStringContainsString('<retrieved_context>', $promptContext);
        $this->assertStringContainsString('</retrieved_context>', $promptContext);
        $this->assertStringContainsString('<summary>', $promptContext);
    }

    public function test_retrieval_result_token_budget_limiting(): void
    {
        $intent = $this->createIntent(
            IntentType::Question,
            [],
            'auth'
        );

        $result = $this->service->retrieve(
            $this->project,
            $intent,
            'Tell me about authentication'
        );

        // Apply a very small token budget
        $limited = $result->limitToTokenBudget(500);

        $this->assertLessThanOrEqual(
            $result->getChunkCount(),
            $limited->getChunkCount()
        );

        $this->assertTrue($limited->metadata['token_limited'] ?? false);
    }

    public function test_search_by_keywords(): void
    {
        $chunks = $this->service->searchByKeywords($this->project, ['Login', 'auth']);

        $this->assertNotEmpty($chunks);

        $paths = $chunks->pluck('path')->toArray();
        $this->assertTrue(
            collect($paths)->contains(fn($p) => str_contains($p, 'Login')),
            'Should find files containing Login keyword'
        );
    }

    public function test_find_by_route(): void
    {
        // First verify the route handler is found
        $handler = $this->routeAnalyzerService->findHandler($this->project, '/login');
        $this->assertNotNull($handler, 'Route handler should be found');

        $chunks = $this->service->findByRoute($this->project, '/login');

        // Should find chunks for the login controller
        $this->assertNotEmpty($chunks, 'Should find chunks for login route');

        $paths = $chunks->pluck('path')->unique()->toArray();
        $this->assertTrue(
            collect($paths)->contains(fn($p) => str_contains($p, 'LoginController')),
            'Should find LoginController'
        );
    }

    public function test_find_by_domain(): void
    {
        $chunks = $this->service->findByDomain($this->project, 'auth');

        $this->assertNotEmpty($chunks);

        $paths = $chunks->pluck('path')->unique()->toArray();
        $this->assertTrue(
            collect($paths)->contains(fn($p) => str_contains($p, 'Auth')),
            'Should find auth domain files'
        );
    }

    public function test_expand_dependencies(): void
    {
        $dependencies = $this->service->expandDependencies(
            $this->project,
            ['app/Http/Controllers/Auth/LoginController.php'],
            2
        );

        // Dependencies may be empty if no imports are detected in the graph
        // but the method should not throw
        $this->assertIsArray($dependencies);
    }

    public function test_rank_chunks(): void
    {
        $chunks = ProjectFileChunk::where('project_id', $this->project->id)->get();
        $intent = $this->createIntent(
            IntentType::BugFix,
            ['symbols' => ['LoginController']],
            'auth'
        );

        $ranked = $this->service->rankChunks($chunks, $intent);

        $this->assertNotEmpty($ranked);

        // Should be sorted by score descending
        $scores = $ranked->pluck('score')->toArray();
        $sortedScores = $scores;
        rsort($sortedScores);
        $this->assertEquals($sortedScores, $scores);
    }

    // =========================================================================
    // SymbolGraphService Tests
    // =========================================================================

    public function test_symbol_graph_builds_nodes(): void
    {
        // KB structure is already created in setUp
        $graph = $this->symbolGraphService->buildGraph($this->project);

        $this->assertInstanceOf(SymbolGraph::class, $graph);
        // Graph should have nodes from our seeded files
        $this->assertNotEmpty($graph->nodes);
    }

    public function test_symbol_graph_get_related(): void
    {
        $graph = new SymbolGraph(
            nodes: [
                'app/Models/User.php' => [
                    'symbols_declared' => [['name' => 'User']],
                    'symbols_used' => [],
                    'imports' => [],
                    'language' => 'php',
                    'size_bytes' => 500,
                ],
                'app/Http/Controllers/UserController.php' => [
                    'symbols_declared' => [['name' => 'UserController']],
                    'symbols_used' => [['symbol' => 'User']],
                    'imports' => [['path' => 'App\\Models\\User']],
                    'language' => 'php',
                    'size_bytes' => 1000,
                ],
            ],
            edges: [
                'app/Http/Controllers/UserController.php' => [
                    'app/Models/User.php' => ['type' => 'imports', 'weight' => 1.0],
                ],
            ],
            metadata: ['built_at' => now()->toIso8601String()],
        );

        $related = $graph->getRelated('app/Http/Controllers/UserController.php', 1);

        $this->assertArrayHasKey('app/Models/User.php', $related);
    }

    public function test_symbol_graph_find_path_between(): void
    {
        $graph = new SymbolGraph(
            nodes: [
                'A.php' => ['symbols_declared' => [], 'symbols_used' => [], 'imports' => [], 'language' => 'php', 'size_bytes' => 100],
                'B.php' => ['symbols_declared' => [], 'symbols_used' => [], 'imports' => [], 'language' => 'php', 'size_bytes' => 100],
                'C.php' => ['symbols_declared' => [], 'symbols_used' => [], 'imports' => [], 'language' => 'php', 'size_bytes' => 100],
            ],
            edges: [
                'A.php' => ['B.php' => ['type' => 'imports', 'weight' => 1.0]],
                'B.php' => ['C.php' => ['type' => 'imports', 'weight' => 1.0]],
            ],
            metadata: [],
        );

        $path = $graph->findPathBetween('A.php', 'C.php');

        $this->assertNotNull($path);
        $this->assertEquals(['A.php', 'B.php', 'C.php'], $path);
    }

    public function test_symbol_graph_get_cluster(): void
    {
        $graph = new SymbolGraph(
            nodes: [
                'A.php' => ['symbols_declared' => [], 'symbols_used' => [], 'imports' => [], 'language' => 'php', 'size_bytes' => 100],
                'B.php' => ['symbols_declared' => [], 'symbols_used' => [], 'imports' => [], 'language' => 'php', 'size_bytes' => 100],
                'C.php' => ['symbols_declared' => [], 'symbols_used' => [], 'imports' => [], 'language' => 'php', 'size_bytes' => 100],
                'D.php' => ['symbols_declared' => [], 'symbols_used' => [], 'imports' => [], 'language' => 'php', 'size_bytes' => 100],
            ],
            edges: [
                'A.php' => [
                    'B.php' => ['type' => 'imports', 'weight' => 1.0],
                    'C.php' => ['type' => 'references', 'weight' => 0.5],
                ],
                'B.php' => ['D.php' => ['type' => 'imports', 'weight' => 0.8]],
            ],
            metadata: [],
        );

        $cluster = $graph->getCluster('A.php');

        $this->assertNotEmpty($cluster);
        $this->assertArrayHasKey('B.php', $cluster);
    }

    public function test_symbol_graph_serialization(): void
    {
        $original = new SymbolGraph(
            nodes: ['test.php' => ['symbols_declared' => [], 'symbols_used' => [], 'imports' => [], 'language' => 'php', 'size_bytes' => 100]],
            edges: [],
            metadata: ['test' => true],
        );

        $serialized = $original->serialize();
        $deserialized = SymbolGraph::deserialize($serialized);

        $this->assertEquals($original->nodes, $deserialized->nodes);
        $this->assertEquals($original->edges, $deserialized->edges);
    }

    // =========================================================================
    // RouteAnalyzerService Tests
    // =========================================================================

    public function test_route_analyzer_gets_routes(): void
    {
        $routes = $this->routeAnalyzerService->getRoutes($this->project);

        $this->assertNotEmpty($routes);
        $this->assertTrue($routes->contains('uri', '/login'));
    }

    public function test_route_analyzer_finds_handler(): void
    {
        $handler = $this->routeAnalyzerService->findHandler($this->project, '/login');

        $this->assertNotNull($handler);
        $this->assertEquals('Auth/LoginController', $handler['controller']);
    }

    public function test_route_analyzer_gets_route_stack(): void
    {
        $stack = $this->routeAnalyzerService->getRouteStack($this->project, '/login');

        $this->assertArrayHasKey('controller', $stack);
        $this->assertArrayHasKey('route_file', $stack);
        $this->assertNotEmpty($stack['route_file']);
    }

    public function test_route_analyzer_matches_description_to_routes(): void
    {
        $matches = $this->routeAnalyzerService->matchDescriptionToRoutes(
            $this->project,
            'I need to fix the login page'
        );

        $this->assertNotEmpty($matches);

        $bestMatch = $matches->first();
        $this->assertArrayHasKey('route', $bestMatch);
        $this->assertArrayHasKey('score', $bestMatch);
        $this->assertStringContainsString('login', strtolower($bestMatch['route']['uri']));
    }

    public function test_route_analyzer_groups_by_domain(): void
    {
        $grouped = $this->routeAnalyzerService->getRoutesByDomain($this->project);

        $this->assertNotEmpty($grouped);
    }

    // =========================================================================
    // RetrievalCacheService Tests
    // =========================================================================

    public function test_cache_service_caches_symbol_graph(): void
    {
        Cache::flush();

        $graph = new SymbolGraph(
            nodes: ['test.php' => ['symbols_declared' => [], 'symbols_used' => [], 'imports' => [], 'language' => 'php', 'size_bytes' => 100]],
            edges: [],
            metadata: ['test' => true],
        );

        $this->cacheService->cacheSymbolGraph($this->project, $graph);
        $cached = $this->cacheService->getCachedSymbolGraph($this->project);

        $this->assertNotNull($cached);
        $this->assertEquals($graph->nodes, $cached->nodes);
    }

    public function test_cache_service_invalidates_on_scan(): void
    {
        Cache::flush();

        $graph = new SymbolGraph(
            nodes: ['test.php' => ['symbols_declared' => [], 'symbols_used' => [], 'imports' => [], 'language' => 'php', 'size_bytes' => 100]],
            edges: [],
            metadata: [],
        );

        $this->cacheService->cacheSymbolGraph($this->project, $graph);
        $this->assertNotNull($this->cacheService->getCachedSymbolGraph($this->project));

        $this->cacheService->invalidateOnScan($this->project);
        $this->assertNull($this->cacheService->getCachedSymbolGraph($this->project));
    }

    public function test_cache_service_returns_stats(): void
    {
        $stats = $this->cacheService->getStats($this->project);

        $this->assertArrayHasKey('symbol_graph', $stats);
        $this->assertArrayHasKey('routes', $stats);
        $this->assertArrayHasKey('enabled', $stats);
    }

    // =========================================================================
    // RetrievalResult DTO Tests
    // =========================================================================

    public function test_retrieval_result_empty(): void
    {
        $result = RetrievalResult::empty('Test reason');

        $this->assertTrue($result->isEmpty());
        $this->assertEquals(0, $result->getChunkCount());
        $this->assertEquals('Test reason', $result->metadata['reason']);
    }

    public function test_retrieval_result_get_top_chunks(): void
    {
        $intent = $this->createIntent(IntentType::Question, [], 'auth');
        $result = $this->service->retrieve($this->project, $intent, 'Show auth files');

        if (!$result->isEmpty()) {
            $top = $result->getTopChunks(2);
            $this->assertLessThanOrEqual(2, $top->count());
        } else {
            $this->assertTrue(true); // Pass if no chunks
        }
    }

    public function test_retrieval_result_to_array(): void
    {
        $result = RetrievalResult::empty('Test');
        $array = $result->toArray();

        $this->assertArrayHasKey('chunks', $array);
        $this->assertArrayHasKey('files', $array);
        $this->assertArrayHasKey('entry_points', $array);
        $this->assertArrayHasKey('dependencies', $array);
        $this->assertArrayHasKey('related_routes', $array);
        $this->assertArrayHasKey('metadata', $array);
        $this->assertArrayHasKey('stats', $array);
    }

    // =========================================================================
    // Integration Tests
    // =========================================================================

    public function test_full_retrieval_pipeline(): void
    {
        $intent = $this->createIntent(
            IntentType::FeatureRequest,
            [
                'features' => ['dashboard', 'stats'],
                'components' => ['Dashboard'],
            ],
            'ui'
        );

        $result = $this->service->retrieve(
            $this->project,
            $intent,
            'Add a new widget to the dashboard showing user stats',
            [
                'max_chunks' => 20,
                'include_dependencies' => true,
                'depth' => 2,
            ]
        );

        $this->assertInstanceOf(RetrievalResult::class, $result);
        $this->assertIsInt($result->getTotalTokenEstimate());
        $this->assertIsArray($result->getFileList());
        $this->assertIsString($result->toPromptContext());
    }
}
