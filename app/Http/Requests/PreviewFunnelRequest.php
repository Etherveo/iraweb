<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

// M4 simulator (SIM-31): explicit funnel preview form — the four
// funnel counts must shrink (view ≥ cart ≥ booking ≥ purchase).
class PreviewFunnelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // <input type="datetime-local"> submits 'Y-m-d\TH:i'; the
        // validator expects 'Y-m-d H:i'.
        if (is_string($this->input('single_point'))) {
            $this->merge(['single_point' => str_replace('T', ' ', $this->input('single_point'))]);
        }
    }

    public function rules(): array
    {
        return [
            'user_ids' => ['required', 'array', 'min:1', 'max:100'],
            'user_ids.*' => ['integer', 'min:1'],
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => ['integer', 'min:1'],
            'period_start' => ['required', 'date_format:Y-m-d'],
            'period_end' => ['required', 'date_format:Y-m-d'],
            'view_count' => ['required', 'integer', 'min:0'],
            'add_to_cart_count' => ['required', 'integer', 'min:0'],
            'booking_count' => ['required', 'integer', 'min:0'],
            'purchase_count' => ['required', 'integer', 'min:0'],
            'time_distribution' => ['nullable', 'in:random,even,single'],
            'single_point' => ['nullable', 'date_format:Y-m-d H:i'],
            'user_distribution' => ['nullable', 'in:random,even,weighted'],
            'product_distribution' => ['nullable', 'in:random,even,weighted'],
            'mode' => ['nullable', 'in:random,deterministic'],
            'seed' => ['nullable', 'integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_ids.required' => 'Pilih minimal 1 akun simulasi (SIM-09).',
            'user_ids.max' => 'Maksimal 100 akun per request (SIM-09).',
            'period_start.required' => 'Tanggal mulai periode wajib diisi.',
            'period_start.date_format' => 'Tanggal mulai harus format YYYY-MM-DD.',
            'period_end.required' => 'Tanggal akhir periode wajib diisi.',
            'period_end.date_format' => 'Tanggal akhir harus format YYYY-MM-DD.',
            'view_count.required' => 'Jumlah view wajib diisi.',
            'add_to_cart_count.required' => 'Jumlah add to cart wajib diisi.',
            'booking_count.required' => 'Jumlah booking wajib diisi.',
            'purchase_count.required' => 'Jumlah purchase wajib diisi.',
            'single_point.date_format' => 'Titik waktu tunggal harus format YYYY-MM-DD HH:MM (WITA).',
            'time_distribution.in' => "Distribusi waktu harus 'random', 'even', atau 'single'.",
            'user_distribution.in' => "Distribusi user harus 'random', 'even', atau 'weighted'.",
            'product_distribution.in' => "Distribusi produk harus 'random', 'even', atau 'weighted'.",
            'mode.in' => "Mode harus 'random' atau 'deterministic'.",
        ];
    }
}
