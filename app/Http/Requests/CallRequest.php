<?php

namespace App\Http\Requests;

use App\Models\Call;
use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CallRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        foreach (['employee_id', 'original_filename', 'source', 'recorded_at', 'duration_seconds'] as $field) {
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
            'source' => ['required', 'string', 'max:50'],
            'original_filename' => ['nullable', 'string', 'max:255'],
            'duration_seconds' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', 'string', Rule::in(Call::STATUSES)],
            'recorded_at' => ['nullable', 'date'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $employeeId = $this->input('employee_id');
            $companyId = $this->input('company_id');

            if ($employeeId === null || $companyId === null || $validator->errors()->isNotEmpty()) {
                return;
            }

            $belongs = Employee::query()
                ->whereKey($employeeId)
                ->where('company_id', $companyId)
                ->exists();

            if (! $belongs) {
                $validator->errors()->add(
                    'employee_id',
                    'The selected employee does not belong to the selected company.',
                );
            }
        });
    }
}
