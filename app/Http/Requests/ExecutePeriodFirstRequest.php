<?php

namespace App\Http\Requests;

// M4 simulator (SIM-26): period-first execute — the same preview
// input plus the idempotency key and the preview's params hash.
class ExecutePeriodFirstRequest extends PreviewPeriodFirstRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'idempotency_key' => ['required', 'string', 'max:64'],
            'params_hash' => ['required', 'string', 'size:64'],
        ]);
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'idempotency_key.required' => 'Kunci idempotensi tidak ada — buat pratinjau baru.',
            'params_hash.required' => 'Hash pratinjau tidak ada — buat pratinjau baru.',
            'params_hash.size' => 'Hash pratinjau tidak valid — buat pratinjau baru.',
        ]);
    }
}
