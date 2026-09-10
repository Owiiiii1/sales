<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CompanyObjectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        foreach (['recommended_response', 'notes'] as $field) {
            if ($this->input($field) === '') {
                $this->merge([$field => null]);
            }
        }

        if ($this->input('priority') === '') {
            $this->merge(['priority' => null]);
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
            'objection' => ['required', 'string', 'max:255'],
            'recommended_response' => ['nullable', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
