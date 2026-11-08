<?php

namespace App\Http\Requests;

use App\Simulation\PeriodResolver;
use Carbon\Carbon;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use InvalidArgumentException;

// M6 FR-04 (ANL-41..46): filter dua periode untuk halaman Perbandingan.
// Query GET `dari`/`sampai` (periode A) + `pembanding_dari`/
// `pembanding_sampai` (periode B) + `pembanding=sebelumnya` (ANL-46:
// isi B dengan periode berpanjang sama tepat sebelum A).
// Tanpa parameter, A = 30 hari terakhir dan B = 30 hari sebelumnya
// (keputusan M6 §3.1: B default = ANL-46, sama seperti KPI dashboard
// M5). Panjang berbeda atau tumpang tindih TIDAK ditolak (ANL-42) —
// modul memberi peringatan eksplisit di `warnings`. Masing-masing
// periode divalidasi sendiri oleh PeriodResolver (maks 366 hari, tolak
// mulai > akhir dan akhir di masa depan — SIM-28).
class AnalyticsCompareRequest extends FormRequest
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

        // Periode B: 'sebelumnya' atau tanpa parameter → periode
        // berpanjang sama tepat sebelum A (ANL-46). Bila hanya satu
        // tanggal B diisi, tanggal lainnya dihitung dari panjang A.
        // Bila A tidak valid, B diisi dari jendela default 30 hari
        // supaya galat hanya menunjuk A, bukan B yang tidak pernah
        // diisi pengguna.
        $aValid = $this->isDate($dari) && $this->isDate($sampai);
        $bDari = $this->query('pembanding_dari');
        $bSampai = $this->query('pembanding_sampai');

        if ($this->query('pembanding') === 'sebelumnya' || ($bDari === null && $bSampai === null)) {
            if ($aValid) {
                [$bDari, $bSampai] = $this->previousPeriod((string) $dari, (string) $sampai);
            } else {
                $defaultDari = Carbon::parse($today, $tz)->subDays(29)->toDateString();
                [$bDari, $bSampai] = $this->previousPeriod($defaultDari, $today);
            }

            $this->merge(['pembanding_dari' => $bDari, 'pembanding_sampai' => $bSampai]);
        } elseif ($aValid && $bSampai === null && $this->isDate($bDari)) {
            $days = $this->periodDays((string) $dari, (string) $sampai);
            $this->merge(['pembanding_sampai' => Carbon::parse($bDari, $tz)->addDays($days - 1)->toDateString()]);
        } elseif ($aValid && $bDari === null && $this->isDate($bSampai)) {
            $days = $this->periodDays((string) $dari, (string) $sampai);
            $this->merge(['pembanding_dari' => Carbon::parse($bSampai, $tz)->subDays($days - 1)->toDateString()]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'dari' => ['required', 'date_format:Y-m-d'],
            'sampai' => ['required', 'date_format:Y-m-d'],
            'pembanding_dari' => ['required', 'date_format:Y-m-d'],
            'pembanding_sampai' => ['required', 'date_format:Y-m-d'],
            'pembanding' => ['nullable', 'in:sebelumnya'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $resolver = new PeriodResolver(
                (string) config('ira.reporting_timezone', 'Asia/Makassar'),
            );

            try {
                $resolver->resolve(
                    (string) $this->input('dari'),
                    (string) $this->input('sampai'),
                );
            } catch (InvalidArgumentException $e) {
                $validator->errors()->add('dari', $e->getMessage());
            }

            try {
                $resolver->resolve(
                    (string) $this->input('pembanding_dari'),
                    (string) $this->input('pembanding_sampai'),
                );
            } catch (InvalidArgumentException $e) {
                $validator->errors()->add('pembanding_dari', $e->getMessage());
            }
        });
    }

    /**
     * Dua periode tervalidasi [[dari A, sampai A], [dari B, sampai B]]
     * (Y-m-d WITA).
     *
     * @return array{0: array{0: string, 1: string}, 1: array{0: string, 1: string}}
     */
    public function periods(): array
    {
        $validated = $this->validated();

        return [
            [(string) $validated['dari'], (string) $validated['sampai']],
            [(string) $validated['pembanding_dari'], (string) $validated['pembanding_sampai']],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        // Periode tidak valid → kembali ke halaman perbandingan dengan
        // periode default 30 hari, lengkap dengan pesan galat (pola
        // AnalyticsPeriodRequest, ANL-01).
        $route = $this->route()?->getName();

        throw new HttpResponseException(
            redirect()->route($route ?: 'admin.perbandingan')
                ->withErrors($validator)
                ->withInput(),
        );
    }

    // Periode sebelumnya dengan panjang yang sama, tepat sebelum periode
    // ini — sama seperti AnalyticsController::previousPeriod (K8/ANL-46).
    /** @return array{0: string, 1: string} */
    private function previousPeriod(string $dari, string $sampai): array
    {
        $start = Carbon::parse($dari);
        $end = Carbon::parse($sampai);
        // Carbon 3 diffInDays() mengembalikan float — floor seperti
        // PeriodResolver, supaya subDays() mendapat bilangan bulat.
        $days = (int) floor($start->diffInDays($end)) + 1;

        return [
            $start->copy()->subDays($days)->toDateString(),
            $end->copy()->subDays($days)->toDateString(),
        ];
    }

    private function periodDays(string $dari, string $sampai): int
    {
        return (int) floor(Carbon::parse($dari)->diffInDays(Carbon::parse($sampai))) + 1;
    }

    private function isDate(mixed $value): bool
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1;
    }
}
