# Error Handling Partial

<error_handling_guidelines>

## When Analyzing/Fixing Bugs

Consider these error categories:

<error_types>
1. **Syntax Errors**: Invalid code structure
2. **Runtime Errors**: Exceptions thrown during execution
3. **Logic Errors**: Code runs but produces wrong results
4. **Type Errors**: Mismatched types in strict mode
5. **Security Vulnerabilities**: SQL injection, XSS, CSRF, etc.
6. **Performance Issues**: N+1 queries, memory leaks, slow algorithms
   </error_types>

<diagnosis_approach>
1. Identify the exact error message/symptom
2. Locate the file and line number where the error originates
3. Trace the code path that leads to the error
4. Identify the root cause (not just the symptom)
5. Consider edge cases that might trigger the same error
   </diagnosis_approach>

## When Writing New Code

Always include proper error handling:

<php_patterns>
```php
// Use try-catch for operations that can fail
try {
    $result = $this->riskyOperation();
} catch (SpecificException $e) {
    Log::error('Operation failed', ['error' => $e->getMessage()]);
    throw new UserFriendlyException('Something went wrong');
}

// Validate input before processing
if (!$this->isValid($input)) {
    throw new ValidationException('Invalid input provided');
}

// Use null coalescing for optional values
$value = $data['key'] ?? $default;

// Return early for invalid states
if (!$user) {
    return response()->json(['error' => 'User not found'], 404);
}
```
</php_patterns>

<javascript_patterns>
```javascript
// Async error handling
try {
    const result = await fetchData()
} catch (error) {
    console.error('Fetch failed:', error)
    throw new Error('Failed to load data')
}

// Optional chaining for nested access
const value = response?.data?.items?.[0]

// Guard clauses
if (!props.data) {
    return <EmptyState />
}
```
</javascript_patterns>

<error_response_format>
When an error occurs, respond with:
```json
{
    "error": {
        "type": "validation|runtime|not_found|unauthorized|server",
        "message": "Human-readable error message",
        "details": "Technical details for debugging (dev only)",
        "code": "ERROR_CODE_CONSTANT"
    }
}
```
</error_response_format>

<security_errors>
Never expose in error messages:
- Database table/column names
- File system paths
- Stack traces (in production)
- SQL queries
- API keys or secrets
- User credentials
  </security_errors>

</error_handling_guidelines>
