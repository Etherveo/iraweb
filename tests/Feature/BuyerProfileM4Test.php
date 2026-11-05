<?php

use App\Models\Address;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;

// M4 slice 1 (buyer profile, Data_Pembeli §1+§2). New nullable profile
// columns on users (username unique, phone, gender CHECK, birthdate date
// cast) and the addresses table (FK user_id, cascade on delete,
// address_type CHECK, 5-character postal_code CHECK). No routes or UI
// yet — this slice is schema + models + factories only.

test('(a) user without a profile still saves, profile columns stay null', function () {
    $user = User::factory()->create([
        'username' => null,
        'phone' => null,
        'gender' => null,
        'birthdate' => null,
    ]);

    expect($user->username)->toBeNull();
    expect($user->phone)->toBeNull();
    expect($user->gender)->toBeNull();
    expect($user->birthdate)->toBeNull();
});

test('(b) duplicate username is rejected by the database', function () {
    User::factory()->create(['username' => 'duplikat01']);

    expect(fn () => User::factory()->create(['username' => 'duplikat01']))
        ->toThrow(QueryException::class);
});

test('(c) gender outside laki-laki/perempuan is rejected by the database', function () {
    expect(fn () => User::factory()->create(['gender' => 'lainnya']))
        ->toThrow(QueryException::class);

    $user = User::factory()->create(['gender' => 'perempuan']);

    expect($user->gender)->toBe('perempuan');
});

test('(d) birthdate is cast to a date', function () {
    $user = User::factory()->create(['birthdate' => '2003-05-15']);

    expect($user->fresh()->birthdate)->toBeInstanceOf(Carbon::class);
    expect($user->fresh()->birthdate->format('Y-m-d'))->toBe('2003-05-15');
});

test('(e) address requires a user_id', function () {
    expect(fn () => Address::factory()->create(['user_id' => null]))
        ->toThrow(QueryException::class);

    expect(fn () => Address::factory()->create(['user_id' => 999999]))
        ->toThrow(QueryException::class);
});

test('(f) deleting a user deletes its addresses', function () {
    $user = User::factory()->create();
    Address::factory()->count(2)->create(['user_id' => $user->id]);

    expect(Address::where('user_id', $user->id)->count())->toBe(2);

    $user->delete();

    expect(Address::where('user_id', $user->id)->count())->toBe(0);
});

test('(g) user->addresses and address->user relations work', function () {
    $user = User::factory()->create();
    $address = Address::factory()->create(['user_id' => $user->id]);

    expect($user->addresses)->toHaveCount(1);
    expect($user->addresses->first()->is($address))->toBeTrue();
    expect($address->user->is($user))->toBeTrue();
});

test('(h) factory builds a profiled user and a complete Gorontalo address', function () {
    $user = User::factory()->create();

    expect($user->username)->toBeString();
    expect($user->username)->not->toBe('');
    expect($user->phone)->toStartWith('08');
    expect(strlen($user->phone))->toBe(12);
    expect($user->gender)->toBeIn(['laki-laki', 'perempuan']);
    expect($user->birthdate->age)->toBeGreaterThanOrEqual(17);
    expect($user->birthdate->age)->toBeLessThanOrEqual(60);

    $address = Address::factory()->create(['user_id' => $user->id]);

    expect($address->recipient_name)->toBe($user->name);
    expect($address->province)->toBe('Gorontalo');
    expect($address->city)->toBe('Kota Gorontalo');
    expect($address->district)->toBe('Kota Selatan');
    expect($address->village)->toBe('Limba B');
    expect($address->postal_code)->toBe('96115');
    expect($address->street_address)->toStartWith('Jl. Jend. Sudirman No. ');
    expect($address->address_type)->toBe('Rumah');
});
