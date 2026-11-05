<?php

namespace App\Http\Requests;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;

// CAT-01/CAT-02: name 2-60 after trim, unique case-insensitively.
// The DB unique lower(name) index is the backstop; this validator gives
// the per-field message. The stored name keeps its original casing.
class CategoryRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'min:2', 'max:60'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $name = mb_strtolower((string) $this->input('name', ''));
            if ($name === '') {
                return;
            }

            $exists = Category::query()
                ->whereRaw('lower(name) = ?', [$name])
                ->when($this->route('kategori') !== null, fn ($q) => $q->where('id', '!=', (int) $this->route('kategori')))
                ->exists();

            if ($exists) {
                $validator->errors()->add('name', 'Nama kategori sudah digunakan.');
            }
        });
    }
}
