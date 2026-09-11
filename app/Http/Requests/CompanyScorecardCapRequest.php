<?php

namespace App\Http\Requests;

use App\Models\CompanyScorecardCap;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompanyScorecardCapRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        foreach (['criterion_key', 'description'] as $field) {
            if ($this->input($field) === '') {
                $this->merge([$field => null]);
            }
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
            'criterion_key' => ['nullable', 'string', 'max:100'],
            'trigger_type' => ['required', 'string', Rule::in(CompanyScorecardCap::triggerTypes())],
            'max_total_score' => ['required', 'integer', 'min:0', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
            'sequence' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
