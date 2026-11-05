<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

// CRT-02: the new quantity is an integer 1..99. Stock availability is
// checked in CartService against fresh product data.
class UpdateCartItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'quantity' => ['required', 'integer', 'min:1', 'max:99'],
        ];
    }
}
