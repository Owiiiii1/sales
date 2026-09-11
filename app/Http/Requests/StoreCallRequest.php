<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesEmployeeCompany;
use App\Support\CallAudioRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreCallRequest extends FormRequest
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

        if (! $this->filled('source')) {
            $this->merge(['source' => 'manual']);
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
            'source' => ['nullable', 'string', 'max:50'],
            'audio' => CallAudioRules::file(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge(CallAudioRules::messages(), [
            'company_id.required' => __('Please select a company.'),
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validateEmployeeBelongsToCompany($validator);
            CallAudioRules::validateExtension($validator, $this->file('audio'));
        });
    }

    /**
     * @return array{company_id:int, employee_id:?int, recorded_at:?string, source:string}
     */
    public function payload(): array
    {
        return [
            'company_id' => (int) $this->validated('company_id'),
            'employee_id' => $this->validated('employee_id'),
            'recorded_at' => $this->validated('recorded_at'),
            'source' => $this->validated('source') ?: 'manual',
        ];
    }
}
