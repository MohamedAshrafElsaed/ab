<?php

namespace Tests\Unit;

use App\DTOs\RetrievalResult;
use App\DTOs\SymbolGraph;
use App\Services\AskAI\DTO\RetrievedChunk;
use PHPUnit\Framework\TestCase;

class RetrievalDTOsTest extends TestCase
{
    // =========================================================================
    // SymbolGraph Tests
    // =========================================================================

    public function test_symbol_graph_empty_factory(): void
    {
        $graph = SymbolGraph::empty();

        $this->assertEmpty($graph->nodes);
        $this->assertEmpty($graph->edges);
        $this->assertTrue($graph->metadata['empty']);
    }

    public function test_symbol_graph_get_stats(): void
    {
        $graph = new SymbolGraph(
            nodes: [
                'A.php' => [
                    'symbols_declared' => [['name' => 'ClassA'], ['name' => 'methodA']],
                    'symbols_used' => [],
                    'imports' => [],
                    'language' => 'php',
                    'size_bytes' => 100,
                ],
                'B.php' => [
                    'symbols_declared' => [['name' => 'ClassB']],
                    'symbols_used' => [],
                    'imports' => [],
                    'language' => 'php',
                    'size_bytes' => 200,
                ],
            ],
            edges: [
                'A.php' => ['B.php' => ['type' => 'imports', 'weight' => 1.0]],
            ],
            metadata: [],
        );

        $stats = $graph->getStats();

        $this->assertEquals(2, $stats['node_count']);
        $this->assertEquals(1, $stats['edge_count']);
        $this->assertEquals(3, $stats['symbol_count']);
    }

    public function test_symbol_graph_get_dependents(): void
    {
        $graph = new SymbolGraph(
            nodes: [
                'A.php' => ['symbols_declared' => [], 'symbols_used' => [], 'imports' => [], 'language' => 'php', 'size_bytes' => 100],
                'B.php' => ['symbols_declared' => [], 'symbols_used' => [], 'imports' => [], 'language' => 'php', 'size_bytes' => 100],
                'C.php' => ['symbols_declared' => [], 'symbols_used' => [], 'imports' => [], 'language' => 'php', 'size_bytes' => 100],
            ],
            edges: [
                'B.php' => ['A.php' => ['type' => 'imports', 'weight' => 1.0]],
                'C.php' => ['A.php' => ['type' => 'extends', 'weight' => 0.9]],
            ],
            metadata: [],
        );

        $dependents = $graph->getDependents('A.php');

        $this->assertCount(2, $dependents);
        $this->assertArrayHasKey('B.php', $dependents);
        $this->assertArrayHasKey('C.php', $dependents);
    }

    public function test_symbol_graph_get_dependencies(): void
    {
        $graph = new SymbolGraph(
            nodes: [
                'A.php' => ['symbols_declared' => [], 'symbols_used' => [], 'imports' => [], 'language' => 'php', 'size_bytes' => 100],
                'B.php' => ['symbols_declared' => [], 'symbols_used' => [], 'imports' => [], 'language' => 'php', 'size_bytes' => 100],
            ],
            edges: [
                'A.php' => [
                    'B.php' => ['type' => 'imports', 'weight' => 1.0],
                ],
            ],
            metadata: [],
        );

        $dependencies = $graph->getDependencies('A.php');

        $this->assertCount(1, $dependencies);
        $this->assertArrayHasKey('B.php', $dependencies);
    }

    public function test_symbol_graph_find_by_symbol(): void
    {
        $graph = new SymbolGraph(
            nodes: [
                'User.php' => [
                    'symbols_declared' => [['name' => 'User', 'type' => 'class']],
                    'symbols_used' => [],
                    'imports' => [],
                    'language' => 'php',
                    'size_bytes' => 500,
                ],
                'Post.php' => [
                    'symbols_declared' => [['name' => 'Post', 'type' => 'class']],
                    'symbols_used' => [],
                    'imports' => [],
                    'language' => 'php',
                    'size_bytes' => 400,
                ],
            ],
            edges: [],
            metadata: [],
        );

        $files = $graph->findBySymbol('User');

        $this->assertCount(1, $files);
        $this->assertEquals('User.php', $files[0]);
    }

