<?php

namespace App\Simulation;

use InvalidArgumentException;

// M4 simulator (SIM-05, blueprint §9.2). Deterministic PRNG implemented
// in-house so plans stay stable across PHP versions: the built-in
// mt_rand()/random_int() behaviour may change, this one may not.
// Algorithm: mulberry32 on unsigned 32-bit state. PHP ints are 64-bit,
// so every 32-bit multiply goes through imul32() (16-bit split) to keep
// the low 32 bits exact without float overflow.
final class SeededRandom
{
    private int $state;

    public function __construct(int $seed)
    {
        // Mask to unsigned 32-bit; a negative seed maps to its
        // two's-complement equivalent, and seed 0 is valid.
        $this->state = $seed & 0xFFFFFFFF;
    }

    /** Next float in [0, 1). */
    public function nextFloat(): float
    {
        $this->state = ($this->state + 0x6D2B79F5) & 0xFFFFFFFF;

        $t = $this->state;
        $t = $this->imul32($t ^ ($t >> 15), 1 | $t);
        $t = (($t + $this->imul32($t ^ ($t >> 7), 61 | $t)) & 0xFFFFFFFF) ^ $t;
        $t ^= $t >> 14;

        return ($t & 0xFFFFFFFF) / 4294967296.0;
    }

    /** Next integer in [$min, $max], both ends inclusive. */
    public function nextInt(int $min, int $max): int
    {
        if ($min > $max) {
            throw new InvalidArgumentException("Batas bawah ({$min}) tidak boleh lebih besar dari batas atas ({$max}).");
        }

        // nextFloat() < 1, so the result never exceeds $max.
        return $min + (int) floor($this->nextFloat() * ($max - $min + 1));
    }

    /** Uniform pick from a non-empty list. */
    public function pick(array $items): mixed
    {
        if ($items === []) {
            throw new InvalidArgumentException('Tidak dapat memilih dari daftar kosong.');
        }

        $values = array_values($items);

        return $values[$this->nextInt(0, count($values) - 1)];
    }

    /**
     * Fisher-Yates shuffle. Returns a new list (input untouched),
     * reindexed 0..n-1, containing exactly the input values.
     */
    public function shuffle(array $items): array
    {
        $values = array_values($items);

        for ($i = count($values) - 1; $i > 0; $i--) {
            $j = $this->nextInt(0, $i);
            [$values[$i], $values[$j]] = [$values[$j], $values[$i]];
        }

        return $values;
    }

    /**
     * Low 32 bits of $a * $b (emulates Math.imul). Both inputs are masked
     * to 32 bits first; the 16-bit split keeps every partial product
     * below 2^33, so there is no float overflow on 64-bit PHP ints.
     */
    private function imul32(int $a, int $b): int
    {
        $a &= 0xFFFFFFFF;
        $b &= 0xFFFFFFFF;

        $lo = ($a & 0xFFFF) * ($b & 0xFFFF);
        $mid = (($a >> 16) * ($b & 0xFFFF)) + (($a & 0xFFFF) * ($b >> 16));

        return ($lo + (($mid & 0xFFFF) << 16)) & 0xFFFFFFFF;
    }
}
