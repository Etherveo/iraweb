<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

// M4 simulator (SIM-38, §4.6): per-run reset — the developer must
// type the exact phrase HAPUS RUN. The phrase is checked
// server-side; the button is only enabled client-side as a courtesy.
class ResetRunRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('phrase'))) {
            $this->merge(['phrase' => trim($this->input('phrase'))]);
        }
    }

    public function rules(): array
    {
        return [
            'phrase' => ['required', 'string', 'in:HAPUS RUN'],
        ];
    }

    public function messages(): array
    {
        return [
            'phrase.required' => 'Ketik frasa konfirmasi untuk mereset run ini.',
            'phrase.in' => 'Ketik persis "HAPUS RUN" untuk mengonfirmasi reset run ini.',
        ];
    }
}
