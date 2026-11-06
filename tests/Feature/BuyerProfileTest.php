<?php

use App\Models\Address;
use App\Models\Developer;
use App\Models\User;

test('guest is redirected to /masuk from all buyer profile routes', function () {
    $this->get('/profil')->assertRedirect('/masuk');
    $this->get('/profil/ubah')->assertRedirect('/masuk');
    $this->put('/profil', ['name' => 'Tamu'])->assertRedirect('/masuk');
    $this->put('/profil/alamat', ['recipient_name' => 'Tamu'])->assertRedirect('/masuk');
});

test('buyer can view own profile and edit form, including empty address state and navbar link', function () {
    $buyer = User::factory()->create([
        'name' => 'Alya Putri',
        'email' => 'alya@contoh.test',
        'username' => null,
        'phone' => null,
        'gender' => null,
        'birthdate' => null,
    ]);

    $showResponse = $this->actingAs($buyer, 'web')->get('/profil');
    $showResponse->assertOk()
        ->assertSee('Profil Saya')
        ->assertSee('Alya Putri')
        ->assertSee('alya@contoh.test')
        ->assertSee('Belum ada alamat tersimpan.')
        ->assertSee(route('profil.show'));

    $editResponse = $this->actingAs($buyer, 'web')->get('/profil/ubah');
    $editResponse->assertOk()
        ->assertSee('Ubah Profil')
        ->assertSee('Alya Putri')
        ->assertSee('alya@contoh.test');
});

test('buyer can update profile with valid data and clear optional fields', function () {
    $buyer = User::factory()->create([
        'name' => 'Budi Lama',
        'email' => 'budi@contoh.test',
        'username' => null,
        'phone' => null,
        'gender' => null,
        'birthdate' => null,
    ]);

    $response = $this->actingAs($buyer, 'web')->put('/profil', [
        'name' => 'Budi Santoso',
        'username' => 'budisantoso',
        'phone' => '081234567890',
        'gender' => 'laki-laki',
        'birthdate' => '1999-08-17',
    ]);

    $response->assertRedirect(route('profil.show'))
        ->assertSessionHas('status');

    $buyer->refresh();
    expect($buyer->name)->toBe('Budi Santoso');
    expect($buyer->username)->toBe('budisantoso');
    expect($buyer->phone)->toBe('081234567890');
    expect($buyer->gender)->toBe('laki-laki');
    expect($buyer->birthdate?->format('Y-m-d'))->toBe('1999-08-17');

    // Updating again while keeping own username and clearing optional fields works.
    $this->actingAs($buyer, 'web')->put('/profil', [
        'name' => 'Budi Santoso Baru',
        'username' => 'budisantoso',
        'phone' => '',
        'gender' => '',
        'birthdate' => '',
    ])->assertRedirect(route('profil.show'));

    $buyer->refresh();
    expect($buyer->name)->toBe('Budi Santoso Baru');
    expect($buyer->username)->toBe('budisantoso');
    expect($buyer->phone)->toBeNull();
    expect($buyer->gender)->toBeNull();
    expect($buyer->birthdate)->toBeNull();
});

test('profile update rejects duplicate username, invalid gender, future birthdate, and short name', function () {
    User::factory()->create(['username' => 'dipakai_orang']);
    $buyer = User::factory()->create(['username' => 'milik_sendiri']);

    $response = $this->actingAs($buyer, 'web')->from('/profil/ubah')->put('/profil', [
        'name' => 'X',
        'username' => 'dipakai_orang',
        'gender' => 'tidak-valid',
        'birthdate' => now()->addDays(5)->toDateString(),
    ]);

    $response->assertRedirect('/profil/ubah')
        ->assertSessionHasErrors(['name', 'username', 'gender', 'birthdate']);

    expect($buyer->fresh()->username)->toBe('milik_sendiri');
});

