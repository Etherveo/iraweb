<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

// CRT-01: product must exist, quantity is an integer 1..99. The stock
// and active checks live in CartService (they need fresh product data,
// not just the request), and failures there surface as validation
// errors back to the product page.
class AddToCartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:99'],
        ];
    }
}
