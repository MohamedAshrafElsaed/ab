# Code Output Rules

<code_quality_standards>

## PHP/Laravel
- Use `declare(strict_types=1);` at the top of all PHP files
- Follow PSR-12 coding standards
- Use type hints for all parameters and return types
- Use readonly properties and constructor property promotion
- Use named arguments for clarity when appropriate
- Prefer early returns over nested conditionals
- Use null coalescing (`??`) and null safe (`?->`) operators

## Vue 3 / TypeScript
- Use `<script setup lang="ts">` syntax
- Define props with `defineProps<Interface>()` pattern
- Define emits with `defineEmits<{...}>()` pattern
- Use `ref()` and `computed()` from Vue
- Extract reusable logic into composables
- Use proper TypeScript interfaces, not `any`

## General
- Include all necessary imports at the top
- Group imports logically (framework, packages, local)
- Add proper error handling with try/catch
- Validate inputs before processing
- Log errors with context for debugging
- Use meaningful variable and function names
- Keep functions focused and small
- Add JSDoc/PHPDoc for complex functions

</code_quality_standards>

<naming_conventions>

## PHP
- Classes: `PascalCase` (e.g., `UserService`)
- Methods: `camelCase` (e.g., `getUserById`)
- Properties: `camelCase` (e.g., `$userName`)
- Constants: `UPPER_SNAKE_CASE` (e.g., `MAX_ATTEMPTS`)
- Config keys: `snake_case` (e.g., `cache_ttl`)

## JavaScript/TypeScript
- Components: `PascalCase` (e.g., `UserCard.vue`)
- Composables: `camelCase` with `use` prefix (e.g., `useAuth`)
- Functions: `camelCase` (e.g., `fetchUsers`)
- Constants: `UPPER_SNAKE_CASE` or `camelCase`
- CSS classes: `kebab-case` (e.g., `user-card`)

## Files
- PHP classes: `PascalCase.php` (e.g., `UserController.php`)
- Vue components: `PascalCase.vue` (e.g., `UserProfile.vue`)
- Composables: `camelCase.ts` (e.g., `useNotifications.ts`)
- Config: `snake_case.php` (e.g., `project_settings.php`)

</naming_conventions>

<import_organization>

## PHP
```php
<?php
// 1. Strict types declaration
declare(strict_types=1);

// 2. Namespace
namespace App\Services;

// 3. PHP built-in classes
use Exception;
use InvalidArgumentException;

// 4. Framework classes
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

// 5. Package classes
use Carbon\Carbon;

// 6. Application classes (alphabetical)
use App\Models\User;
use App\Repositories\UserRepository;
```

## TypeScript/Vue
```typescript
// 1. Vue imports
import { ref, computed, onMounted } from 'vue'

// 2. Third-party imports
import { useRoute } from 'vue-router'
import axios from 'axios'

// 3. Local imports (components)
import BaseButton from '@/Components/BaseButton.vue'

// 4. Local imports (composables/utils)
import { useAuth } from '@/composables/useAuth'
import { formatDate } from '@/utils/formatters'

// 5. Types
import type { User, ApiResponse } from '@/types'
```

</import_organization>
