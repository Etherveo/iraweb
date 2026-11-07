<?php

namespace App\Simulation;

use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use JsonException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;
use Throwable;

// M4 simulator (SIM-06, blueprint §9.3). The real BiodataGenerator:
// runs iraweb/python/biodata/generate_biodata.py as a CLI subprocess
// (no second service to operate — rencana §3.1), reads the single JSON
// object from stdout, and validates its shape, the sim email domain,
// and in-batch uniqueness. Database collisions are NOT checked here —
// SimulationRequestValidator::validateBiodataEmails owns those (the
// service layer decides deterministic-reject vs random-retry).
//
// Seed contract (U-02, rencana §3.1): a given seed is passed through
// as --seed (deterministic); a null seed runs the wrapper in random
// mode and the wrapper reports the seed it used — lastSeed() returns
// it, so the caller can store it on the run (blueprint §9.2).
final class PythonBiodataGenerator implements BiodataGenerator
{
    private ?int $lastSeed = null;

    public function generate(int $count, int $offset, ?int $seed): array
    {
        if ($count < 1) {
            throw new InvalidArgumentException('Jumlah biodata harus ≥ 1.');
        }

        if ($offset < 0) {
            throw new InvalidArgumentException('Offset harus ≥ 0.');
        }

        $binary = (string) config('simulation.generator_python', PHP_OS_FAMILY === 'Windows' ? 'python' : 'python3');
        $path = (string) config('simulation.generator_path');
        $timeout = (int) config('simulation.generator_timeout', 120);

        $command = [$binary, $path, '--count', (string) $count, '--offset', (string) $offset];

        if ($seed !== null) {
            $command[] = '--seed';
            $command[] = (string) $seed;
        } else {
            $command[] = '--mode';
            $command[] = 'random';
        }

        $process = new Process($command);
        $process->setTimeout(max(1, $timeout));

        try {
            $process->run();
        } catch (ProcessTimedOutException $e) {
            throw ValidationException::withMessages([
                'generator' => 'Generator biodata kehabisan waktu ('.$timeout.' detik). Coba jumlah akun yang lebih kecil.',
            ]);
        } catch (Throwable $e) {
            throw ValidationException::withMessages([
                'generator' => 'Generator biodata gagal dijalankan: '.$this->trimDetail($e->getMessage()),
            ]);
        }

        if (! $process->isSuccessful()) {
            throw ValidationException::withMessages([
                'generator' => 'Generator biodata gagal: '.$this->trimDetail($process->getErrorOutput() ?: 'proses keluar dengan kode '.$process->getExitCode().'.'),
            ]);
        }

        $decoded = $this->decodeOutput($process->getOutput());

        $this->validateShape($decoded, $count);

        $this->lastSeed = (int) $decoded['seed'];

        return array_map($this->normalizeItem(...), $decoded['items']);
    }

    /** Effective seed of the last generate() call (null before any call). */
    public function lastSeed(): ?int
    {
        return $this->lastSeed;
    }

    /** @return array{seed:int, count:int, offset:int, items:list<array>} */
    private function decodeOutput(string $output): array
    {
        try {
            $decoded = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw ValidationException::withMessages([
                'generator' => 'Generator biodata mengembalikan keluaran yang bukan JSON valid.',
            ]);
        }

        if (! is_array($decoded) || ! isset($decoded['items']) || ! is_array($decoded['items']) || ! isset($decoded['seed'])) {
            throw ValidationException::withMessages([
                'generator' => 'Generator biodata mengembalikan bentuk JSON yang tidak dikenal.',
            ]);
        }

        return $decoded;
    }

    /** Shape + sim-domain + in-batch uniqueness (no database checks here). */
    private function validateShape(array $decoded, int $count): void
    {
        if (count($decoded['items']) !== $count) {
            throw ValidationException::withMessages([
                'generator' => 'Generator biodata mengembalikan '.count($decoded['items']).' baris, diminta '.$count.'.',
            ]);
        }

        $domain = mb_strtolower((string) config('simulation.email_domain'));
        $seen = [];

        foreach ($decoded['items'] as $index => $item) {
            $label = 'Biodata #'.($index + 1);

            if (! is_array($item) || ! isset($item['name']) || ! is_string($item['name']) || trim($item['name']) === '') {
                throw ValidationException::withMessages([
                    'generator' => $label.' tidak memiliki nama.',
                ]);
            }

            $email = isset($item['email']) ? mb_strtolower(trim((string) $item['email'])) : '';

            if ($email === '' || ! str_ends_with($email, '@'.$domain)) {
                throw ValidationException::withMessages([
                    'generator' => $label." tidak memiliki email pada domain simulasi (@{$domain}).",
                ]);
            }

            if (isset($seen[$email])) {
                throw ValidationException::withMessages([
                    'generator' => "Email '{$email}' muncul lebih dari sekali di hasil generator.",
                ]);
            }

            $seen[$email] = true;

            if (! isset($item['address']) || ! is_array($item['address'])) {
                throw ValidationException::withMessages([
                    'generator' => $label.' tidak memiliki alamat.',
                ]);
            }

            foreach (['recipient_name', 'province', 'city', 'district', 'village', 'postal_code', 'street_address', 'address_type'] as $key) {
                if (! isset($item['address'][$key]) || ! is_string($item['address'][$key]) || trim($item['address'][$key]) === '') {
                    throw ValidationException::withMessages([
                        'generator' => $label." alamatnya tidak lengkap (kolom '{$key}').",
                    ]);
                }
            }

            if (mb_strlen($item['address']['postal_code']) !== 5) {
                throw ValidationException::withMessages([
                    'generator' => $label.' kode posnya bukan 5 karakter.',
                ]);
            }

            if (isset($item['gender']) && $item['gender'] !== null && ! in_array($item['gender'], ['laki-laki', 'perempuan'], true)) {
                throw ValidationException::withMessages([
                    'generator' => $label.' gendernya tidak dikenal.',
                ]);
            }

            if (isset($item['birthdate']) && $item['birthdate'] !== null
                && (! is_string($item['birthdate']) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $item['birthdate']))) {
                throw ValidationException::withMessages([
                    'generator' => $label.' tanggal lahirnya bukan format Y-m-d.',
                ]);
            }
        }
    }

    /** Normalize one wrapper item to the exact BiodataGenerator shape. */
    private function normalizeItem(array $item): array
    {
        return [
            'name' => (string) $item['name'],
            'email' => (string) $item['email'],
            'username' => isset($item['username']) && $item['username'] !== '' ? (string) $item['username'] : null,
            'phone' => isset($item['phone']) && $item['phone'] !== '' ? (string) $item['phone'] : null,
            'gender' => $item['gender'] ?? null,
            'birthdate' => $item['birthdate'] ?? null,
            'address' => [
                'recipient_name' => (string) $item['address']['recipient_name'],
                'recipient_phone' => isset($item['address']['recipient_phone']) && $item['address']['recipient_phone'] !== '' ? (string) $item['address']['recipient_phone'] : null,
                'province' => (string) $item['address']['province'],
                'city' => (string) $item['address']['city'],
                'district' => (string) $item['address']['district'],
                'village' => (string) $item['address']['village'],
                'postal_code' => (string) $item['address']['postal_code'],
                'street_address' => (string) $item['address']['street_address'],
                'address_type' => (string) $item['address']['address_type'],
            ],
        ];
    }

    private function trimDetail(string $detail): string
    {
        $detail = trim(preg_replace('/\s+/', ' ', $detail) ?? '');

        return mb_strlen($detail) > 500 ? mb_substr($detail, 0, 500).'…' : $detail;
    }
}
