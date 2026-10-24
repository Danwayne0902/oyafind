<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class LoginRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'identifier' => ['required', 'string', 'max:190'],
            'password' => ['required', 'string', 'max:255'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->has('identifier')) {
                return;
            }

            $identifier = trim((string) $this->input('identifier'));
            $isEmail = filter_var($identifier, FILTER_VALIDATE_EMAIL) !== false;
            $isPhone = preg_match('/^\+?[0-9][0-9\s().-]{5,24}$/D', $identifier) === 1;

            if (! $isEmail && ! $isPhone) {
                $validator->errors()->add(
                    'identifier',
                    'Enter a valid email address or phone number.',
                );
            }
        });
    }
}
