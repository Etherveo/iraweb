<?php

namespace App\Simulation;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

// M4 simulator (SIM-06, blueprint §9.3). Test double for
// BiodataGenerator: sequential, deterministic, no shell-out to Python
// — Pest uses this by default, so tests never depend on a Python
// binary. Names run "Simulasi 0001", "Simulasi 0002", … starting at
// offset + 1; the fixed address follows the AddressFactory pattern
// (Gorontalo). A null seed behaves like seed 0 (still deterministic);
// lastSeed() reports the effective seed.
final class SequentialBiodataGenerator implements BiodataGenerator
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

        $this->lastSeed = $seed ?? 0;

        $domain = (string) config('simulation.email_domain');
        $items = [];

        for ($i = 0; $i < $count; $i++) {
            $n = $offset + $i + 1;
            $number = str_pad((string) $n, 4, '0', STR_PAD_LEFT);
            $name = 'Simulasi '.$number;
            $phone = '081234'.str_pad((string) $n, 6, '0', STR_PAD_LEFT);

            $items[] = [
                'name' => $name,
                'email' => 'simulasi'.$number.'@'.$domain,
                'username' => 'simulasi'.$number,
                'phone' => $phone,
                'gender' => $n % 2 === 1 ? 'laki-laki' : 'perempuan',
                'birthdate' => CarbonImmutable::create(1990, 1, 1)->addDays($n - 1)->format('Y-m-d'),
                'address' => [
                    'recipient_name' => $name,
                    'recipient_phone' => $phone,
                    'province' => 'Gorontalo',
                    'city' => 'Kota Gorontalo',
                    'district' => 'Kota Selatan',
                    'village' => 'Limba B',
                    'postal_code' => '96115',
                    'street_address' => 'Jl. Jend. Sudirman No. '.((($n - 1) % 200) + 1),
                    'address_type' => 'Rumah',
                ],
            ];
        }

        return $items;
    }

    /** Effective seed of the last generate() call (null before any call). */
    public function lastSeed(): ?int
    {
        return $this->lastSeed;
    }
}
