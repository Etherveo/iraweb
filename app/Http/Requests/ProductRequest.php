<?php

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;

// PRD-01/PRD-02/PRD-03: name 2-150 unique case-insensitively, category
// must exist, description optional <= 2000, price integer rupiah
// 1..100.000.000 (the 'integer' rule rejects 0, negatives, decimals
// and text; the DB CHECK is the backstop), initial stock integer
// 0..1.000.000 (create only; stock changes go through PRD-05, never here).
class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => trim($this->input('name'))]);
        }
    }

    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'min:2', 'max:150'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'description' => ['nullable', 'string', 'max:2000'],
            'price_idr' => ['required', 'integer', 'min:1', 'max:100000000'],
            'is_active' => ['sometimes', 'boolean'],
        ];

        if ($this->isMethod('post') && $this->route('produk') === null) {
            $rules['stock_on_hand'] = ['required', 'integer', 'min:0', 'max:1000000'];
        }

        return $rules;
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $name = mb_strtolower((string) $this->input('name', ''));
            if ($name === '') {
                return;
            }

            $exists = Product::query()
                ->whereRaw('lower(name) = ?', [$name])
                ->when($this->route('produk') !== null, fn ($q) => $q->where('id', '!=', (int) $this->route('produk')))
                ->exists();

            if ($exists) {
                $validator->errors()->add('name', 'Nama produk sudah digunakan.');
            }
        });
    }
}
