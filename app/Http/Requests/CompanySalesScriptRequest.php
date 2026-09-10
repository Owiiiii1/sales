<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CompanySalesScriptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('description') === '') {
            $this->merge(['description' => null]);
        }

        if ($this->has('is_active')) {
            $this->merge(['is_active' => filter_var($this->input('is_active'), FILTER_VALIDATE_BOOLEAN)]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'script_text' => ['required', 'string', 'max:20000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
