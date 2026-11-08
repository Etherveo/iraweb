<?php

use App\Simulation\PythonBiodataGenerator;
use App\Simulation\SequentialBiodataGenerator;
use InvalidArgumentException;

// M4 simulator (SIM-04/05/06) — generator biodata. Test double
// (SequentialBiodataGenerator) sepenuhnya deterministik dan tanpa
// shell-out; satu tes integrasi memanggil wrapper Python asli dan
// di-skip bila Python/wrapper tidak tersedia di mesin tes.

test('test double deterministik: seed+offset+count sama → hasil identik', function () {
    $generator = new SequentialBiodataGenerator;

    $first = $generator->generate(5, 0, 42);
    $second = $generator->generate(5, 0, 42);

    expect($second)->toEqual($first);
    expect($generator->lastSeed())->toBe(42);
    expect($first)->toHaveCount(5);
});

test('test double: offset mengiris aliran yang sama (SIM-05)', function () {
    $generator = new SequentialBiodataGenerator;

    $all = $generator->generate(10, 0, 42);
    $slice = $generator->generate(5, 5, 42);

    expect($slice)->toEqual(array_slice($all, 5));
    expect($slice[0]['name'])->toBe('Simulasi 0006');
});

test('test double: nama berurutan, email unik pada domain simulasi', function () {
    $generator = new SequentialBiodataGenerator;

    $items = $generator->generate(10, 0, 42);
    $emails = array_column($items, 'email');

    expect($emails)->toHaveCount(10);
    expect(array_unique($emails))->toHaveCount(10);

    foreach ($emails as $email) {
        expect($email)->toEndWith('@sim.ira-stationery.test');
    }

    expect($items[0]['name'])->toBe('Simulasi 0001');
    expect($items[0]['email'])->toBe('simulasi0001@sim.ira-stationery.test');
    expect($items[9]['name'])->toBe('Simulasi 0010');
    expect($items[9]['username'])->toBe('simulasi0010');
});

test('test double: biodata lengkap dengan alamat Gorontalo', function () {
    $generator = new SequentialBiodataGenerator;

    $item = $generator->generate(1, 0, 42)[0];

    expect($item['gender'])->toBeIn(['laki-laki', 'perempuan']);
    expect($item['birthdate'])->toMatch('/^\d{4}-\d{2}-\d{2}$/');
    expect($item['phone'])->toStartWith('08');
    expect($item['address']['recipient_name'])->toBe($item['name']);
    expect($item['address']['province'])->toBe('Gorontalo');
    expect($item['address']['city'])->toBe('Kota Gorontalo');
    expect($item['address']['district'])->toBe('Kota Selatan');
    expect($item['address']['village'])->toBe('Limba B');
    expect($item['address']['postal_code'])->toBe('96115');
    expect(mb_strlen($item['address']['postal_code']))->toBe(5);
    expect($item['address']['address_type'])->toBe('Rumah');
});

test('test double: seed null dilaporkan sebagai 0 (tetap deterministik)', function () {
    $generator = new SequentialBiodataGenerator;

    $generator->generate(2, 0, null);

    expect($generator->lastSeed())->toBe(0);
});

test('test double: count/offset tidak valid ditolak', function () {
    $generator = new SequentialBiodataGenerator;

    expect(fn () => $generator->generate(0, 0, 42))->toThrow(InvalidArgumentException::class);
    expect(fn () => $generator->generate(2, -1, 42))->toThrow(InvalidArgumentException::class);
});

test('integrasi wrapper Python: deterministik + domain sim + seed dilaporkan', function () {
    $generator = new PythonBiodataGenerator;

    $first = $generator->generate(3, 0, 42);
    $second = $generator->generate(3, 0, 42);

    expect($second)->toEqual($first);
    expect($generator->lastSeed())->toBe(42);
    expect($first)->toHaveCount(3);

    foreach ($first as $item) {
        expect($item['name'])->not->toBe('');
        expect($item['email'])->toEndWith('@sim.ira-stationery.test');
        expect($item['address']['province'])->not->toBe('');
        expect($item['address']['city'])->not->toBe('');
        expect(mb_strlen($item['address']['postal_code']))->toBe(5);
        expect($item['address']['street_address'])->not->toBe('');
    }

    // Mode random: seed dipilih wrapper dan dilaporkan (bukan null).
    $random = $generator->generate(3, 0, null);

    expect($generator->lastSeed())->toBeInt();
    expect($random)->toHaveCount(3);

    foreach ($random as $item) {
        expect($item['email'])->toEndWith('@sim.ira-stationery.test');
    }
})->skip(function (): bool {
    // Di-skip bila Python atau wrapper tidak ada di mesin tes (mis.
    // CI tanpa Python). Di laptop Ren keduanya ada, jadi tes ini jalan.
    $binary = PHP_OS_FAMILY === 'Windows' ? 'python' : 'python3';
    $version = trim((string) shell_exec($binary.' --version 2>&1'));

    return $version === '' || ! file_exists(base_path('python/biodata/generate_biodata.py'));
}, 'Python atau wrapper generate_biodata.py tidak tersedia di mesin ini.');

test('integrasi wrapper Python: binary kosong di config jatuh ke default OS', function () {
    // Regresi 2026-11-08: SIM_GENERATOR_PYTHON= (kosong) di .env membuat
    // config berisi '' dan Process mencoba menjalankan "" — Windows
    // menjawab '""' is not recognized as a command. Kosong harus
    // berarti "pakai default OS", sama seperti bila variabel tak ada.
    config(['simulation.generator_python' => '']);

    $generator = new PythonBiodataGenerator;

    $items = $generator->generate(1, 0, 42);

    expect($items)->toHaveCount(1);
    expect($items[0]['email'])->toEndWith('@sim.ira-stationery.test');
})->skip(function (): bool {
    $binary = PHP_OS_FAMILY === 'Windows' ? 'python' : 'python3';
    $version = trim((string) shell_exec($binary.' --version 2>&1'));

    return $version === '' || ! file_exists(base_path('python/biodata/generate_biodata.py'));
}, 'Python atau wrapper generate_biodata.py tidak tersedia di mesin ini.');
