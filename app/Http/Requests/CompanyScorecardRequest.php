<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CompanyScorecardRequest extends FormRequest
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

        foreach (['is_active', 'is_default'] as $field) {
            if ($this->has($field)) {
                $this->merge([$field => filter_var($this->input($field), FILTER_VALIDATE_BOOLEAN)]);
            }
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
            'is_default' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'score_bands' => ['sometimes', 'nullable', 'array'],
            'score_bands.*.min' => ['required_with:score_bands', 'integer', 'min:0', 'max:100'],
            'score_bands.*.max' => ['required_with:score_bands', 'integer', 'min:0', 'max:100'],
            'score_bands.*.label' => ['required_with:score_bands', 'string', 'max:100'],
        ];
    }
}
