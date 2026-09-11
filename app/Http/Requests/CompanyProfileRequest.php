<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CompanyProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        foreach ([
            'short_description',
            'sales_context',
            'target_audience',
            'ideal_customer_profile',
            'value_proposition',
            'usp',
            'pricing_context',
            'competitors',
            'customer_pains',
            'sales_goals',
            'desired_next_steps',
            'forbidden_claims',
            'mandatory_questions',
            'notes',
            'report_language',
        ] as $field) {
            if ($this->input($field) === '') {
                $this->merge([$field => null]);
            }
        }

        if ($this->input('report_language') === null || $this->input('report_language') === '') {
            $this->merge(['report_language' => 'same_as_call']);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'short_description' => ['nullable', 'string', 'max:5000'],
            'sales_context' => ['nullable', 'string', 'max:20000'],
            'target_audience' => ['nullable', 'string', 'max:5000'],
            'ideal_customer_profile' => ['nullable', 'string', 'max:5000'],
            'value_proposition' => ['nullable', 'string', 'max:5000'],
            'usp' => ['nullable', 'string', 'max:5000'],
            'pricing_context' => ['nullable', 'string', 'max:5000'],
            'competitors' => ['nullable', 'string', 'max:5000'],
            'customer_pains' => ['nullable', 'string', 'max:5000'],
            'sales_goals' => ['nullable', 'string', 'max:5000'],
            'desired_next_steps' => ['nullable', 'string', 'max:5000'],
            'forbidden_claims' => ['nullable', 'string', 'max:5000'],
            'mandatory_questions' => ['nullable', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'report_language' => ['nullable', 'string', 'in:same_as_call,en,ru,uk'],
        ];
    }
}
