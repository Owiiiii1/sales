<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesEmployeeCompany;
use App\Support\CallAudioRules;
use App\Support\LanguageCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PublicAnalyzeRequest extends FormRequest
{
    use ValidatesEmployeeCompany;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['company_id', 'employee_id'] as $field) {
            if ($this->input($field) === '' || $this->input($field) === '0') {
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
            'audio' => CallAudioRules::file(),
            'company_id' => ['nullable', 'integer', Rule::exists('companies', 'id')->where('is_active', true)],
            'employee_id' => ['nullable', 'integer', Rule::exists('employees', 'id')->where('is_active', true)],
            'locale' => ['nullable', 'string', Rule::in(['en', 'ru', 'uk'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return CallAudioRules::messages();
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            CallAudioRules::validateExtension($validator, $this->file('audio'));
            $this->validateEmployeeRequiresCompany($validator);
            $this->validateEmployeeBelongsToCompany($validator);
        });
    }

    /**
     * @return array{company_id:?int, employee_id:?int, source:string, ui_locale:?string}
     */
    public function payload(): array
    {
        $companyId = $this->validated('company_id');
        $employeeId = $this->validated('employee_id');
        $locale = LanguageCode::normalize($this->validated('locale') ?: app()->getLocale());

        return [
            'company_id' => $companyId !== null ? (int) $companyId : null,
            'employee_id' => $employeeId !== null ? (int) $employeeId : null,
            'source' => 'public',
            'ui_locale' => LanguageCode::isSupported($locale) ? $locale : 'en',
        ];
    }

    private function validateEmployeeRequiresCompany(Validator $validator): void
    {
        if ($this->input('employee_id') === null || $validator->errors()->has('employee_id')) {
            return;
        }

        if ($this->input('company_id') === null) {
            $validator->errors()->add(
                'employee_id',
                __('An employee can only be selected with a company.'),
            );
        }
    }
}
