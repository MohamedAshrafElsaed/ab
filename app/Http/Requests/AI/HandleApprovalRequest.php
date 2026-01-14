<?php

namespace App\Http\Requests\AI;

use Illuminate\Foundation\Http\FormRequest;

class HandleApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('conversation')->project);
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
            'approved.required' => 'Please specify whether to approve or reject the plan.',
            'feedback.max' => 'Feedback must be under 5,000 characters.',
        ];
    }
}
