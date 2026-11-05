<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

// ACC-01: name 2-100, email valid/max 190/unique case-insensitive,
// password >= 8 + confirmation, reserved simulation domain rejected.
class RegisterBuyerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
        if (is_string($this->input('name'))) {
            $this->merge(['name' => trim($this->input('name'))]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:190', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $email = (string) $this->input('email', '');
            $domain = mb_strtolower(substr(strrchr($email, '@') ?: '', 1));
            if ($domain !== '' && $domain === mb_strtolower((string) config('ira.sim_account_email_domain'))) {
                $validator->errors()->add('email', 'Domain email ini dicadangkan untuk akun simulasi.');
            }
        });
    }
}
