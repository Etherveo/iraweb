<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

// M4 simulator (SIM-02/03): bulk account creation form. The 100 cap
// is a code constant (SIM-03), so it is safe to state here; the
// validator service enforces it authoritatively.
class StoreSimulationAccountsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'count' => ['required', 'integer', 'min:1', 'max:100'],
            'mode' => ['required', 'in:random,deterministic'],
            'seed' => ['nullable', 'integer'],
            'offset' => ['nullable', 'integer', 'min:0'],
            'idempotency_key' => ['required', 'string', 'max:64'],
        ];
    }

    public function messages(): array
    {
        return [
            'count.required' => 'Jumlah akun wajib diisi.',
            'count.min' => 'Jumlah akun minimal 1.',
            'count.max' => 'Maksimal 100 akun per request (SIM-03).',
            'mode.in' => "Mode harus 'random' atau 'deterministic'.",
            'offset.min' => 'Offset minimal 0.',
            'idempotency_key.required' => 'Kunci idempotensi tidak ada — muat ulang formulir.',
        ];
    }
}
