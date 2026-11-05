<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

// ORD-12: admin fulfillment may only move dibayar -> diproses and
// diproses -> selesai. The state machine rejects anything else at the
// service layer too; this request only narrows the input to the two
// values the UI can ever send.
class AdminOrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'to_status' => ['required', 'string', Rule::in(['diproses', 'selesai'])],
        ];
    }
}