test('buyer address is created when none exists and updated in-place when one already exists', function () {
    $buyer = User::factory()->create(['name' => 'Citra Lestari']);
    expect($buyer->addresses()->count())->toBe(0);

    $createPayload = [
        'recipient_name' => 'Citra Lestari',
        'recipient_phone' => '082199887766',
        'province' => 'Gorontalo',
        'city' => 'Kota Gorontalo',
        'district' => 'Kota Tengah',
        'village' => 'Dulalowo',
        'postal_code' => '96128',
        'street_address' => 'Jl. HB Jassin No. 12',
        'address_type' => 'Rumah',
    ];

    $this->actingAs($buyer, 'web')
        ->put('/profil/alamat', $createPayload)
        ->assertRedirect(route('profil.show'))
        ->assertSessionHas('status');

    expect($buyer->addresses()->count())->toBe(1);
    $firstAddressId = $buyer->addresses()->first()->id;

    $updatePayload = [
        'recipient_name' => 'Citra Kantor',
        'recipient_phone' => '081122334455',
        'province' => 'Gorontalo',
        'city' => 'Kabupaten Bone Bolango',
        'district' => 'Tilongkabila',
        'village' => 'Moutong',
        'postal_code' => '96119',
        'street_address' => 'Jl. Prof. Dr. Ing. B.J. Habibie No. 5',
        'address_type' => 'Kantor',
    ];

    $this->actingAs($buyer, 'web')
        ->put('/profil/alamat', $updatePayload)
        ->assertRedirect(route('profil.show'));

    expect($buyer->addresses()->count())->toBe(1);
    $address = $buyer->addresses()->first();
    expect($address->id)->toBe($firstAddressId);
    expect($address->recipient_name)->toBe('Citra Kantor');
    expect($address->city)->toBe('Kabupaten Bone Bolango');
    expect($address->postal_code)->toBe('96119');
    expect($address->address_type)->toBe('Kantor');
});

test('address update rejects non-5-digit postal code and invalid address_type', function () {
    $buyer = User::factory()->create();

    $response = $this->actingAs($buyer, 'web')->from('/profil/ubah')->put('/profil/alamat', [
        'recipient_name' => 'Penerima',
        'province' => 'Gorontalo',
        'city' => 'Kota Gorontalo',
        'district' => 'Kota Selatan',
        'village' => 'Limba B',
        'postal_code' => '9611',
        'street_address' => 'Jl. Sudirman No. 1',
        'address_type' => 'Kos',
    ]);

    $response->assertRedirect('/profil/ubah')
        ->assertSessionHasErrors(['postal_code', 'address_type']);

    expect($buyer->addresses()->count())->toBe(0);
});

test('security: user_id and email in payload are ignored and cannot overwrite another user or email', function () {
    $victim = User::factory()->create([
        'name' => 'Korban Asli',
        'email' => 'korban@contoh.test',
        'username' => 'korban_asli',
    ]);
    $victimAddress = Address::factory()->create([
        'user_id' => $victim->id,
        'recipient_name' => 'Korban Asli',
        'postal_code' => '96115',
    ]);

    $attacker = User::factory()->create([
        'name' => 'Penyerang',
        'email' => 'penyerang@contoh.test',
    ]);

    $this->actingAs($attacker, 'web')->put('/profil', [
        'user_id' => $victim->id,
        'id' => $victim->id,
        'name' => 'Diubah Penyerang',
        'email' => 'baru@contoh.test',
        'username' => 'penyerang_baru',
    ])->assertRedirect(route('profil.show'));

    $victim->refresh();
    $attacker->refresh();
    expect($victim->name)->toBe('Korban Asli');
    expect($victim->username)->toBe('korban_asli');
    expect($attacker->email)->toBe('penyerang@contoh.test');
    expect($attacker->name)->toBe('Diubah Penyerang');

    $this->actingAs($attacker, 'web')->put('/profil/alamat', [
        'user_id' => $victim->id,
        'id' => $victimAddress->id,
        'recipient_name' => 'Alamat Penyerang',
        'province' => 'Gorontalo',
        'city' => 'Kota Gorontalo',
        'district' => 'Kota Utara',
        'village' => 'Wongkaditi',
        'postal_code' => '96127',
        'street_address' => 'Jl. Penyerang No. 99',
        'address_type' => 'Lainnya',
    ])->assertRedirect(route('profil.show'));

    expect($victimAddress->fresh()->recipient_name)->toBe('Korban Asli');
    expect($attacker->addresses()->count())->toBe(1);
    expect($attacker->addresses()->first()->recipient_name)->toBe('Alamat Penyerang');
});

test('security: admin_toko and developer cannot access buyer profile routes', function () {
    $admin = User::factory()->admin()->create();
    $developer = Developer::create([
        'name' => 'Dev Ira',
        'email' => 'dev@ira.test',
        'password' => 'rahasia123',
        'role' => 'dev',
    ]);

    $this->actingAs($admin, 'web')->get('/profil')->assertForbidden();
    $this->actingAs($admin, 'web')->get('/profil/ubah')->assertForbidden();
    $this->actingAs($admin, 'web')->put('/profil', ['name' => 'Admin'])->assertForbidden();
    $this->actingAs($admin, 'web')->put('/profil/alamat', ['recipient_name' => 'Admin'])->assertForbidden();

    $this->actingAs($developer, 'developer')->get('/profil')->assertForbidden();
    $this->actingAs($developer, 'developer')->put('/profil', ['name' => 'Dev'])->assertForbidden();
});
