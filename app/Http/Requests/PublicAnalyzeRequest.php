<?php

namespace App\Http\Requests;

use App\Support\CallAudioRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class PublicAnalyzeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'audio' => CallAudioRules::file(),
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
        $validator->after(fn (Validator $validator) => CallAudioRules::validateExtension(
            $validator,
            $this->file('audio'),
        ));
    }
}
