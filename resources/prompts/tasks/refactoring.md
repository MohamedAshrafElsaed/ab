# Refactoring Task Template

<task_context>
You are refactoring code in a {{FRAMEWORK}} application to improve quality without changing behavior.

<project_info>
{{PROJECT_INFO}}
</project_info>

<tech_stack>
{{TECH_STACK}}
</tech_stack>

<relevant_code>
{{RELEVANT_FILES}}
</relevant_code>
</task_context>

<user_request>
{{USER_REQUEST}}
</user_request>

<instructions>
Plan and execute refactoring following these principles:

1. **Behavior Preservation**
    - The external behavior must remain IDENTICAL
    - All existing tests must continue to pass
    - No API/interface changes without explicit approval

2. **Refactoring Goals**
    - Improve readability and maintainability
    - Reduce complexity (cyclomatic, cognitive)
    - Follow SOLID principles
    - Apply design patterns where appropriate
    - Remove code smells

3. **Safety Measures**
    - Small, incremental changes
    - Each step should be independently verifiable
    - Add tests before refactoring if coverage is lacking
    - Document any non-obvious changes

4. **Common Refactoring Patterns**
    - Extract Method/Class
    - Replace Conditional with Polymorphism
    - Introduce Parameter Object
    - Replace Magic Numbers with Constants
    - Rename for clarity
    - Remove dead code
      </instructions>

