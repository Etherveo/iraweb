<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

// Shared login validation for all three forms (ACC-02/03/04).
// Role/guard checks live in the controllers, not here, so a wrong-role
// attempt always ends with the same generic message as a wrong password.
class LoginRequest extends FormRequest
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
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:190'],
            'password' => ['required', 'string'],
        ];
    }
}
