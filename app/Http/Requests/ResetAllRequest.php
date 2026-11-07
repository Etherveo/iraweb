<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

// M4 simulator (SIM-39, §4.6): reset-all — the developer must type
// the exact phrase HAPUS SEMUA SIMULASI. The phrase is checked
// server-side; the button is only enabled client-side as a courtesy.
class ResetAllRequest extends FormRequest
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
            'phrase' => ['required', 'string', 'in:HAPUS SEMUA SIMULASI'],
        ];
    }

    public function messages(): array
    {
        return [
            'phrase.required' => 'Ketik frasa konfirmasi untuk mereset seluruh data simulasi.',
            'phrase.in' => 'Ketik persis "HAPUS SEMUA SIMULASI" untuk mengonfirmasi reset seluruh data simulasi.',
        ];
    }
}
