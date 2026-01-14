# Output Format Partial

<output_instructions>
Respond with a valid JSON object following this schema:

```json
{
    "success": true,
    "confidence": "high|medium|low",
    "result": {
        "summary": "Brief description of what was done/found",
        "details": "Detailed explanation or implementation plan",
        "files_affected": ["path/to/file1.php", "path/to/file2.vue"],
        "changes": [
            {
                "file": "path/to/file.php",
                "action": "create|modify|delete",
                "description": "What this change accomplishes"
            }
        ]
    },
    "warnings": ["Any potential issues or considerations"],
    "next_steps": ["Suggested follow-up actions"],
    "citations": [
        {
            "file": "path/to/file.php",
            "lines": "10-25",
            "relevance": "Why this code is relevant"
        }
    ]
}
```

<confidence_levels>
- **high**: All information needed is available, clear solution exists
- **medium**: Most information available, some assumptions made
- **low**: Significant information missing, answer is partial or uncertain
  </confidence_levels>

<when_to_use_not_enough_context>
If insufficient information exists to answer, respond with:
```json
{
    "success": false,
    "confidence": "low",
    "result": {
        "summary": "NOT ENOUGH CONTEXT",
        "details": "Explanation of what's missing",
        "files_needed": ["List of files that would help"]
    },
    "next_steps": ["How to provide the missing context"]
}
```
</when_to_use_not_enough_context>
</output_instructions>
