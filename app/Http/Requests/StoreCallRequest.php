<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesEmployeeCompany;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;
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
        $maxKb = (int) config('sales-analyzer.max_audio_size_kb');
        $extensions = config('sales-analyzer.allowed_audio_extensions');
        $mimes = config('sales-analyzer.allowed_audio_mime_types');

        return [
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'recorded_at' => ['nullable', 'date'],
            'source' => ['nullable', 'string', 'max:50'],
            'audio' => [
                'required',
                File::types($extensions)->max($maxKb),
                'mimetypes:'.implode(',', $mimes),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $maxMb = (int) config('sales-analyzer.max_audio_size_mb');

        return [
            'company_id.required' => 'Please select a company.',
            'audio.required' => 'Please choose an audio file.',
            'audio.file' => 'Please choose an audio file.',
            'audio.max' => "The audio file is too large. Maximum size is {$maxMb} MB.",
            'audio.mimes' => 'This audio format is not supported.',
            'audio.mimetypes' => 'This audio format is not supported.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validateEmployeeBelongsToCompany($validator);
            $this->validateAudioExtension($validator);
        });
    }

    private function validateAudioExtension(Validator $validator): void
    {
        $file = $this->file('audio');
        if ($file === null) {
            return;
        }

        $extension = strtolower($file->getClientOriginalExtension());
        $allowed = config('sales-analyzer.allowed_audio_extensions', []);

        if ($extension === '' || ! in_array($extension, $allowed, true)) {
            $validator->errors()->add('audio', 'This audio format is not supported.');
        }
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
