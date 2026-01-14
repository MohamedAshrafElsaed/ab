<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProjectRequest extends FormRequest
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
            'repo_full_name' => ['required', 'string', 'max:255'],
            'repo_id' => ['nullable', 'string', 'max:255'],
            'default_branch' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'repo_full_name.required' => 'Please select a repository.',
            'default_branch.required' => 'The default branch is required.',
        ];
    }
}
