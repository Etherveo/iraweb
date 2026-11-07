<?php

namespace App\Http\Requests;

// M4 simulator (SIM-16..19): period-first preview form — user scope
// (one/several/all/random) + total transaction count.
class PreviewPeriodFirstRequest extends PreviewTransactionRequest
{
    public function rules(): array
    {
        return array_merge($this->commonRules(), [
            'user_scope' => ['required', 'in:one,several,all,random'],
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['integer', 'min:1'],
            'random_k' => ['nullable', 'integer', 'min:1'],
            'transaction_count' => ['required', 'integer', 'min:1'],
        ]);
    }

    public function messages(): array
    {
        return array_merge($this->commonMessages(), [
            'user_scope.required' => 'Cakupan user wajib dipilih.',
            'user_scope.in' => "Cakupan user harus 'one', 'several', 'all', atau 'random'.",
            'random_k.min' => 'Jumlah user acak (k) minimal 1.',
            'transaction_count.required' => 'Jumlah transaksi wajib diisi.',
            'transaction_count.min' => 'Jumlah transaksi minimal 1 (SIM-17).',
        ]);
    }
}
