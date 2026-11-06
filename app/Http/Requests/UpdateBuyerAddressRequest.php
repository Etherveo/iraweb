<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBuyerAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach ([
            'recipient_name', 'recipient_phone', 'province', 'city',
            'district', 'village', 'postal_code', 'street_address', 'address_type',
        ] as $field) {
            if (is_string($this->input($field))) {
                $trimmed = trim($this->input($field));
                $this->merge([$field => $trimmed === '' && $field === 'recipient_phone' ? null : $trimmed]);
            }
        }

        if ($this->input('address_type') === null || $this->input('address_type') === '') {
            $this->merge(['address_type' => 'Rumah']);
        }
    }

    public function rules(): array
    {
        return [
            'recipient_name' => ['required', 'string', 'max:100'],
            'recipient_phone' => ['nullable', 'string', 'max:20'],
            'province' => ['required', 'string', 'max:100'],
            'city' => ['required', 'string', 'max:100'],
            'district' => ['required', 'string', 'max:100'],
            'village' => ['required', 'string', 'max:100'],
            'postal_code' => ['required', 'string', 'digits:5'],
            'street_address' => ['required', 'string', 'max:500'],
            'address_type' => ['required', 'string', Rule::in(['Rumah', 'Kantor', 'Lainnya'])],
        ];
    }
}
