<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

// M4 simulator (SIM-09..24/31): structural rules shared by the
// transaction preview requests. Semantic validation (limits, user
// scope, products, period) lives in SimulationRequestValidator — the
// FormRequest only guarantees the shape the validator consumes.
abstract class PreviewTransactionRequest extends FormRequest
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

    /** @return array<string, mixed> */
    protected function commonRules(): array
    {
        return [
            'period_start' => ['required', 'date_format:Y-m-d'],
            'period_end' => ['required', 'date_format:Y-m-d'],
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => ['integer', 'min:1'],
            'items_per_tx' => ['nullable', 'array'],
            'items_per_tx.min' => ['nullable', 'integer', 'min:1'],
            'items_per_tx.max' => ['nullable', 'integer', 'min:1'],
            'qty_per_item' => ['nullable', 'array'],
            'qty_per_item.min' => ['nullable', 'integer', 'min:1'],
            'qty_per_item.max' => ['nullable', 'integer', 'min:1'],
            'time_distribution' => ['nullable', 'in:random,even,single'],
            'single_point' => ['nullable', 'date_format:Y-m-d H:i'],
            'user_distribution' => ['nullable', 'in:random,even,weighted'],
            'product_distribution' => ['nullable', 'in:random,even,weighted'],
            'status_mix' => ['nullable', 'array'],
            'status_mix.selesai' => ['nullable', 'integer', 'min:0', 'max:100'],
            'status_mix.dibatalkan' => ['nullable', 'integer', 'min:0', 'max:100'],
            'status_mix.kedaluwarsa' => ['nullable', 'integer', 'min:0', 'max:100'],
            'funnel_view_to_cart' => ['nullable', 'integer', 'min:1', 'max:100'],
            'funnel_cart_to_booking' => ['nullable', 'integer', 'min:1', 'max:100'],
            'mode' => ['nullable', 'in:random,deterministic'],
            'seed' => ['nullable', 'integer'],
        ];
    }

    /** @return array<string, string> */
    protected function commonMessages(): array
    {
        return [
            'period_start.required' => 'Tanggal mulai periode wajib diisi.',
            'period_start.date_format' => 'Tanggal mulai harus format YYYY-MM-DD.',
            'period_end.required' => 'Tanggal akhir periode wajib diisi.',
            'period_end.date_format' => 'Tanggal akhir harus format YYYY-MM-DD.',
            'single_point.date_format' => 'Titik waktu tunggal harus format YYYY-MM-DD HH:MM (WITA).',
            'time_distribution.in' => "Distribusi waktu harus 'random', 'even', atau 'single'.",
            'user_distribution.in' => "Distribusi user harus 'random', 'even', atau 'weighted'.",
            'product_distribution.in' => "Distribusi produk harus 'random', 'even', atau 'weighted'.",
            'mode.in' => "Mode harus 'random' atau 'deterministic'.",
        ];
    }
}
