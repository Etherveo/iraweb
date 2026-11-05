<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

// Keputusan 2026-11-05: login disatukan — one form, one POST, with an
// aktor switch (pembeli | admin_toko | developer). Guard/role checks live
// in the controller, not here, so a wrong-actor attempt always ends with
// the same generic message as a wrong password.
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
            'aktor' => ['required', 'string', 'in:pembeli,admin_toko,developer'],
            'email' => ['required', 'string', 'email', 'max:190'],
            'password' => ['required', 'string'],
        ];
    }
}
