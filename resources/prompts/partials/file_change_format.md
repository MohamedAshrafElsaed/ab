# File Change Format Partial

<file_change_instructions>

When specifying code changes, use this precise format to enable automated application:

<file_changes>
<file path="relative/path/to/file.ext" action="create|modify|delete">

For **create** action:
<content>
```language
// Complete file content here
// Must be production-ready, no placeholders
```
</content>

For **modify** action, use one of these change types:

<change type="replace" start_line="10" end_line="25">
```language
// New code to replace lines 10-25
// Include complete replacement, not just the changed parts
```
</change>

<change type="insert_after" line="30">
```language
// Code to insert after line 30
```
</change>

<change type="insert_before" line="15">
```language
// Code to insert before line 15
```
</change>

<change type="append">
```language
// Code to append to end of file
```
</change>

<change type="delete" start_line="50" end_line="60">
<reason>Explain why these lines are being removed</reason>
</change>

For **delete** action:
<reason>Explain why this file should be deleted</reason>

</file>
</file_changes>

<important_rules>
1. **Complete Code Only**: Never use comments like "// ... rest of code" or "// implementation here"
2. **Exact Line Numbers**: Line numbers must match the source file exactly
3. **Include Context**: For replacements, include enough surrounding context to locate the change
4. **One Change Per Section**: Each `<change>` block should be atomic
5. **Preserve Formatting**: Match the project's existing code style
6. **No Breaking Changes**: Ensure each change can be applied independently
   </important_rules>

<verification_checklist>
After specifying changes:
- [ ] All file paths are relative to project root
- [ ] Line numbers are accurate
- [ ] Code is syntactically correct
- [ ] Imports/use statements are included
- [ ] Type hints and return types are present
- [ ] Error handling is included
- [ ] No placeholder comments
  </verification_checklist>

</file_change_instructions>
