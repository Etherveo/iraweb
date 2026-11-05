<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

// PRD-05: the admin sets a NEW physical stock (0..1.000.000), not a
// delta. The "not below stock_reserved" rule lives in StockService,
// because it depends on the current reserved value, not just the input.
class AdjustStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'stock_on_hand' => ['required', 'integer', 'min:0', 'max:1000000'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
