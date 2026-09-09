<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesEmployeeCompany;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateCallRequest extends FormRequest
{
    use ValidatesEmployeeCompany;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        foreach (['employee_id', 'recorded_at'] as $field) {
            if ($this->input($field) === '') {
                $this->merge([$field => null]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'recorded_at' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'company_id.required' => 'Please select a company.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(fn (Validator $validator) => $this->validateEmployeeBelongsToCompany($validator));
    }

    /**
     * @return array{company_id:int, employee_id:?int, recorded_at:?string}
     */
    public function payload(): array
    {
        return [
            'company_id' => (int) $this->validated('company_id'),
            'employee_id' => $this->validated('employee_id'),
            'recorded_at' => $this->validated('recorded_at'),
        ];
    }
}
