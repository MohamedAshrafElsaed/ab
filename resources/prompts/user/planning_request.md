# Planning Request User Prompt

<user_request>
{{USER_REQUEST}}
</user_request>

<intent_analysis>
- **Type**: {{INTENT_TYPE}}
- **Confidence**: {{INTENT_CONFIDENCE}}
- **Complexity**: {{INTENT_COMPLEXITY}}
- **Domain**: {{INTENT_DOMAIN}}
  {{#MENTIONED_FILES}}
- **Mentioned Files**: {{MENTIONED_FILES}}
  {{/MENTIONED_FILES}}
  {{#MENTIONED_SYMBOLS}}
- **Mentioned Symbols**: {{MENTIONED_SYMBOLS}}
  {{/MENTIONED_SYMBOLS}}
  </intent_analysis>

<codebase_context>
{{CODEBASE_CONTEXT}}
</codebase_context>

<output_requirements>
Respond with a **valid JSON object** following this exact schema. Do not include any explanation, markdown formatting, or additional text outside the JSON.

```json
{
  "title": "Brief, descriptive title (max 60 chars)",
  "summary": "Detailed explanation of what will be implemented and why",
  "approach": "Technical approach and design decisions explained",
  
  "file_operations": [
    {
      "type": "create|modify|delete|rename|move",
      "path": "path/to/file.php",
      "new_path": "only for rename/move operations",
      "priority": 1,
      "description": "What this operation accomplishes",
      "template_content": "Full file content for CREATE operations",
      "changes": [
        {
          "section": "Section of file being modified",
          "change_type": "add|remove|replace",
          "before": "Existing code (required for replace/remove)",
          "after": "New code to insert",
          "start_line": 10,
          "end_line": 20,
          "explanation": "Why this change is needed"
        }
      ],
      "dependencies": ["paths of files that must exist first"]
    }
  ],
  
  "risks": [
    {
      "level": "low|medium|high",
      "description": "What could go wrong",
      "mitigation": "How to prevent or address it"
    }
  ],
  
  "prerequisites": ["Requirements before execution"],
  "testing_notes": "How to verify the changes work correctly",
  "estimated_time": "Time estimate for execution"
}
```
</output_requirements>

<critical_rules>
1. **Complete Code Only**: For `create` operations, provide the COMPLETE file content. Never use placeholders like `// ... rest of code` or `// TODO`.

2. **Exact Matches for Modifications**: For `replace` and `remove` changes, the `before` content must match existing code exactly.

3. **Dependency Order**: Set `priority` so files without dependencies have lower numbers. Files that depend on others should have higher priority numbers.

4. **Follow Project Conventions**: Match the existing code style, naming conventions, and patterns visible in the codebase context.

5. **Include All Affected Files**: List every file that needs to change, including:
    - Route files when adding endpoints
    - Migration files for database changes
    - Test files for new functionality
    - Config files for new settings

6. **Error Handling**: Include proper error handling, validation, and edge case handling in all code.

7. **Security First**: Never hardcode credentials, always validate inputs, use proper escaping.
   </critical_rules>

<examples>
<example type="create">
```json
{
  "type": "create",
  "path": "app/Services/PaymentService.php",
  "priority": 1,
  "description": "New service for processing payments",
  "template_content": "<?php\n\nnamespace App\\Services;\n\nuse App\\Models\\Payment;\nuse Illuminate\\Support\\Facades\\Log;\n\nclass PaymentService\n{\n    public function process(array $data): Payment\n    {\n        Log::info('Processing payment', ['amount' => $data['amount']]);\n        \n        return Payment::create([\n            'amount' => $data['amount'],\n            'status' => 'pending',\n        ]);\n    }\n}",
  "dependencies": []
}
```
</example>

<example type="modify">
```json
{
  "type": "modify",
  "path": "routes/web.php",
  "priority": 2,
  "description": "Add payment routes",
  "changes": [
    {
      "section": "payment routes",
      "change_type": "add",
      "before": null,
      "after": "Route::post('/payments', [PaymentController::class, 'store'])->name('payments.store');",
      "start_line": 45,
      "explanation": "Adding POST endpoint for payment processing"
    }
  ],
  "dependencies": ["app/Http/Controllers/PaymentController.php"]
}
```
</example>

<example type="delete">
```json
{
  "type": "delete",
  "path": "app/Services/LegacyPaymentService.php",
  "priority": 10,
  "description": "Remove deprecated service replaced by PaymentService",
  "dependencies": ["app/Services/PaymentService.php"]
}
```
</example>
</examples>
