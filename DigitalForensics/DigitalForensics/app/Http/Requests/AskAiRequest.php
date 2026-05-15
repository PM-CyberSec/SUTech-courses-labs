<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AskAiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'question' => ['required', 'string', 'max:4000'],
            'context' => ['nullable', 'string', 'max:20000'],
            'context_title' => ['nullable', 'string', 'max:255'],
            'context_path' => ['nullable', 'string', 'max:1024'],
        ];
    }
}
