<?php

namespace App\Http\Requests;

use App\Models\CompanyOffering;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompanyOfferingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        foreach (['description', 'target_customer', 'value_proposition', 'pricing', 'differentiators', 'common_use_cases'] as $field) {
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
            'type' => ['required', Rule::in(CompanyOffering::TYPES)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'target_customer' => ['nullable', 'string', 'max:2000'],
            'value_proposition' => ['nullable', 'string', 'max:5000'],
            'pricing' => ['nullable', 'string', 'max:2000'],
            'differentiators' => ['nullable', 'string', 'max:2000'],
            'common_use_cases' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