    public function test_symbol_graph_has_file(): void
    {
        $graph = new SymbolGraph(
            nodes: ['A.php' => ['symbols_declared' => [], 'symbols_used' => [], 'imports' => [], 'language' => 'php', 'size_bytes' => 100]],
            edges: [],
            metadata: [],
        );

        $this->assertTrue($graph->hasFile('A.php'));
        $this->assertFalse($graph->hasFile('B.php'));
    }

    public function test_symbol_graph_find_path_same_node(): void
    {
        $graph = new SymbolGraph(
            nodes: ['A.php' => ['symbols_declared' => [], 'symbols_used' => [], 'imports' => [], 'language' => 'php', 'size_bytes' => 100]],
            edges: [],
            metadata: [],
        );

        $path = $graph->findPathBetween('A.php', 'A.php');

        $this->assertEquals(['A.php'], $path);
    }

    public function test_symbol_graph_find_path_no_path(): void
    {
        $graph = new SymbolGraph(
            nodes: [
                'A.php' => ['symbols_declared' => [], 'symbols_used' => [], 'imports' => [], 'language' => 'php', 'size_bytes' => 100],
                'B.php' => ['symbols_declared' => [], 'symbols_used' => [], 'imports' => [], 'language' => 'php', 'size_bytes' => 100],
            ],
            edges: [], // No edges between A and B
            metadata: [],
        );

        $path = $graph->findPathBetween('A.php', 'B.php');

        $this->assertNull($path);
    }

    // =========================================================================
    // RetrievalResult Tests
    // =========================================================================

    public function test_retrieval_result_empty_factory(): void
    {
        $result = RetrievalResult::empty('No context');

        $this->assertTrue($result->isEmpty());
        $this->assertEquals(0, $result->getChunkCount());
        $this->assertEquals(0, $result->getFileCount());
        $this->assertEquals('No context', $result->metadata['reason']);
    }

    public function test_retrieval_result_get_file_list(): void
    {
        $chunks = collect([
            new RetrievedChunk(
                chunkId: 'c1',
                path: 'app/Models/User.php',
                startLine: 1,
                endLine: 50,
                sha1: 'abc',
                content: 'content',
                relevanceScore: 0.9,
            ),
            new RetrievedChunk(
                chunkId: 'c2',
                path: 'app/Models/Post.php',
                startLine: 1,
                endLine: 30,
                sha1: 'def',
                content: 'content',
                relevanceScore: 0.8,
            ),
            new RetrievedChunk(
                chunkId: 'c3',
                path: 'app/Models/User.php',
                startLine: 51,
                endLine: 100,
                sha1: 'ghi',
                content: 'content',
                relevanceScore: 0.7,
            ),
        ]);

        $result = new RetrievalResult(
            chunks: $chunks,
            files: collect([
                ['path' => 'app/Models/User.php', 'language' => 'php', 'relevance' => 0.9],
                ['path' => 'app/Models/Post.php', 'language' => 'php', 'relevance' => 0.8],
            ]),
            entryPoints: [],
            dependencies: [],
            relatedRoutes: [],
            metadata: [],
        );

        $fileList = $result->getFileList();

        $this->assertCount(2, $fileList);
        $this->assertContains('app/Models/User.php', $fileList);
        $this->assertContains('app/Models/Post.php', $fileList);
    }

