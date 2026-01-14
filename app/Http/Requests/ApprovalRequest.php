<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'approved' => ['required', 'boolean'],
            'feedback' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'approved.required' => 'Please indicate whether you approve or reject the plan.',
            'approved.boolean' => 'Approval must be true or false.',
            'feedback.max' => 'Feedback is too long. Please keep it under 5,000 characters.',
        ];
    }
}
