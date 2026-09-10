<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CompanyScorecardCriterionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        foreach (['description', 'ai_instructions'] as $field) {
            if ($this->input($field) === '') {
                $this->merge([$field => null]);
            }
        }

        if (! filled($this->input('key')) && filled($this->input('name'))) {
            $this->merge(['key' => Str::slug((string) $this->input('name'), '_')]);
        }

        foreach (['is_active', 'is_critical'] as $field) {
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
            'key' => [
                'required',
                'string',
                'max:80',
                'regex:/^[a-z0-9_]+$/',
                Rule::unique('company_scorecard_criteria', 'key')
                    ->where(fn ($query) => $query->where('scorecard_id', $this->route('scorecard')?->id))
                    ->ignore($this->route('criterion')),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'weight' => ['required', 'numeric', 'min:0', 'max:1000'],
            'max_score' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'is_critical' => ['sometimes', 'boolean'],
            'ai_instructions' => ['nullable', 'string', 'max:5000'],
            'sequence' => ['sometimes', 'integer', 'min:0', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
