<?php

use App\Simulation\SeededRandom;

test('seed yang sama menghasilkan urutan yang sama', function () {
    $a = new SeededRandom(42);
    $b = new SeededRandom(42);

    $floatsA = [];
    $floatsB = [];

    for ($i = 0; $i < 20; $i++) {
        $floatsA[] = $a->nextFloat();
        $floatsB[] = $b->nextFloat();
    }

    expect($floatsA)->toEqual($floatsB);

    $a = new SeededRandom(7);
    $b = new SeededRandom(7);

    $intsA = [];
    $intsB = [];

    for ($i = 0; $i < 20; $i++) {
        $intsA[] = $a->nextInt(1, 100);
        $intsB[] = $b->nextInt(1, 100);
    }

    expect($intsA)->toEqual($intsB);
});

test('seed yang berbeda menghasilkan urutan yang berbeda', function () {
    $a = new SeededRandom(42);
    $b = new SeededRandom(43);

    $seqA = [];
    $seqB = [];

    for ($i = 0; $i < 10; $i++) {
        $seqA[] = $a->nextFloat();
        $seqB[] = $b->nextFloat();
    }

    expect($seqA)->not->toEqual($seqB);
});

test('nextInt selalu di dalam rentang, kedua ujung inklusif', function () {
    $random = new SeededRandom(123);

    for ($i = 0; $i < 1000; $i++) {
        expect($random->nextInt(1, 3))
            ->toBeGreaterThanOrEqual(1)
            ->toBeLessThanOrEqual(3);
    }

    expect((new SeededRandom(1))->nextInt(5, 5))->toBe(5);
    expect((new SeededRandom(1))->nextInt(-10, -5))->toBeGreaterThanOrEqual(-10)->toBeLessThanOrEqual(-5);
});

test('nextInt menolak min lebih besar dari max', function () {
    expect(fn () => (new SeededRandom(1))->nextInt(5, 4))->toThrow(InvalidArgumentException::class);
});

test('nextFloat selalu di [0, 1)', function () {
    $random = new SeededRandom(99);

    for ($i = 0; $i < 1000; $i++) {
        expect($random->nextFloat())
            ->toBeGreaterThanOrEqual(0.0)
            ->toBeLessThan(1.0);
    }
});

test('shuffle deterministik dan merupakan permutasi lengkap', function () {
    $items = [1, 2, 3, 4, 5, 6, 7, 8];

    $a = (new SeededRandom(42))->shuffle($items);
    $b = (new SeededRandom(42))->shuffle($items);

    expect($a)->toEqual($b);

    $sorted = $a;
    sort($sorted);

    expect($sorted)->toEqual($items);

    // Input tidak diubah.
    expect($items)->toEqual([1, 2, 3, 4, 5, 6, 7, 8]);

    expect((new SeededRandom(42))->shuffle([]))->toBe([]);
});

test('pick mengembalikan anggota daftar dan menolak daftar kosong', function () {
    $random = new SeededRandom(5);

    for ($i = 0; $i < 50; $i++) {
        expect($random->pick(['a', 'b', 'c']))->toBeIn(['a', 'b', 'c']);
    }

    expect(fn () => (new SeededRandom(1))->pick([]))->toThrow(InvalidArgumentException::class);
});

test('seed negatif dipetakan ke padanan 32-bit-nya', function () {
    $a = new SeededRandom(-1);
    $b = new SeededRandom(0xFFFFFFFF);

    expect($a->nextFloat())->toEqual($b->nextFloat());
    expect($a->nextInt(1, 100))->toEqual($b->nextInt(1, 100));
});

test('seed nol valid', function () {
    $random = new SeededRandom(0);

    expect($random->nextFloat())->toBeGreaterThanOrEqual(0.0)->toBeLessThan(1.0);
});
