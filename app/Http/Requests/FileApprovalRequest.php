<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FileApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', 'string', Rule::in(['approve', 'skip', 'reject'])],
            'feedback' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'action.required' => 'Please specify an action (approve, skip, or reject).',
            'action.in' => 'Action must be one of: approve, skip, reject.',
            'feedback.max' => 'Feedback is too long. Please keep it under 2,000 characters.',
        ];
    }
}