    public function test_retrieval_result_token_estimate(): void
    {
        $content = str_repeat('a', 400); // 400 chars

        $result = new RetrievalResult(
            chunks: collect([
                new RetrievedChunk(
                    chunkId: 'c1',
                    path: 'test.php',
                    startLine: 1,
                    endLine: 10,
                    sha1: 'abc',
                    content: $content,
                    relevanceScore: 0.9,
                ),
            ]),
            files: collect(),
            entryPoints: [],
            dependencies: [],
            relatedRoutes: [],
            metadata: [],
        );

        $estimate = $result->getTotalTokenEstimate();

        // Default 0.25 tokens per char: 400 * 0.25 = 100
        $this->assertEquals(100, $estimate);
    }

    public function test_retrieval_result_get_top_chunks(): void
    {
        $chunks = collect([
            new RetrievedChunk('c1', 'a.php', 1, 10, 'a', 'content', 0.5),
            new RetrievedChunk('c2', 'b.php', 1, 10, 'b', 'content', 0.9),
            new RetrievedChunk('c3', 'c.php', 1, 10, 'c', 'content', 0.7),
        ]);

        $result = new RetrievalResult(
            chunks: $chunks,
            files: collect(),
            entryPoints: [],
            dependencies: [],
            relatedRoutes: [],
            metadata: [],
        );

        $top = $result->getTopChunks(2);

        $this->assertCount(2, $top);
        $this->assertEquals('c2', $top->first()->chunkId); // Highest score
        $this->assertEquals('c3', $top->last()->chunkId);  // Second highest
    }

    public function test_retrieval_result_to_array(): void
    {
        $result = new RetrievalResult(
            chunks: collect([
                new RetrievedChunk('c1', 'a.php', 1, 10, 'abc', 'content', 0.9),
            ]),
            files: collect([['path' => 'a.php', 'language' => 'php', 'relevance' => 0.9]]),
            entryPoints: ['a.php'],
            dependencies: [
                'b.php' => ['path' => 'b.php', 'relationship' => 'imports', 'depth' => 1],
            ],
            relatedRoutes: [
                ['uri' => '/users', 'method' => 'GET', 'controller' => 'UserController'],
            ],
            metadata: ['test' => true],
        );

        $array = $result->toArray();

        $this->assertArrayHasKey('chunks', $array);
        $this->assertArrayHasKey('files', $array);
        $this->assertArrayHasKey('entry_points', $array);
        $this->assertArrayHasKey('dependencies', $array);
        $this->assertArrayHasKey('related_routes', $array);
        $this->assertArrayHasKey('metadata', $array);
        $this->assertArrayHasKey('stats', $array);

        $this->assertEquals(1, $array['stats']['chunk_count']);
        $this->assertEquals(1, $array['stats']['file_count']);
    }

    public function test_retrieval_result_to_prompt_context_empty(): void
    {
        $result = RetrievalResult::empty();

        $context = $result->toPromptContext();

        $this->assertStringContainsString('No relevant code context', $context);
    }

    public function test_retrieval_result_to_prompt_context_with_chunks(): void
    {
        $result = new RetrievalResult(
            chunks: collect([
                new RetrievedChunk(
                    chunkId: 'c1',
                    path: 'app/Models/User.php',
                    startLine: 1,
                    endLine: 20,
                    sha1: 'abc',
                    content: '<?php class User extends Model {}',
                    relevanceScore: 0.95,
                    symbolsDeclared: [['name' => 'User', 'type' => 'class']],
                    language: 'php',
                ),
            ]),
            files: collect([['path' => 'app/Models/User.php', 'language' => 'php', 'relevance' => 0.95]]),
            entryPoints: ['app/Models/User.php'],
            dependencies: [],
            relatedRoutes: [],
            metadata: [],
        );

        $context = $result->toPromptContext();

        $this->assertStringContainsString('<retrieved_context>', $context);
        $this->assertStringContainsString('</retrieved_context>', $context);
        $this->assertStringContainsString('app/Models/User.php', $context);
        $this->assertStringContainsString('role="entry_point"', $context);
        $this->assertStringContainsString('Declares: User', $context);
        $this->assertStringContainsString('```php', $context);
    }
}
