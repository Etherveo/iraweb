<?php

namespace App\Simulation;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

// M4 simulator (SIM-20, blueprint §9.4). Picks booking instants inside
// a UTC range, driven by one SeededRandom stream. Returned instants are
// always ascending, so plan orders read chronologically.
final class TimeDistributor
{
    public function __construct(private SeededRandom $random) {}

    /**
     * @return list<CarbonImmutable> booking instants (UTC), ascending.
     *
     * @throws InvalidArgumentException on an unknown mode, an empty range,
     *   or a single point outside the range / missing for mode 'single'.
     */
    public function distribute(
        int $count,
        CarbonImmutable $startUtc,
        CarbonImmutable $endUtc,
        string $mode,
        ?CarbonImmutable $singlePoint = null,
    ): array {
        if ($count < 1) {
            throw new InvalidArgumentException('Jumlah transaksi harus ≥ 1.');
        }

        if ($endUtc < $startUtc) {
            throw new InvalidArgumentException('Akhir rentang waktu tidak boleh sebelum awal rentang.');
        }

        $span = $endUtc->getTimestamp() - $startUtc->getTimestamp();

        $times = match ($mode) {
            'random' => $this->randomTimes($count, $startUtc, $span),
            'even' => $this->evenTimes($count, $startUtc, $span),
            'single' => $this->singleTimes($count, $startUtc, $endUtc, $singlePoint),
            default => throw new InvalidArgumentException("Distribusi waktu tidak dikenal: '{$mode}'."),
        };

        usort($times, fn (CarbonImmutable $a, CarbonImmutable $b): int => $a->getTimestamp() <=> $b->getTimestamp());

        return $times;
    }

    /** @return list<CarbonImmutable> */
    private function randomTimes(int $count, CarbonImmutable $startUtc, int $span): array
    {
        $times = [];

        for ($i = 0; $i < $count; $i++) {
            $times[] = $startUtc->addSeconds($this->random->nextInt(0, $span));
        }

        return $times;
    }

    /**
     * Equal quantiles (blueprint §9.4): the i-th of n sits at the middle
     * of the i-th slice. Purely deterministic — consumes no RNG draws.
     *
     * @return list<CarbonImmutable>
     */
    private function evenTimes(int $count, CarbonImmutable $startUtc, int $span): array
    {
        $times = [];

        for ($i = 0; $i < $count; $i++) {
            $times[] = $startUtc->addSeconds((int) floor(($i + 0.5) * $span / $count));
        }

        return $times;
    }

    /** @return list<CarbonImmutable> */
    private function singleTimes(
        int $count,
        CarbonImmutable $startUtc,
        CarbonImmutable $endUtc,
        ?CarbonImmutable $singlePoint,
    ): array {
        if ($singlePoint === null) {
            throw new InvalidArgumentException("Distribusi 'satu titik' membutuhkan tanggal+jam.");
        }

        if ($singlePoint < $startUtc || $singlePoint > $endUtc) {
            throw new InvalidArgumentException('Titik waktu tunggal harus berada di dalam periode.');
        }

        return array_fill(0, $count, $singlePoint);
    }
}
