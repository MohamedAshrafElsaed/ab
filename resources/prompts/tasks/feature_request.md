# Feature Request Task Template

<task_context>
You are implementing a new feature for a {{FRAMEWORK}} application.

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
Analyze this feature request and create a detailed implementation plan. Consider:

1. **Requirements Analysis**
    - What exactly is the user asking for?
    - What are the acceptance criteria?
    - Are there any implicit requirements?

2. **Architecture Impact**
    - Which layers need modification (models, controllers, views, services)?
    - Are new database tables/columns needed?
    - Does this require new routes or API endpoints?

3. **Existing Code Integration**
    - How does this fit with existing patterns?
    - What existing code can be reused?
    - Are there similar features to reference?

4. **Implementation Steps**
    - Break down into atomic, verifiable steps
    - Order by dependency (what must come first?)
    - Estimate complexity for each step

5. **Testing Strategy**
    - What unit tests are needed?
    - What integration tests are needed?
    - How to manually verify?
      </instructions>

<examples>
<example>
<request>Add password reset functionality via email</request>
<analysis>
This is a multi-component feature requiring:
- New database column for reset tokens
- Mail notification class
- Controller for password reset flow
- Frontend forms for request and reset
- Rate limiting for security
</analysis>
<plan>
<summary>Implement password reset via email with token-based verification</summary>

<steps>
<step number="1" file="database/migrations/xxxx_add_password_reset_token.php" action="create">
<description>Create migration for password reset tokens table</description>
<changes>
- Create password_resets table with email, token, created_at columns
- Add index on email column
</changes>
<verification>Run migration, verify table exists</verification>
</step>

<step number="2" file="app/Mail/PasswordResetMail.php" action="create">
<description>Create mailable for password reset emails</description>
<changes>
- Create Mailable class with reset link
- Include token in URL
- Add email template
</changes>
<verification>Send test email, verify content</verification>
</step>

<step number="3" file="app/Http/Controllers/Auth/PasswordResetController.php" action="create">
<description>Create controller for password reset flow</description>
<changes>
- showRequestForm(): Display email input form
- sendResetEmail(): Generate token, send email
- showResetForm(): Display new password form
- resetPassword(): Validate token, update password
</changes>
<verification>Test each endpoint manually</verification>
</step>

<step number="4" file="routes/web.php" action="modify">
<description>Add routes for password reset</description>
<changes>
Add routes for forgot-password, reset-password endpoints
</changes>
<verification>Run route:list, verify routes exist</verification>
</step>
</steps>

<testing_strategy>
- Unit test: Token generation and validation
- Feature test: Full reset flow
- Security test: Rate limiting, token expiration
  </testing_strategy>

<estimated_complexity>medium</estimated_complexity>
</plan>
</example>

<example>
<request>Add dark mode toggle to the settings page</request>
<analysis>
UI-focused feature requiring:
- User preference storage
- Frontend toggle component
- CSS/Tailwind dark mode classes
- Persistence mechanism
</analysis>
<plan>
<summary>Add user-controlled dark mode with persistent preference</summary>

<steps>
<step number="1" file="database/migrations/xxxx_add_dark_mode_to_users.php" action="create">
<description>Add dark mode preference column to users table</description>
<changes>Add boolean 'prefers_dark_mode' column with default false</changes>
<verification>Run migration, check column exists</verification>
</step>

<step number="2" file="app/Models/User.php" action="modify">
<description>Add dark mode attribute to User model</description>
<changes>Add 'prefers_dark_mode' to fillable array</changes>
<verification>Update user preference via tinker</verification>
</step>

<step number="3" file="resources/js/Components/DarkModeToggle.vue" action="create">
<description>Create dark mode toggle component</description>
<changes>
- Toggle switch UI with sun/moon icons
- Emit change event
- Apply 'dark' class to document root
</changes>
<verification>Component renders and toggles class</verification>
</step>

<step number="4" file="resources/js/Pages/Settings.vue" action="modify">
<description>Add dark mode toggle to settings page</description>
<changes>
- Import DarkModeToggle component
- Add to appearance section
- Wire up preference update
</changes>
<verification>Toggle appears and persists</verification>
</step>
</steps>

<testing_strategy>
- Component test: Toggle emits correct events
- Feature test: Preference saves to database
- Visual test: Dark mode classes apply correctly
  </testing_strategy>

<estimated_complexity>simple</estimated_complexity>
</plan>
</example>

<example>
<request>Implement real-time notifications using WebSockets</request>
<analysis>
Complex feature requiring:
- WebSocket server setup (Laravel Echo, Pusher/Soketi)
- Event broadcasting configuration
- Frontend listener setup
- Notification model and storage
- UI notification component
</analysis>
<plan>
<summary>Implement real-time notification system with WebSocket broadcasting</summary>

<prerequisites>
- Install Laravel Echo and Pusher PHP SDK
- Configure broadcasting in .env
- Set up Pusher/Soketi credentials
</prerequisites>

<steps>
<step number="1" file="config/broadcasting.php" action="modify">
<description>Configure Pusher broadcasting driver</description>
<changes>Update pusher configuration with credentials</changes>
<verification>Broadcast test event successfully</verification>
</step>

<step number="2" file="app/Models/Notification.php" action="create">
<description>Create Notification model</description>
<changes>
- Define notification types enum
- Add relationships to User
- Include read_at timestamp
</changes>
<verification>Create notification via factory</verification>
</step>

<step number="3" file="app/Events/NotificationCreated.php" action="create">
<description>Create broadcastable notification event</description>
<changes>
- Implement ShouldBroadcast
- Define private channel for user
- Include notification data in payload
</changes>
<verification>Event broadcasts to correct channel</verification>
</step>

<step number="4" file="resources/js/composables/useNotifications.js" action="create">
<description>Create notifications composable</description>
<changes>
- Set up Echo listener
- Manage notification state
- Provide mark-as-read functionality
</changes>
<verification>Composable receives broadcast events</verification>
</step>
</steps>

<testing_strategy>
- Unit test: Event serialization
- Integration test: Full broadcast flow
- E2E test: Notification appears in UI
  </testing_strategy>

<estimated_complexity>complex</estimated_complexity>
</plan>
</example>
</examples>

{{OUTPUT_FORMAT}}
