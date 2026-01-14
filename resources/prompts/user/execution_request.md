# Execution Request User Prompt

<task>
{{TASK_DESCRIPTION}}
</task>

<operation_details>
- **Operation Type**: {{OPERATION_TYPE}}
- **File Path**: {{FILE_PATH}}
  {{#NEW_FILE_PATH}}
- **New Path**: {{NEW_FILE_PATH}}
  {{/NEW_FILE_PATH}}
- **Priority**: {{PRIORITY}}
  </operation_details>

<plan_context>
**Plan Title**: {{PLAN_TITLE}}
**Plan Summary**: {{PLAN_SUMMARY}}
**Approach**: {{PLAN_APPROACH}}
</plan_context>

{{#CURRENT_FILE_CONTENT}}
<current_file>
The file currently contains this code:

```{{LANGUAGE}}
{{CURRENT_FILE_CONTENT}}
```
</current_file>
{{/CURRENT_FILE_CONTENT}}

{{#PLANNED_CHANGES}}
<planned_changes>
The following changes should be applied:

{{PLANNED_CHANGES}}
</planned_changes>
{{/PLANNED_CHANGES}}

{{#TEMPLATE_CONTENT}}
<template_guidance>
Use this as a starting point or reference:

```{{LANGUAGE}}
{{TEMPLATE_CONTENT}}
```
</template_guidance>
{{/TEMPLATE_CONTENT}}

{{#RELATED_FILES}}
<related_files>
These related files provide context for patterns and conventions:

{{RELATED_FILES}}
</related_files>
{{/RELATED_FILES}}

<output_requirements>
Return ONLY the complete file content. Do not include:
- Markdown code fences (```)
- File path comments
- Explanations of changes
- Preamble or postscript text

The response should be the exact content that will be written to the file.
</output_requirements>

<examples>
<example type="create_php_service">
<input>
Task: Create a UserNotificationService
Operation: create
Path: app/Services/UserNotificationService.php
</input>
<output>
<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Notifications\UserNotification;
use Illuminate\Support\Facades\Log;

class UserNotificationService
{
public function __construct(
private readonly NotificationChannelResolver $channelResolver,
) {}

    public function notify(User $user, string $type, array $data = []): bool
    {
        try {
            $channel = $this->channelResolver->resolve($user, $type);
            $user->notify(new UserNotification($type, $data, $channel));
            
            return true;
        } catch (\Throwable $e) {
            Log::error('Failed to send notification', [
                'user_id' => $user->id,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);
            
            return false;
        }
    }
}
</output>
</example>

<example type="modify_controller">
<input>
Task: Add a store method to handle form submission
Operation: modify
Current file has: index, show methods
Changes: Add store method after show
</input>
<output>
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreItemRequest;
use App\Models\Item;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ItemController extends Controller
{
public function index(): Response
{
return Inertia::render('Items/Index', [
'items' => Item::paginate(20),
]);
}

    public function show(Item $item): Response
    {
        return Inertia::render('Items/Show', [
            'item' => $item,
        ]);
    }

    public function store(StoreItemRequest $request): RedirectResponse
    {
        $item = Item::create($request->validated());

        return redirect()
            ->route('items.show', $item)
            ->with('success', 'Item created successfully.');
    }
}
</output>
</example>

<example type="create_vue_component">
<input>
Task: Create a StatusBadge component
Operation: create
Path: resources/js/Components/StatusBadge.vue
</input>
<output>
<script setup lang="ts">
interface Props {
    status: 'pending' | 'active' | 'completed' | 'failed'
    size?: 'sm' | 'md' | 'lg'
}

const props = withDefaults(defineProps<Props>(), {
size: 'md',
})

const colorClasses = {
pending: 'bg-yellow-100 text-yellow-800 border-yellow-200',
active: 'bg-blue-100 text-blue-800 border-blue-200',
completed: 'bg-green-100 text-green-800 border-green-200',
failed: 'bg-red-100 text-red-800 border-red-200',
}

const sizeClasses = {
sm: 'px-2 py-0.5 text-xs',
md: 'px-2.5 py-1 text-sm',
lg: 'px-3 py-1.5 text-base',
}

const label = {
pending: 'Pending',
active: 'Active',
completed: 'Completed',
failed: 'Failed',
}
</script>

<template>
    <span
        :class="[
            'inline-flex items-center font-medium rounded-full border',
            colorClasses[status],
            sizeClasses[size],
        ]"
    >
        {{ label[status] }}
    </span>
</template>
</output>
</example>
</examples>

<critical_rules>
1. **Complete File Only**: Output the ENTIRE file content. Never output partial code or snippets.

2. **No Markdown**: Do not wrap the output in code fences. The raw file content is expected.

3. **Preserve Structure**: When modifying, keep all existing code that is not explicitly being changed.

4. **Match Style**: Follow the exact coding style visible in the current file and related files:
    - Same indentation (spaces vs tabs, count)
    - Same brace style
    - Same naming conventions
    - Same import organization

5. **Include All Imports**: Add any new imports/use statements required by the changes.

6. **Handle Types**: Include proper type hints, PHPDoc blocks, or TypeScript types as appropriate.

7. **Error Handling**: Include appropriate try/catch blocks and error handling for new code.

8. **No Placeholders**: Never use comments like `// ... rest of code`, `// TODO`, or `/* existing code */`.
   </critical_rules>
