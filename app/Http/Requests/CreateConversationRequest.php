<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateConversationRequest extends FormRequest
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
            'message' => ['required', 'string', 'min:10', 'max:10000'],
            'title' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'message.required' => 'Please provide an initial message to start the conversation.',
            'message.min' => 'Please provide more detail about what you need help with (minimum 10 characters).',
            'message.max' => 'Message is too long. Please keep it under 10,000 characters.',
            'title.max' => 'Title must be under 255 characters.',
        ];
    }
}
