# Laravel Controller Patterns

<controller_conventions>

## Controller Types

### Resource Controller (CRUD)
```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Models\Project;
use App\Services\ProjectService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function __construct(
        private readonly ProjectService $projectService,
    ) {}

    public function index(): Response
    {
        $projects = Project::query()
            ->forUser(auth()->user())
            ->with('latestScan')
            ->latest()
            ->paginate(15);

        return Inertia::render('Projects/Index', [
            'projects' => $projects,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Projects/Create');
    }

    public function store(StoreProjectRequest $request): RedirectResponse
    {
        $project = $this->projectService->create(
            $request->validated(),
            $request->user()
        );

        return redirect()
            ->route('projects.show', $project)
            ->with('success', 'Project created successfully.');
    }

    public function show(Project $project): Response
    {
        $this->authorize('view', $project);

        return Inertia::render('Projects/Show', [
            'project' => $project->load(['files', 'latestScan']),
        ]);
    }

    public function edit(Project $project): Response
    {
        $this->authorize('update', $project);

        return Inertia::render('Projects/Edit', [
            'project' => $project,
        ]);
    }

    public function update(UpdateProjectRequest $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $this->projectService->update($project, $request->validated());

        return redirect()
            ->route('projects.show', $project)
            ->with('success', 'Project updated successfully.');
    }

    public function destroy(Project $project): RedirectResponse
    {
        $this->authorize('delete', $project);

        $this->projectService->delete($project);

        return redirect()
            ->route('projects.index')
            ->with('success', 'Project deleted successfully.');
    }
}
```

### API Controller
```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Http\Resources\ProjectCollection;
use App\Models\Project;
use App\Services\ProjectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ProjectController extends Controller
{
    public function __construct(
        private readonly ProjectService $projectService,
    ) {}

    public function index(): ProjectCollection
    {
        $projects = Project::query()
            ->forUser(auth()->user())
            ->paginate(20);

        return new ProjectCollection($projects);
    }

    public function store(StoreProjectRequest $request): JsonResponse
    {
        $project = $this->projectService->create(
            $request->validated(),
            $request->user()
        );

        return response()->json([
            'data' => new ProjectResource($project),
            'message' => 'Project created successfully.',
        ], Response::HTTP_CREATED);
    }

    public function show(Project $project): ProjectResource
    {
        $this->authorize('view', $project);

        return new ProjectResource($project->load('files'));
    }

    public function update(UpdateProjectRequest $request, Project $project): ProjectResource
    {
        $this->authorize('update', $project);

        $this->projectService->update($project, $request->validated());

        return new ProjectResource($project->fresh());
    }

    public function destroy(Project $project): JsonResponse
    {
        $this->authorize('delete', $project);

        $this->projectService->delete($project);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
```

### Invokable Controller (Single Action)
```php
<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Jobs\ScanProjectJob;
use Illuminate\Http\RedirectResponse;

class ScanProjectController extends Controller
{
    public function __invoke(Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        ScanProjectJob::dispatch($project);

        return redirect()
            ->back()
            ->with('success', 'Project scan started.');
    }
}
```

## Best Practices

### Keep Controllers Thin
```php
// Bad - too much logic in controller
public function store(Request $request)
{
    $validated = $request->validate([...]);
    $project = new Project($validated);
    $project->user_id = auth()->id();
    $project->status = 'pending';
    $project->save();
    
    // Clone repo
    $git = new GitService();
    $git->clone($project->repo_url, $project->repo_path);
    
    // Scan files
    $scanner = new Scanner();
    $files = $scanner->scan($project->repo_path);
    
    // ... more logic
}

// Good - delegate to service
public function store(StoreProjectRequest $request)
{
    $project = $this->projectService->create(
        $request->validated(),
        $request->user()
    );

    return redirect()->route('projects.show', $project);
}
```

### Use Form Requests
```php
// Always validate via Form Request, not inline
public function store(StoreProjectRequest $request)
{
    // $request->validated() is already validated
}
```

### Consistent Response Types
```php
// Web controllers return: Response, RedirectResponse, View
// API controllers return: JsonResponse, Resource, Collection
```

### Authorization in Controllers
```php
// Use authorize() method
public function show(Project $project)
{
    $this->authorize('view', $project);
    // ...
}

// Or middleware
public function __construct()
{
    $this->middleware('can:manage,project')->only(['edit', 'update', 'destroy']);
}
```

### Route Model Binding
```php
// Automatic - uses {project} parameter
public function show(Project $project) { }

// Custom key
public function show(Project $project)
{
    // In route: Route::get('/projects/{project:slug}', ...);
}
```

</controller_conventions>
