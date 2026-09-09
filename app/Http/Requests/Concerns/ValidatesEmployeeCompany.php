<?php

namespace App\Http\Requests\Concerns;

use App\Models\Employee;
use Illuminate\Validation\Validator;

trait ValidatesEmployeeCompany
{
    protected function validateEmployeeBelongsToCompany(Validator $validator): void
    {
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
    }
}
