<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AskAIRequest extends FormRequest
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
            'question' => ['required', 'string', 'min:5', 'max:5000'],
            'context' => ['nullable', 'array'],
            'context.files' => ['nullable', 'array'],
            'context.files.*' => ['string', 'max:500'],
            'include_code' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'question.required' => 'Please enter a question.',
            'question.min' => 'Please provide more detail in your question (minimum 5 characters).',
            'question.max' => 'Question is too long. Please keep it under 5,000 characters.',
            'context.array' => 'Context must be an array.',
            'context.files.array' => 'Files must be an array.',
            'context.files.*.string' => 'Each file path must be a string.',
        ];
    }
}
