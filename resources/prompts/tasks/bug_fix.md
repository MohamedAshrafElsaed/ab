# Bug Fix Task Template

<task_context>
You are diagnosing and fixing a bug in a {{FRAMEWORK}} application.

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
Analyze this bug report and create a fix plan. Follow this process:

1. **Bug Identification**
    - What is the exact symptom?
    - What is the expected vs actual behavior?
    - When does the bug occur? (conditions, triggers)

2. **Root Cause Analysis**
    - Trace the code path that leads to the bug
    - Identify the exact line(s) causing the issue
    - Understand why the bug exists (logic error, edge case, etc.)

3. **Impact Assessment**
    - What functionality is affected?
    - Are there related areas that might have similar issues?
    - What is the severity? (critical, high, medium, low)

4. **Fix Strategy**
    - What is the minimal change to fix the bug?
    - Are there multiple ways to fix it? Which is best?
    - Does the fix risk breaking anything else?

5. **Regression Prevention**
    - What test would have caught this bug?
    - How do we ensure it doesn't happen again?
      </instructions>

<examples>
<example>
<request>The login form crashes when email contains a plus sign</request>
<diagnosis>
<symptom>500 error when submitting login with email like "user+test@example.com"</symptom>
<expected>Login should accept valid emails with plus signs</expected>
<root_cause>Email validation regex doesn't account for plus sign character</root_cause>
<location>app/Http/Requests/LoginRequest.php:15</location>
</diagnosis>
<fix>
<summary>Update email validation to accept plus signs</summary>

<steps>
<step number="1" file="app/Http/Requests/LoginRequest.php" action="modify">
<description>Fix email validation rule</description>
<changes>
Change: 'email' => 'required|regex:/^[a-zA-Z0-9._%+-]+@/'
To: 'email' => 'required|email:rfc,dns'
Use Laravel's built-in email validation which properly handles plus signs
</changes>
<verification>Submit form with plus sign email</verification>
</step>

<step number="2" file="tests/Feature/LoginTest.php" action="modify">
<description>Add regression test</description>
<changes>
Add test case: test_login_accepts_email_with_plus_sign()
- Create user with plus sign email
- Submit login form
- Assert successful authentication
</changes>
<verification>Run test suite</verification>
</step>
</steps>

<testing>
- Test: user+test@example.com
- Test: user.name+tag@example.com
- Test: regular@example.com (ensure not broken)
</testing>

<severity>medium</severity>
</fix>
</example>

<example>
<request>Users can access other users' private documents by changing the URL ID</request>
<diagnosis>
<symptom>IDOR vulnerability - changing /documents/123 to /documents/456 shows other user's document</symptom>
<expected>Users should only see their own documents</expected>
<root_cause>DocumentController::show() doesn't verify ownership</root_cause>
<location>app/Http/Controllers/DocumentController.php:45</location>
<severity>CRITICAL - Security vulnerability</severity>
</diagnosis>
<fix>
<summary>Add authorization check to document access</summary>

<steps>
<step number="1" file="app/Policies/DocumentPolicy.php" action="create">
<description>Create document policy for authorization</description>
<changes>
```php
class DocumentPolicy
{
    public function view(User $user, Document $document): bool
    {
        return $user->id === $document->user_id;
    }
}
```
</changes>
<verification>Policy registered in AuthServiceProvider</verification>
</step>

<step number="2" file="app/Http/Controllers/DocumentController.php" action="modify">
<description>Add authorization to show method</description>
<changes>
Add at start of show(): $this->authorize('view', $document);
</changes>
<verification>Try accessing another user's document - expect 403</verification>
</step>

<step number="3" file="tests/Feature/DocumentSecurityTest.php" action="create">
<description>Add security regression tests</description>
<changes>
- test_user_cannot_view_other_users_document()
- test_user_can_view_own_document()
- test_unauthenticated_user_cannot_view_documents()
</changes>
<verification>All security tests pass</verification>
</step>
</steps>

<additional_audit>
Review all controllers for similar IDOR vulnerabilities:
- ProjectController
- FileController
- CommentController
  </additional_audit>

<severity>critical</severity>
</fix>
</example>

<example>
<request>Dashboard loads slowly, takes 10+ seconds</request>
<diagnosis>
<symptom>Dashboard page load time exceeds 10 seconds</symptom>
<expected>Page should load in under 2 seconds</expected>
<root_cause>N+1 query problem - loading user with projects, each project loading files separately</root_cause>
<location>app/Http/Controllers/DashboardController.php:20</location>
</diagnosis>
<fix>
<summary>Fix N+1 query with eager loading</summary>

<steps>
<step number="1" file="app/Http/Controllers/DashboardController.php" action="modify">
<description>Add eager loading to dashboard query</description>
<changes>
Change: $projects = auth()->user()->projects;
To: $projects = auth()->user()->projects()->with(['files', 'latestScan'])->get();
</changes>
<verification>Check query count with Laravel Debugbar</verification>
</step>

<step number="2" file="app/Models/Project.php" action="modify">
<description>Add latestScan relationship if missing</description>
<changes>
```php
public function latestScan(): HasOne
{
    return $this->hasOne(ProjectScan::class)->latestOfMany();
}
```
</changes>
<verification>Relationship returns correct scan</verification>
</step>

<step number="3" file="tests/Feature/DashboardPerformanceTest.php" action="create">
<description>Add performance regression test</description>
<changes>
Assert query count is under 10 for dashboard with 20 projects
</changes>
<verification>Test passes with acceptable query count</verification>
</step>
</steps>

<metrics>
Before: ~200 queries, 10+ second load
After: ~5 queries, <1 second load
</metrics>

<severity>high</severity>
</fix>
</example>
</examples>

{{OUTPUT_FORMAT}}
