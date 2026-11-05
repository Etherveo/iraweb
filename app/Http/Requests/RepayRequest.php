<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

// M3b PAY-31: repay-after-failure form. The method must exist in the
// catalog; PaymentService::createForOrder additionally rejects
// inactive methods and enforces the repay conditions.
class RepayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'payment_method' => [
                'required', 'string',
                Rule::in(array_keys(config('payment.methods', []))),
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'payment_method.required' => 'Pilih metode pembayaran.',
            'payment_method.in' => 'Metode pembayaran tidak dikenal.',
        ];
    }
}
