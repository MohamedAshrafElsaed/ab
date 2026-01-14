<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AskProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');

        // User must own the project
        return $project && $project->user_id === $this->user()->id;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'question' => [
                'required',
                'string',
                'min:10',
                'max:2000',
            ],
            'mode' => [
                'sometimes',
                'string',
                Rule::in(['read_only']),
            ],
            'preferred_depth' => [
                'sometimes',
                'string',
                Rule::in(['quick', 'deep']),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'question.required' => 'Please provide a question about the codebase.',
            'question.min' => 'Your question should be at least 10 characters long.',
            'question.max' => 'Your question is too long. Please keep it under 2000 characters.',
            'mode.in' => 'Invalid mode. Currently only "read_only" mode is supported.',
            'preferred_depth.in' => 'Invalid depth. Use "quick" or "deep".',
        ];
    }

    public function getQuestion(): string
    {
        return trim($this->input('question'));
    }

    public function getMode(): string
    {
        return $this->input('mode', 'read_only');
    }

    public function getDepth(): string
    {
        return $this->input('preferred_depth', 'quick');
    }
}
