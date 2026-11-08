<?php

namespace App\Http\Requests;

use App\Simulation\PeriodResolver;
use Carbon\Carbon;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use InvalidArgumentException;

// M5 FR-01..03 (ANL-01): filter periode bersama untuk halaman analitik
// admin. Query GET `dari`/`sampai` (Y-m-d, WITA). Tanpa parameter, dipakai
// 30 hari terakhir (hari ini WITA dan 29 hari sebelumnya); parameter yang
// hilang diisi relatif terhadap yang ada (dari = sampai − 29 hari,
// sampai = hari ini). Validasi rentang memakai PeriodResolver (maks 366
// hari, tolak mulai > akhir dan akhir di masa depan — SIM-28). Bila tidak
// valid, kembali ke halaman analitik yang sama dengan periode default
// 30 hari + pesan galat di bawah kolom tanggal (ANL-01: "tampilan
// sebelumnya dipertahankan").
// M6 FR-05 (ANL-54): parameter `kategori` opsional (id kategori yang
// harus ada) untuk halaman Aktivitas & Minat; halaman lain tidak
// mengirimnya sehingga aturan ini tidak berpengaruh di sana.
class AnalyticsPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $tz = (string) config('ira.reporting_timezone', 'Asia/Makassar');
        $today = Carbon::now($tz)->toDateString();

        $dari = $this->query('dari');
        $sampai = $this->query('sampai');

        if ($dari === null && $sampai === null) {
            $sampai = $today;
            $dari = Carbon::parse($today, $tz)->subDays(29)->toDateString();
        } elseif ($sampai === null) {
            $sampai = $today;
        } elseif ($dari === null && $this->isDate($sampai)) {
            // Hanya hitung default dari `sampai` bila formatnya tanggal
            // valid — bila tidak, biarkan rules yang menolaknya (jangan
            // sampai Carbon::parse melempar di sini dan jadi 500).
            $dari = Carbon::parse($sampai, $tz)->subDays(29)->toDateString();
        }

        $this->merge(['dari' => $dari, 'sampai' => $sampai]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'dari' => ['required', 'date_format:Y-m-d'],
            'sampai' => ['required', 'date_format:Y-m-d'],
            'kategori' => ['nullable', 'integer', 'exists:categories,id'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            try {
                (new PeriodResolver(
                    (string) config('ira.reporting_timezone', 'Asia/Makassar'),
                ))->resolve(
                    (string) $this->input('dari'),
                    (string) $this->input('sampai'),
                );
            } catch (InvalidArgumentException $e) {
                $validator->errors()->add('dari', $e->getMessage());
            }
        });
    }

    /** @return array{0: string, 1: string} Periode tervalidasi [dari, sampai] (Y-m-d WITA). */
    public function period(): array
    {
        $validated = $this->validated();

        return [(string) $validated['dari'], (string) $validated['sampai']];
    }

    /** Kategori terpilih untuk ANL-54 (null = semua kategori). */
    public function categoryId(): ?int
    {
        $value = $this->validated()['kategori'] ?? null;

        return $value === null ? null : (int) $value;
    }

    protected function failedValidation(Validator $validator): void
    {
        // Periode tidak valid → kembali ke halaman analitik yang sama
        // dengan periode default 30 hari, lengkap dengan pesan galat
        // (bukan kembali ke halaman sebelumnya, yang bisa jadi halaman
        // lain — ANL-01: "tampilan sebelumnya dipertahankan").
        $route = $this->route()?->getName();

        throw new HttpResponseException(
            redirect()->route($route ?: 'admin.dashboard')
                ->withErrors($validator)
                ->withInput(),
        );
    }

    private function isDate(mixed $value): bool
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1;
    }
}
