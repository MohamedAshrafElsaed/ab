# Testing Documentation

This document provides an overview of the test suite and commands for running tests.

## Test Structure

The test suite is organized into two main categories:

### Unit Tests (`tests/Unit/`)

Unit tests focus on testing individual components in isolation.

| Test File | Description |
|-----------|-------------|
| `tests/Unit/Models/ProjectTest.php` | Tests for the Project model methods and relationships |
| `tests/Unit/Enums/ConversationPhaseTest.php` | Tests for conversation phase transitions |
| `tests/Unit/Enums/PlanStatusTest.php` | Tests for execution plan status transitions |
| `tests/Unit/Enums/ComplexityLevelTest.php` | Tests for complexity level enum values |
| `tests/Unit/Services/StorageQuotaServiceTest.php` | Tests for storage quota calculations |
| `tests/Unit/Policies/ProjectPolicyTest.php` | Tests for project authorization policies |
| `tests/Unit/Exceptions/ProjectExceptionTest.php` | Tests for custom exception classes |

### Feature Tests (`tests/Feature/`)

Feature tests verify the application's HTTP endpoints and integration behavior.

| Test File | Description |
|-----------|-------------|
| `tests/Feature/AuthenticationTest.php` | Tests for login, logout, and social authentication |
| `tests/Feature/ProjectTest.php` | Tests for project CRUD operations and authorization |
| `tests/Feature/ConversationTest.php` | Tests for AI conversation API authorization |

## Running Tests

### Run All Tests

```bash
php artisan test
```

### Run All Tests (Compact Output)

```bash
php artisan test --compact
```

### Run Unit Tests Only

```bash
php artisan test --testsuite=Unit
```

### Run Feature Tests Only

```bash
php artisan test --testsuite=Feature
```

### Run a Specific Test File

```bash
php artisan test tests/Feature/ProjectTest.php
```

### Run a Specific Test Method

```bash
php artisan test --filter=test_user_can_login_with_valid_credentials
```

### Run Tests Matching a Pattern

```bash
php artisan test --filter="user_can"
```

### Run Tests with Coverage Report

```bash
php artisan test --coverage
```

### Run Tests with Parallel Execution

```bash
php artisan test --parallel
```

## Common Test Scenarios

### Authentication Tests

```bash
# Test login flow
php artisan test --filter=test_user_can_login

# Test logout flow
php artisan test --filter=test_authenticated_user_can_logout

# Test authorization
php artisan test --filter=test_guest_cannot_access_dashboard
```

### Project Tests

```bash
# Test project access control
php artisan test --filter=ProjectTest

# Test project deletion
php artisan test --filter=test_user_can_delete_own_project

# Test scan status
php artisan test --filter=test_user_can_get_scan_status
```

### Conversation Tests

```bash
# Test conversation authorization
php artisan test --filter=ConversationTest
```

### Model Tests

```bash
# Test project model
php artisan test --filter=ProjectTest --testsuite=Unit

# Test all enums
php artisan test --filter=EnumTest
```

### Policy Tests

```bash
# Test all policies
php artisan test --filter=PolicyTest
```

### Service Tests

```bash
# Test storage quota service
php artisan test --filter=StorageQuotaServiceTest
```

## Test Configuration

Tests are configured in `phpunit.xml`. Key settings:

- **Database**: SQLite in-memory (`:memory:`)
- **Queue**: Synchronous (`sync`)
- **Cache**: Array driver
- **Session**: Array driver

## Writing New Tests

### Unit Test Example

```php
<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ExampleTest extends TestCase
{
    public function test_example(): void
    {
        $this->assertTrue(true);
    }
}
```

### Feature Test Example

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_example(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
    }
}
```

## Creating New Tests

Use artisan commands to create new tests:

```bash
# Create a Feature test
php artisan make:test MyFeatureTest

# Create a Unit test
php artisan make:test MyUnitTest --unit
```

## CI/CD Integration

For continuous integration, use:

```bash
# Run all tests with exit code
php artisan test --stop-on-failure

# Run with compact output for CI logs
php artisan test --compact
```

## Troubleshooting

### Common Issues

1. **Missing APP_KEY**: Ensure `APP_KEY` is set in `phpunit.xml`
2. **Database migrations**: Tests use in-memory SQLite with `RefreshDatabase` trait
3. **Vite manifest not found**: Run `npm run build` before feature tests that render views

### Reset Test Environment

```bash
# Clear config cache
php artisan config:clear

# Clear compiled views
php artisan view:clear

# Rebuild assets
npm run build
```
