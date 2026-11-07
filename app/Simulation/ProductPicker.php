<?php

namespace App\Simulation;

use InvalidArgumentException;

// M4 simulator (SIM-11/22, blueprint §9.4). Picks DISTINCT products for
// one order (no duplicate product per order, SIM-11), driven by one
// SeededRandom stream. Inputs must be sorted by id (blueprint §9.2).
final class ProductPicker
{
    /**
     * Round-robin cursor shared across calls, so 'even' spreads products
     * over the whole plan — not just inside one order.
     */
    private int $evenCursor = 0;

    public function __construct(private SeededRandom $random) {}

    /**
     * @param  list<array{id:int, price_idr:int}> $products sorted by id.
     * @return list<array{id:int, price_idr:int}> in pick order.
     *
     * @throws InvalidArgumentException on an unknown mode, or when more
     *   distinct products are asked than the pool holds.
     */
    public function pickDistinct(int $count, array $products, string $mode): array
    {
        if ($count < 1) {
            throw new InvalidArgumentException('Jumlah produk harus ≥ 1.');
        }

        if ($products === []) {
            throw new InvalidArgumentException('Tidak ada produk untuk dipilih.');
        }

        if ($count > count($products)) {
            throw new InvalidArgumentException("Tidak dapat memilih {$count} produk berbeda dari ".count($products).' produk.');
        }

        return match ($mode) {
            'random' => $this->randomDistinct($count, $products),
            'even' => $this->evenDistinct($count, $products),
            'weighted' => $this->weightedDistinct($count, $products),
            default => throw new InvalidArgumentException("Distribusi produk tidak dikenal: '{$mode}'."),
        };
    }

    /**
     * Seeded shuffle, take the first $count: uniform without replacement.
     *
     * @return list<array{id:int, price_idr:int}>
     */
    private function randomDistinct(int $count, array $products): array
    {
        return array_slice($this->random->shuffle($products), 0, $count);
    }

    /** @return list<array{id:int, price_idr:int}> */
    private function evenDistinct(int $count, array $products): array
    {
        $n = count($products);
        $out = [];

        for ($i = 0; $i < $count; $i++) {
            $out[] = $products[$this->evenCursor % $n];
            $this->evenCursor++;
        }

        return $out;
    }

    /**
     * Blueprint §9.4: weight 1/rank over a seeded shuffle; a picked
     * product leaves the pool, and ranks are re-derived from the
     * remaining positions on every draw.
     *
     * @return list<array{id:int, price_idr:int}>
     */
    private function weightedDistinct(int $count, array $products): array
    {
        $pool = array_values($this->random->shuffle($products));
        $out = [];

        while (count($out) < $count) {
            $total = 0.0;

            foreach ($pool as $pos => $product) {
                $total += 1.0 / ($pos + 1);
            }

            $draw = $this->random->nextFloat() * $total;
            $acc = 0.0;
            $pickedPos = count($pool) - 1;

            foreach ($pool as $pos => $product) {
                $acc += 1.0 / ($pos + 1);

                if ($draw < $acc) {
                    $pickedPos = $pos;
                    break;
                }
            }

            $out[] = $pool[$pickedPos];
            array_splice($pool, $pickedPos, 1);
        }

        return $out;
    }
}
