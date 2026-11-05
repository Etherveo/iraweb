<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

// ORD-01/ORD-02: a payment method from the active config catalog
// (PAY-02) and the idempotency key issued with the checkout form.
// There is deliberately NO amount/total field: the total is computed
// on the server (ORD-04) and any client-supplied amount is ignored.
class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $active = array_keys(array_filter(
            config('payment.methods', []),
            fn (array $method) => ($method['active'] ?? false) === true,
        ));

        return [
            'payment_method' => ['required', 'string', Rule::in($active)],
            'checkout_key' => ['nullable', 'string', 'max:64'],
        ];
    }
}
