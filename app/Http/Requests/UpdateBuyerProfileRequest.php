<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBuyerProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['name', 'username', 'phone', 'gender', 'birthdate'] as $field) {
            if (is_string($this->input($field))) {
                $trimmed = trim($this->input($field));
                $this->merge([$field => $trimmed === '' && $field !== 'name' ? null : $trimmed]);
            }
        }
    }

    public function rules(): array
    {
        $userId = $this->user('web')?->id;

        return [
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'username' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('users', 'username')->ignore($userId),
            ],
            'phone' => ['nullable', 'string', 'max:20'],
            'gender' => ['nullable', 'string', Rule::in(['laki-laki', 'perempuan'])],
            'birthdate' => ['nullable', 'date', 'before_or_equal:today'],
        ];
    }
}
