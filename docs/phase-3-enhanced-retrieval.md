# Phase 3: Enhanced Knowledge Retrieval System

## Overview

Phase 3 provides intelligent context retrieval for AI agents, building on the knowledge base created by the scanner. It integrates with Phase 1 (Intent Classification) and Phase 2 (Prompt Templates) to deliver highly relevant code context.

## Architecture

```
User Request
    → IntentAnalyzerService (Phase 1)
    → ContextRetrievalService (Phase 3)
        → SymbolGraphService (dependency analysis)
        → RouteAnalyzerService (route-based lookup)
        → Scoring & Ranking
    → PromptComposer (Phase 2)
    → Claude API
```

## Components

### 1. ContextRetrievalService

The main orchestrator for retrieving relevant code context.

```php
use App\Services\AI\ContextRetrievalService;

$result = $contextRetrieval->retrieve(
    project: $project,
    intent: $intentAnalysis,
    userMessage: "Add password reset feature",
    options: [
        'max_chunks' => 50,
        'token_budget' => 100000,
        'include_dependencies' => true,
        'depth' => 2,
    ]
);

// Result contains:
// - $result->chunks: Ranked relevant code chunks
// - $result->files: Unique files involved
// - $result->entryPoints: Main files to focus on
// - $result->dependencies: Supporting files
// - $result->relatedRoutes: Routes that might be affected

// Format for prompt injection
$promptContext = $result->toPromptContext();

// Apply token budget limiting
$limited = $result->limitToTokenBudget(50000);
```

### 2. SymbolGraphService

Builds and queries the dependency graph.

```php
use App\Services\AI\SymbolGraphService;

// Build graph for project
$graph = $symbolGraph->buildGraph($project);

// Find files that depend on User.php
$dependents = $symbolGraph->findDependents($project, 'app/Models/User.php');

// Find all dependencies of a controller
$dependencies = $symbolGraph->findDependencies($project, 'app/Http/Controllers/UserController.php');

// Find files that declare or use a symbol
$results = $symbolGraph->findBySymbol($project, 'UserService');
// Returns: ['declares' => [...], 'uses' => [...]]

// Get dependency tree
$tree = $symbolGraph->getDependencyTree($project, 'app/Http/Controllers/UserController.php', 3);
```

### 3. RouteAnalyzerService

Understands web routes and their handlers.

```php
use App\Services\AI\RouteAnalyzerService;

// Get all routes
$routes = $routeAnalyzer->getRoutes($project);

// Find handler for a route
$handler = $routeAnalyzer->findHandler($project, '/users/{id}');
// Returns: ['controller' => 'UserController', 'action' => 'show', 'file' => '...']

// Get full route stack (controller, request, resource, model, views)
$stack = $routeAnalyzer->getRouteStack($project, '/login');

// Match description to routes
$matches = $routeAnalyzer->matchDescriptionToRoutes($project, 'fix the login page');
```

### 4. RetrievalCacheService

Caching layer for performance.

```php
use App\Services\AI\RetrievalCacheService;

// Cache is automatically managed, but you can:

// Warm up cache after scan
$cacheService->warmUp($project, $symbolGraphService, $routeAnalyzerService);

// Invalidate on new scan
$cacheService->invalidateOnScan($project);

// Check cache status
$stats = $cacheService->getStats($project);
```

## DTOs

### RetrievalResult

```php
readonly class RetrievalResult
{
    public Collection $chunks;        // Ranked relevant chunks
    public Collection $files;         // Unique files involved
    public array $entryPoints;        // Main files to focus on
    public array $dependencies;       // Supporting files
    public array $relatedRoutes;      // Routes that might be affected
    public array $metadata;           // Stats, scores, timing
    
    // Methods
    public function toPromptContext(): string;
    public function getFileList(): array;
    public function getTotalTokenEstimate(): int;
    public function limitToTokenBudget(int $budget): self;
    public function getTopChunks(int $n): Collection;
}
```

### SymbolGraph

```php
readonly class SymbolGraph
{
    public array $nodes;  // file_path => symbol info
    public array $edges;  // from_file => [to_file => relationship]
    
    // Methods
    public function getRelated(string $filePath, int $depth = 1): array;
    public function findPathBetween(string $from, string $to): ?array;
    public function getCluster(string $filePath, int $maxSize = 20): array;
    public function getDependents(string $filePath): array;
    public function getDependencies(string $filePath): array;
    public function findBySymbol(string $symbolName): array;
}
```

## Scoring System

Chunks are scored based on multiple factors:

| Factor | Weight | Description |
|--------|--------|-------------|
| Keyword Match | 25% | Keywords from intent found in path/content |
| File Type Relevance | 20% | File type matches intent (Controller for routes, etc.) |
| Domain Match | 20% | File is in the relevant domain (auth, users, etc.) |
| Dependency Proximity | 15% | Distance from entry points |
| Route Relevance | 10% | Related to detected routes |
| Symbol Match | 10% | Declares or uses mentioned symbols |

Boost multipliers:
- Exact path match: 10x
- Symbol declared: 8x
- Entry point: 7x
- Route handler: 9x
- Import match: 6x

## Configuration

```php
// config/retrieval.php

return [
    'max_chunks' => 50,
    'max_token_budget' => 100000,
    'default_dependency_depth' => 2,
    
    'scoring' => [
        'weights' => [
            'keyword_match' => 0.25,
            'file_type_relevance' => 0.20,
            // ...
        ],
        'boost' => [
            'exact_path_match' => 10.0,
            'symbol_declared' => 8.0,
            // ...
        ],
    ],
    
    'domain_paths' => [
        'auth' => ['app/Http/Controllers/Auth', 'app/Models/User.php', ...],
        'users' => ['app/Models/User.php', 'app/Http/Controllers/User', ...],
        // ...
    ],
    
    'cache' => [
        'enabled' => true,
        'ttl' => [
            'symbol_graph' => 3600,
            'routes' => 1800,
        ],
    ],
];
```

## CLI Commands

```bash
# Warm up cache for all projects
php artisan retrieval:warmup --all

# Warm up specific project
php artisan retrieval:warmup --project=abc123

# Show cache statistics
php artisan retrieval:warmup --stats
```

## Events

The system automatically invalidates and warms up cache when scans complete:

```php
// Dispatch after scan
event(new ProjectScanCompleted($project, $scanId, $stats));

// Listener handles cache invalidation
// App\Listeners\InvalidateRetrievalCacheOnScan
```

## Integration Example

Full pipeline integration:

```php
use App\Services\AI\EnhancedAskService;

$response = $enhancedAsk->ask(
    project: $project,
    question: "Add a password reset email feature",
    conversationHistory: $history,
    conversationId: $convId,
);

if ($response->isSuccess()) {
    // $response->answer - formatted answer with citations
    // $response->citations - audit log entries
    // $response->intentAnalysis - Phase 1 output
    // $response->retrievalResult - Phase 3 output
}

if ($response->needsClarification()) {
    // $response->clarificationQuestions
}
```

## Testing

```bash
# Run Phase 3 tests
php artisan test tests/Feature/ContextRetrievalServiceTest.php
php artisan test tests/Unit/RetrievalDTOsTest.php
```

## Performance Considerations

1. **Caching**: Symbol graphs and routes are cached. Warm up after scans.
2. **Token Budget**: Always apply token budget to avoid context overflow.
3. **Dependency Depth**: Limit to 2-3 levels for reasonable response times.
4. **Chunk Selection**: Diversity algorithm ensures coverage across files.

## Future Enhancements

- Embedding-based semantic search
- Cross-project knowledge sharing
- Incremental graph updates
- ML-based relevance scoring
