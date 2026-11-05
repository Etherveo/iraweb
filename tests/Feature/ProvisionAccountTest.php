<?php

use App\Models\Developer;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

// ACC-05: provisioning is artisan-only, never public registration.
// Passwords are hashed, never printed. Re-running without --force does
// not overwrite. Provisioning never converts a buyer into an admin.
test('ACC-05 provision creates admin and developer accounts with hashed passwords', function () {
    $this->artisan('ira:provision-account', [
        'role' => 'admin_toko',
        'email' => 'admin@contoh.test',
        'name' => 'Admin Satu',
        '--password' => 'rahasia123',
    ])->assertExitCode(0);

    $this->artisan('ira:provision-account', [
        'role' => 'developer',
        'email' => 'dev@contoh.test',
        'name' => 'Dev Satu',
        '--password' => 'rahasia123',
    ])->assertExitCode(0);

    $admin = User::where('email', 'admin@contoh.test')->first();
    expect($admin->role)->toBe('admin_toko');
    expect($admin->is_active)->toBeTrue();
    expect(Hash::check('rahasia123', $admin->password))->toBeTrue();

    $developer = Developer::where('email', 'dev@contoh.test')->first();
    expect($developer)->not->toBeNull();
    expect(Hash::check('rahasia123', $developer->password))->toBeTrue();
});

test('ACC-05 re-running without force fails and does not overwrite the password', function () {
    $this->artisan('ira:provision-account', [
        'role' => 'admin_toko',
        'email' => 'admin@contoh.test',
        'name' => 'Admin Satu',
        '--password' => 'rahasia123',
    ])->assertExitCode(0);

    $this->artisan('ira:provision-account', [
        'role' => 'admin_toko',
        'email' => 'admin@contoh.test',
        'name' => 'Admin Satu',
        '--password' => 'lainnya123',
    ])->assertExitCode(1);

    expect(Hash::check('rahasia123', User::first()->password))->toBeTrue();
    expect(Hash::check('lainnya123', User::first()->password))->toBeFalse();
});

test('ACC-05 provisioning refuses a buyer email and short or invalid input', function () {
    User::factory()->create(['email' => 'nara@contoh.test']);

    $this->artisan('ira:provision-account', [
        'role' => 'admin_toko',
        'email' => 'nara@contoh.test',
        'name' => 'Bukan Admin',
        '--password' => 'rahasia123',
    ])->assertExitCode(1);
    expect(User::where('email', 'nara@contoh.test')->first()->role)->toBe('pembeli');

    $this->artisan('ira:provision-account', [
        'role' => 'kasir',
        'email' => 'x@contoh.test',
        'name' => 'X',
        '--password' => 'rahasia123',
    ])->assertExitCode(1);

    $this->artisan('ira:provision-account', [
        'role' => 'developer',
        'email' => 'dev@contoh.test',
        'name' => 'Dev',
        '--password' => 'pendek',
    ])->assertExitCode(1);
    $this->assertDatabaseCount('developers', 0);
});
