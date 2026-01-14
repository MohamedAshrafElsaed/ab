# Test Writing Task Template

<task_context>
You are writing tests for a {{FRAMEWORK}} application.

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
Create comprehensive tests following these guidelines:

1. **Test Coverage Analysis**
    - Identify all public methods that need testing
    - Find edge cases and boundary conditions
    - Consider error scenarios and exception handling

2. **Test Organization**
    - Group related tests logically
    - Use descriptive test names that explain the scenario
    - Follow existing test patterns in the project

3. **Test Types**
    - Unit tests: Test isolated logic, mock dependencies
    - Feature tests: Test full request/response cycles
    - Integration tests: Test component interactions

4. **Test Quality**
    - Each test should verify ONE behavior
    - Tests should be independent (no shared state)
    - Use factories for test data
    - Assert specific outcomes, not implementation details
      </instructions>

<testing_framework_patterns>
<phpunit>
- Use PHPUnit assertions
- Extend TestCase or Feature/TestCase
- Use setUp() for common setup
- Use data providers for multiple inputs
  </phpunit>

<pest>
- Use describe/it blocks for organization
- Use expect() for assertions
- Use beforeEach() for setup
- Use dataset() for data providers
</pest>

<vitest>
- Use describe/it blocks
- Use expect() assertions
- Use beforeEach/afterEach hooks
- Mock with vi.mock()
</vitest>
</testing_framework_patterns>

<examples>
<example>
<request>Write tests for the UserService class</request>
<analysis>
Testing UserService which handles:
- User registration
- Profile updates
- Password changes
- Account deletion
</analysis>
<tests>
<file path="tests/Unit/Services/UserServiceTest.php" action="create">
```php
<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserServiceTest extends TestCase
{
use RefreshDatabase;

    private UserService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(UserService::class);
    }

    public function test_creates_user_with_valid_data(): void
    {
        $data = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'securepassword123',
        ];

        $user = $this->service->create($data);

        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals('John Doe', $user->name);
        $this->assertEquals('john@example.com', $user->email);
        $this->assertTrue(Hash::check('securepassword123', $user->password));
    }

    public function test_throws_exception_for_duplicate_email(): void
    {
        User::factory()->create(['email' => 'existing@example.com']);

        $this->expectException(\App\Exceptions\DuplicateEmailException::class);

        $this->service->create([
            'name' => 'Another User',
            'email' => 'existing@example.com',
            'password' => 'password123',
        ]);
    }

    public function test_updates_user_profile(): void
    {
        $user = User::factory()->create(['name' => 'Old Name']);

        $updated = $this->service->updateProfile($user, ['name' => 'New Name']);

        $this->assertEquals('New Name', $updated->name);
        $this->assertEquals('New Name', $user->fresh()->name);
    }

    public function test_changes_password_with_correct_current_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('oldpassword'),
        ]);

        $result = $this->service->changePassword($user, 'oldpassword', 'newpassword');

        $this->assertTrue($result);
        $this->assertTrue(Hash::check('newpassword', $user->fresh()->password));
    }

    public function test_rejects_password_change_with_wrong_current_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('oldpassword'),
        ]);

        $this->expectException(\App\Exceptions\InvalidPasswordException::class);

        $this->service->changePassword($user, 'wrongpassword', 'newpassword');
    }

    public function test_soft_deletes_user_account(): void
    {
        $user = User::factory()->create();

        $this->service->deleteAccount($user);

        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }
}
```
</file>
</tests>
</example>

<example>
<request>Write feature tests for the authentication endpoints</request>
<analysis>
Testing authentication flow:
- Login with valid credentials
- Login with invalid credentials
- Logout functionality
- Rate limiting on login attempts
</analysis>
<tests>
<file path="tests/Feature/AuthenticationTest.php" action="create">
```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        $response = $this->post('/login', [
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_user_cannot_login_with_invalid_password(): void
    {
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        $response = $this->post('/login', [
            'email' => 'test@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_user_cannot_login_with_nonexistent_email(): void
    {
        $response = $this->post('/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_authenticated_user_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $response->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_login_is_rate_limited(): void
    {
        User::factory()->create(['email' => 'test@example.com']);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', [
                'email' => 'test@example.com',
                'password' => 'wrongpassword',
            ]);
        }

        $response = $this->post('/login', [
            'email' => 'test@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(429);
    }
}
```
</file>
</tests>
</example>

<example>
<request>Write Vue component tests for the DataTable component</request>
<analysis>
Testing DataTable component:
- Renders table with data
- Handles sorting
- Handles pagination
- Emits events correctly
</analysis>
<tests>
<file path="resources/js/Components/__tests__/DataTable.spec.js" action="create">
```javascript
import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import DataTable from '../DataTable.vue'

describe('DataTable', () => {
const defaultProps = {
columns: [
{ key: 'name', label: 'Name', sortable: true },
{ key: 'email', label: 'Email', sortable: true },
{ key: 'created_at', label: 'Created', sortable: false },
],
data: [
{ id: 1, name: 'Alice', email: 'alice@example.com', created_at: '2024-01-01' },
{ id: 2, name: 'Bob', email: 'bob@example.com', created_at: '2024-01-02' },
],
}

    it('renders table headers correctly', () => {
        const wrapper = mount(DataTable, { props: defaultProps })

        const headers = wrapper.findAll('th')
        expect(headers).toHaveLength(3)
        expect(headers[0].text()).toBe('Name')
        expect(headers[1].text()).toBe('Email')
        expect(headers[2].text()).toBe('Created')
    })

    it('renders table rows with data', () => {
        const wrapper = mount(DataTable, { props: defaultProps })

        const rows = wrapper.findAll('tbody tr')
        expect(rows).toHaveLength(2)
        expect(rows[0].text()).toContain('Alice')
        expect(rows[1].text()).toContain('Bob')
    })

    it('emits sort event when clicking sortable column header', async () => {
        const wrapper = mount(DataTable, { props: defaultProps })

        await wrapper.find('th:first-child').trigger('click')

        expect(wrapper.emitted('sort')).toBeTruthy()
        expect(wrapper.emitted('sort')[0]).toEqual([{ column: 'name', direction: 'asc' }])
    })

    it('does not emit sort event for non-sortable columns', async () => {
        const wrapper = mount(DataTable, { props: defaultProps })

        await wrapper.find('th:last-child').trigger('click')

        expect(wrapper.emitted('sort')).toBeFalsy()
    })

    it('shows empty state when no data provided', () => {
        const wrapper = mount(DataTable, {
            props: { ...defaultProps, data: [] },
        })

        expect(wrapper.text()).toContain('No data available')
    })

    it('emits row-click event when clicking a row', async () => {
        const wrapper = mount(DataTable, { props: defaultProps })

        await wrapper.find('tbody tr:first-child').trigger('click')

        expect(wrapper.emitted('row-click')).toBeTruthy()
        expect(wrapper.emitted('row-click')[0][0]).toEqual(defaultProps.data[0])
    })
})
```
</file>
</tests>
</example>
</examples>

{{OUTPUT_FORMAT}}
