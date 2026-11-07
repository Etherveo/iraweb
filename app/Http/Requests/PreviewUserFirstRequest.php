<?php

namespace App\Http\Requests;

// M4 simulator (SIM-09/11): user-first preview form — explicit
// account selection + transactions per account.
class PreviewUserFirstRequest extends PreviewTransactionRequest
{
    public function rules(): array
    {
        return array_merge($this->commonRules(), [
            'user_ids' => ['required', 'array', 'min:1', 'max:100'],
            'user_ids.*' => ['integer', 'min:1'],
            'transactions_per_account' => ['required', 'integer', 'min:1'],
        ]);
    }

    public function messages(): array
    {
        return array_merge($this->commonMessages(), [
            'user_ids.required' => 'Pilih minimal 1 akun simulasi (SIM-09).',
            'user_ids.max' => 'Maksimal 100 akun per request (SIM-09).',
            'transactions_per_account.required' => 'Jumlah transaksi per akun wajib diisi.',
            'transactions_per_account.min' => 'Jumlah transaksi per akun minimal 1 (SIM-11).',
        ]);
    }
}
