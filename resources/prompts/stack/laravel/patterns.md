# Laravel Patterns

<laravel_conventions>

## Project Structure
```
app/
├── Console/Commands/     # Artisan commands
├── DTOs/                 # Data Transfer Objects
├── Enums/               # PHP 8.1+ enums
├── Events/              # Event classes
├── Exceptions/          # Custom exceptions
├── Http/
│   ├── Controllers/     # Request handlers
│   ├── Middleware/      # Request/response filters
│   └── Requests/        # Form request validation
├── Jobs/                # Queue jobs
├── Listeners/           # Event listeners
├── Mail/                # Mailable classes
├── Models/              # Eloquent models
├── Policies/            # Authorization policies
├── Providers/           # Service providers
└── Services/            # Business logic
```

## Coding Standards

### Controllers
- Keep controllers thin (delegate to services)
- Use resource controllers for CRUD
- Type-hint dependencies in constructor
- Return consistent response types

```php
class ProjectController extends Controller
{
    public function __construct(
        private readonly ProjectService $projectService,
    ) {}

    public function store(StoreProjectRequest $request): RedirectResponse
    {
        $project = $this->projectService->create($request->validated());
        
        return redirect()
            ->route('projects.show', $project)
            ->with('success', 'Project created successfully.');
    }
}
```

### Services
- Single responsibility
- Constructor dependency injection
- Return DTOs or Models, not arrays
- Throw exceptions for errors

```php
class ProjectService
{
    public function __construct(
        private readonly GitService $git,
        private readonly ScannerService $scanner,
    ) {}

    public function create(array $data): Project
    {
        return DB::transaction(function () use ($data) {
            $project = Project::create($data);
            $this->git->clone($project);
            return $project;
        });
    }
}
```

### Form Requests
- Validate all user input
- Use specific rules
- Custom messages when helpful

```php
class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Or check user permissions
    }

    public function rules(): array
    {
        return [
            'repo_url' => ['required', 'url', 'starts_with:https://github.com/'],
            'name' => ['required', 'string', 'max:255'],
            'branch' => ['sometimes', 'string', 'max:100'],
        ];
    }
}
```

### DTOs
- Use readonly properties
- Static factory methods
- Validate in constructor

```php
readonly class ProjectData
{
    public function __construct(
        public string $name,
        public string $repoUrl,
        public ?string $branch = 'main',
    ) {}

    public static function fromRequest(StoreProjectRequest $request): self
    {
        return new self(
            name: $request->validated('name'),
            repoUrl: $request->validated('repo_url'),
            branch: $request->validated('branch', 'main'),
        );
    }
}
```

## Best Practices

### Database
- Always use migrations for schema changes
- Use factories for test data
- Prefer Eloquent over raw queries
- Use eager loading to prevent N+1

### Testing
- Feature tests for HTTP endpoints
- Unit tests for services and DTOs
- Use RefreshDatabase trait
- Test both happy path and edge cases

### Security
- Use Form Requests for validation
- Authorize actions with Policies
- Escape output in Blade
- Use parameterized queries

### Performance
- Cache expensive queries
- Queue background jobs
- Use chunking for large datasets
- Index frequently queried columns

</laravel_conventions>
