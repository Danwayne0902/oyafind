<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

class RegisterRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('contact'))) {
            $contact = trim($this->input('contact'));
            if (filter_var($contact, FILTER_VALIDATE_EMAIL) !== false) {
                $contact = Str::lower($contact);
            } else {
                $contact = preg_replace('/[\s().-]/', '', $contact) ?? $contact;
            }
            $this->merge(['contact' => $contact]);
        }

        if (is_string($this->input('name'))) {
            $this->merge(['name' => trim($this->input('name'))]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'contact' => ['required', 'string', 'max:190'],
            'password' => ['required', 'string', 'min:12', 'max:255', 'confirmed'],
            'role' => ['prohibited'],
            'status' => ['prohibited'],
            'email_verified_at' => ['prohibited'],
            'phone_verified_at' => ['prohibited'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->has('contact')) {
                return;
            }

            $contact = (string) $this->input('contact');
            $isEmail = filter_var($contact, FILTER_VALIDATE_EMAIL) !== false;
            $isPhone = preg_match('/^\+?[0-9][0-9]{5,24}$/D', $contact) === 1;

            if (! $isEmail && ! $isPhone) {
                $validator->errors()->add('contact', 'Enter a valid email address or phone number.');

                return;
            }

            $query = User::query();
            if ($isEmail) {
                $query->whereRaw('LOWER(email) = ?', [$contact]);
            } else {
                $query->where('phone', $contact);
            }
            $existingUser = $query->first();

            if ($existingUser) {
                $validator->errors()->add('contact', 'An account with this email or phone number already exists. Sign in or use different details.');
            }
        });
    }

    public function authorize(): bool
    {
        return true;
    }
}