<code_smells>
Watch for these issues:
- Long methods (>20 lines)
- Large classes (>200 lines)
- Long parameter lists (>3 parameters)
- Duplicate code
- Feature envy (method uses another object's data extensively)
- Data clumps (same data groups appearing together)
- Primitive obsession
- Inappropriate intimacy between classes
- Comments explaining complex code (simplify instead)
- Dead code
  </code_smells>

<examples>
<example>
<request>Refactor the UserController - it's doing too much</request>
<analysis>
Current issues:
- 500+ line controller
- Handles user CRUD, profile, settings, notifications
- Contains business logic that should be in services
- Repeated validation logic
- Hard to test
</analysis>
<refactoring_plan>
<summary>Extract controller into single-responsibility controllers with service layer</summary>

<before_after>
Before: One large UserController
After: UserController, ProfileController, SettingsController, NotificationPreferencesController + UserService
</before_after>

<steps>
<step number="1" file="app/Services/UserService.php" action="create">
<description>Extract business logic to UserService</description>
<changes>
Move user creation, update, deletion logic from controller to service.
Service handles validation, events, and database operations.
</changes>
<verification>Existing tests pass with new service</verification>
</step>

<step number="2" file="app/Http/Controllers/UserController.php" action="modify">
<description>Simplify UserController to use service</description>
<changes>
- Inject UserService
- Remove business logic, delegate to service
- Keep only HTTP-specific concerns (request/response)
</changes>
<verification>All user CRUD operations work</verification>
</step>

<step number="3" file="app/Http/Controllers/ProfileController.php" action="create">
<description>Extract profile-related actions</description>
<changes>
- show(): Display profile
- edit(): Profile edit form
- update(): Update profile
</changes>
<verification>Profile pages work correctly</verification>
</step>

<step number="4" file="app/Http/Controllers/SettingsController.php" action="create">
<description>Extract settings-related actions</description>
<changes>
- index(): Settings dashboard
- updatePassword(): Password change
- updatePreferences(): User preferences
</changes>
<verification>Settings pages work correctly</verification>
</step>

<step number="5" file="routes/web.php" action="modify">
<description>Update routes for new controllers</description>
<changes>
Split user routes to use appropriate controllers
</changes>
<verification>All routes resolve correctly</verification>
</step>
</steps>

<metrics>
Before: 1 controller @ 500 lines
After: 4 controllers @ ~80 lines each + 1 service @ 150 lines
Complexity reduction: ~40%
</metrics>
</refactoring_plan>
</example>

<example>
<request>Clean up this query builder chain - it's unreadable</request>
<code>
```php
$users = User::where('status', 'active')->where('role', '!=', 'admin')->where(function($q) {
    $q->where('email_verified_at', '!=', null)->orWhere('phone_verified_at', '!=', null);
})->where('created_at', '>', now()->subDays(30))->whereHas('orders', function($q) {
    $q->where('total', '>', 100)->where('status', 'completed');
})->with(['orders' => function($q) { $q->latest()->limit(5); }, 'profile'])->orderBy('created_at', 'desc')->paginate(20);
```
</code>
<refactoring_plan>
<summary>Extract query into model scopes for readability and reusability</summary>

<steps>
<step number="1" file="app/Models/User.php" action="modify">
<description>Add query scopes to User model</description>
<changes>
```php
// Add these scopes to User model
public function scopeActive(Builder $query): Builder
{
    return $query->where('status', 'active');
}

public function scopeNonAdmin(Builder $query): Builder
{
return $query->where('role', '!=', 'admin');
}

public function scopeVerified(Builder $query): Builder
{
return $query->where(function ($q) {
$q->whereNotNull('email_verified_at')
->orWhereNotNull('phone_verified_at');
});
}

public function scopeRecentlyCreated(Builder $query, int $days = 30): Builder
{
return $query->where('created_at', '>', now()->subDays($days));
}

public function scopeWithSignificantOrders(Builder $query, float $minTotal = 100): Builder
{
return $query->whereHas('orders', function ($q) use ($minTotal) {
$q->where('total', '>', $minTotal)
->where('status', 'completed');
});
}

public function scopeWithRecentOrders(Builder $query, int $limit = 5): Builder
{
return $query->with(['orders' => fn($q) => $q->latest()->limit($limit)]);
}
```
</changes>
<verification>Each scope works independently</verification>
</step>

<step number="2" file="app/Http/Controllers/UserController.php" action="modify">
<description>Use scopes in controller</description>
<changes>
```php
$users = User::query()
    ->active()
    ->nonAdmin()
    ->verified()
    ->recentlyCreated(30)
    ->withSignificantOrders(100)
    ->withRecentOrders(5)
    ->with('profile')
    ->latest()
    ->paginate(20);
```
</changes>
<verification>Same results as original query</verification>
</step>
</steps>

<benefits>
- Each scope is testable independently
- Scopes are reusable across the application
- Main query is self-documenting
- Easy to modify individual conditions
</benefits>
</refactoring_plan>
</example>

<example>
<request>Refactor this service to use dependency injection properly</request>
<code>
```php
class ReportService
{
    public function generateReport($type)
    {
        $db = new DatabaseConnection();
        $mailer = new Mailer();
        $pdf = new PdfGenerator();

        $data = $db->query("SELECT * FROM reports WHERE type = '$type'");
        $report = $pdf->generate($data);
        $mailer->send($report);
        
        return $report;
    }
}
```
</code>
<refactoring_plan>
<summary>Refactor to use constructor injection and interfaces</summary>

<issues>
- Hard-coded dependencies (untestable)
- No interfaces (tight coupling)
- SQL injection vulnerability
- Single method doing too much
</issues>

<steps>
<step number="1" file="app/Contracts/ReportGeneratorInterface.php" action="create">
<description>Define interface for report generation</description>
<changes>
```php
<?php

namespace App\Contracts;

interface ReportGeneratorInterface
{
    public function generate(array $data): string;
}
```
</changes>
</step>

<step number="2" file="app/Contracts/MailerInterface.php" action="create">
<description>Define interface for mailer</description>
<changes>
```php
<?php

namespace App\Contracts;

interface MailerInterface
{
public function send(string $content, array $recipients): void;
}
```
</changes>
</step>

<step number="3" file="app/Services/ReportService.php" action="modify">
<description>Refactor to use dependency injection</description>
<changes>
```php
<?php

namespace App\Services;

use App\Contracts\MailerInterface;
use App\Contracts\ReportGeneratorInterface;
use App\Models\Report;
use App\DTOs\ReportResult;

class ReportService
{
    public function __construct(
        private readonly ReportGeneratorInterface $generator,
        private readonly MailerInterface $mailer,
    ) {}

    public function generateReport(string $type): ReportResult
    {
        $data = Report::where('type', $type)->get()->toArray();
        $content = $this->generator->generate($data);
        
        return new ReportResult(
            content: $content,
            type: $type,
            generatedAt: now(),
        );
    }

    public function generateAndSend(string $type, array $recipients): ReportResult
    {
        $result = $this->generateReport($type);
        $this->mailer->send($result->content, $recipients);
        
        return $result;
    }
}
```
</changes>
<verification>Service works with injected dependencies</verification>
</step>

<step number="4" file="app/Providers/AppServiceProvider.php" action="modify">
<description>Bind interfaces to implementations</description>
<changes>
```php
$this->app->bind(ReportGeneratorInterface::class, PdfReportGenerator::class);
$this->app->bind(MailerInterface::class, SmtpMailer::class);
```
</changes>
</step>
</steps>

<benefits>
- Testable with mocks
- Swappable implementations
- SQL injection fixed
- Single responsibility per method
</benefits>
</refactoring_plan>
</example>
</examples>

{{OUTPUT_FORMAT}}
