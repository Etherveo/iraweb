<?php

namespace App\Simulation;

use InvalidArgumentException;

// M4 simulator (SIM-21, blueprint §9.4). Assigns one user id per
// transaction, driven by one SeededRandom stream. Inputs must be sorted
// ascending (blueprint §9.2); 'even' is round-robin over that order.
final class UserDistributor
{
    public function __construct(private SeededRandom $random) {}

    /**
     * @param  list<int> $userIds sorted ascending, ≥ 1 entry.
     * @return list<int> one user id per transaction, in transaction order.
     *
     * @throws InvalidArgumentException on an unknown mode or empty input.
     */
    public function distribute(int $count, array $userIds, string $mode): array
    {
        if ($count < 1) {
            throw new InvalidArgumentException('Jumlah transaksi harus ≥ 1.');
        }

        if ($userIds === []) {
            throw new InvalidArgumentException('Tidak ada akun untuk didistribusikan.');
        }

        return match ($mode) {
            'random' => $this->random($count, $userIds),
            'even' => $this->even($count, $userIds),
            'weighted' => $this->weighted($count, $userIds),
            default => throw new InvalidArgumentException("Distribusi user tidak dikenal: '{$mode}'."),
        };
    }

    /** @return list<int> */
    private function random(int $count, array $userIds): array
    {
        $out = [];

        for ($i = 0; $i < $count; $i++) {
            $out[] = $this->random->pick($userIds);
        }

        return $out;
    }

    /**
     * Round-robin over the sorted list: counts per user differ by ≤ 1
     * (SIM-21). Consumes no RNG draws.
     *
     * @return list<int>
     */
    private function even(int $count, array $userIds): array
    {
        $out = [];
        $n = count($userIds);

        for ($i = 0; $i < $count; $i++) {
            $out[] = $userIds[$i % $n];
        }

        return $out;
    }

    /**
     * Blueprint §9.4: weight 1/rank over a seeded shuffle, so a few
     * users dominate. Rank 1 is the first id after the shuffle.
     *
     * @return list<int>
     */
    private function weighted(int $count, array $userIds): array
    {
        $shuffled = $this->random->shuffle($userIds);

        $weights = [];
        $total = 0.0;

        foreach ($shuffled as $index => $id) {
            $w = 1.0 / ($index + 1);
            $weights[] = [$id, $w];
            $total += $w;
        }

        $out = [];

        for ($i = 0; $i < $count; $i++) {
            $draw = $this->random->nextFloat() * $total;
            $acc = 0.0;
            $picked = $shuffled[count($shuffled) - 1];

            foreach ($weights as [$id, $w]) {
                $acc += $w;

                if ($draw < $acc) {
                    $picked = $id;
                    break;
                }
            }

            $out[] = $picked;
        }

        return $out;
    }
}
