<?php

use App\Simulation\SeededRandom;
use App\Simulation\TimeDistributor;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

// M7 SIM-34: mode rising/falling + regresi mode lama (random/even/single
// tidak boleh berubah untuk seed yang sama). Nilai emas mode random
// dihitung dari mulberry32 (SeededRandom) dan diverifikasi silang dengan
// implementasi Node Math.imul: seed 20261101, span 86400 detik, 5 titik
// → offset terurut [36273, 42391, 58229, 65026, 77433].

function m7tDistributor(int $seed = 20261101): TimeDistributor
{
    return new TimeDistributor(new SeededRandom($seed));
}

function m7tRange(): array
{
    return [
        CarbonImmutable::parse('2026-10-01 00:00:00', 'UTC'),
        CarbonImmutable::parse('2026-10-02 00:00:00', 'UTC'),
    ];
}

/** Offset detik dari awal rentang, terurut. */
function m7tOffsets(array $times, CarbonImmutable $start): array
{
    return array_map(fn (CarbonImmutable $t): int => $t->getTimestamp() - $start->getTimestamp(), $times);
}

test('M7 SIM-34 rising deterministik, terurut, dan kerapatannya naik', function () {
    [$start, $end] = m7tRange();

    $a = m7tDistributor()->distribute(1000, $start, $end, 'rising');
    $b = m7tDistributor()->distribute(1000, $start, $end, 'rising');

    expect(m7tOffsets($a, $start))->toEqual(m7tOffsets($b, $start));

    $offsets = m7tOffsets($a, $start);
    $sorted = $offsets;
    sort($sorted);
    expect($offsets)->toEqual($sorted);
    expect(min($offsets))->toBeGreaterThanOrEqual(0);
    expect(max($offsets))->toBeLessThanOrEqual(86400);

    // Kerapatan linear naik f(u) = 2u: kuartal pertama ≈ 6% titik,
    // kuartal terakhir ≈ 44% — margin lebar supaya tes stabil.
    $firstQuarter = count(array_filter($offsets, fn (int $o): bool => $o < 21600));
    $lastQuarter = count(array_filter($offsets, fn (int $o): bool => $o >= 64800));
    expect($firstQuarter)->toBeLessThan(150);
    expect($lastQuarter)->toBeGreaterThan(300);
    expect($firstQuarter)->toBeLessThan($lastQuarter);
});

test('M7 SIM-34 falling deterministik, terurut, dan kerapatannya turun', function () {
    [$start, $end] = m7tRange();

    $a = m7tDistributor()->distribute(1000, $start, $end, 'falling');
    $b = m7tDistributor()->distribute(1000, $start, $end, 'falling');

    expect(m7tOffsets($a, $start))->toEqual(m7tOffsets($b, $start));

    $offsets = m7tOffsets($a, $start);
    $sorted = $offsets;
    sort($sorted);
    expect($offsets)->toEqual($sorted);
    expect(min($offsets))->toBeGreaterThanOrEqual(0);
    expect(max($offsets))->toBeLessThanOrEqual(86400);

    $firstQuarter = count(array_filter($offsets, fn (int $o): bool => $o < 21600));
    $lastQuarter = count(array_filter($offsets, fn (int $o): bool => $o >= 64800));
    expect($firstQuarter)->toBeGreaterThan(300);
    expect($lastQuarter)->toBeLessThan(150);
    expect($firstQuarter)->toBeGreaterThan($lastQuarter);
});

test('M7 SIM-34 mode lama random tidak berubah untuk seed yang sama (regresi)', function () {
    [$start, $end] = m7tRange();

    $times = m7tDistributor()->distribute(5, $start, $end, 'random');

    expect(m7tOffsets($times, $start))->toEqual([36273, 42391, 58229, 65026, 77433]);
});

test('M7 SIM-34 mode lama even tidak berubah untuk seed yang sama (regresi)', function () {
    $start = CarbonImmutable::parse('2026-10-01 00:00:00', 'UTC');
    $end = CarbonImmutable::parse('2026-10-01 01:00:00', 'UTC');

    // Kuantil tengah tiap potongan: (i + 0,5) * 3600 / 4.
    $times = m7tDistributor()->distribute(4, $start, $end, 'even');

    expect(m7tOffsets($times, $start))->toEqual([450, 1350, 2250, 3150]);
});

test('M7 SIM-34 mode lama single tidak berubah (regresi)', function () {
    [$start, $end] = m7tRange();
    $point = CarbonImmutable::parse('2026-10-01 12:00:00', 'UTC');

    $times = m7tDistributor()->distribute(3, $start, $end, 'single', $point);

    expect($times)->toHaveCount(3);
    foreach ($times as $time) {
        expect($time->getTimestamp())->toBe($point->getTimestamp());
    }
});

test('M7 SIM-34 mode waktu tidak dikenal tetap ditolak', function () {
    [$start, $end] = m7tRange();

    expect(fn () => m7tDistributor()->distribute(2, $start, $end, 'zigzag'))
        ->toThrow(InvalidArgumentException::class);
});
