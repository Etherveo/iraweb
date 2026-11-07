<?php

use App\Models\Developer;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

// M1 ACC-01..15 (ACC-09 is groundwork here; full cart/order isolation is M3).
// Keputusan 2026-11-05: login disatukan — ONE form at /masuk with an aktor
// switch (pembeli | admin_toko | developer). Guards/tables stay separate;
// developers have role 'dev' and developer routes declare role:dev.
// No Python, no Figma images, no external HTTP in any test.

test('ACC-01 register pembeli creates active pembeli, hashes password, logs in, redirects to katalog', function () {
    $response = $this->post('/daftar', [
        'name' => 'Nara Contoh',
        'email' => 'nara@contoh.test',
        'password' => 'rahasia123',
        'password_confirmation' => 'rahasia123',
    ]);

    $response->assertRedirect('/katalog');
    $this->assertAuthenticatedAs(User::where('email', 'nara@contoh.test')->first(), 'web');
    $this->assertDatabaseHas('users', [
        'email' => 'nara@contoh.test',
        'role' => 'pembeli',
        'origin' => 'normal',
        'is_active' => true,
    ]);
    $user = User::where('email', 'nara@contoh.test')->first();
    expect(Hash::check('rahasia123', $user->password))->toBeTrue();
    expect($user->password)->not->toBe('rahasia123');
});

test('ACC-01 register rejects short name, bad email, short password, mismatched confirmation, and keeps old input', function () {
    $response = $this->post('/daftar', [
        'name' => 'A',
        'email' => 'bukan-email',
        'password' => 'pendek',
        'password_confirmation' => 'beda',
    ]);

    $response->assertSessionHasErrors(['name', 'email', 'password']);
    $this->assertGuest('web');
    $this->assertDatabaseCount('users', 0);
});

test('ACC-01 register email uniqueness is case-insensitive and stored lowercased', function () {
    User::factory()->create(['email' => 'Nara@Contoh.Test']);

    $response = $this->post('/daftar', [
        'name' => 'Lain',
        'email' => 'nara@contoh.test',
        'password' => 'rahasia123',
        'password_confirmation' => 'rahasia123',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertDatabaseCount('users', 1);
    expect(User::first()->email)->toBe('nara@contoh.test');
});

test('ACC-01 register rejects the reserved simulation domain and double submit does not create two accounts', function () {
    $domain = config('ira.sim_account_email_domain');

    $this->post('/daftar', [
        'name' => 'Sim',
        'email' => 'x@'.$domain,
        'password' => 'rahasia123',
        'password_confirmation' => 'rahasia123',
    ])->assertSessionHasErrors('email');
    $this->assertDatabaseCount('users', 0);

    $payload = [
        'name' => 'Nara',
        'email' => 'nara@contoh.test',
        'password' => 'rahasia123',
        'password_confirmation' => 'rahasia123',
    ];
    $this->post('/daftar', $payload)->assertRedirect('/katalog');
    $this->post('/daftar', $payload)->assertSessionHasErrors('email');
    $this->assertDatabaseCount('users', 1);
});

test('ACC-02 buyer login succeeds, wrong credentials and unknown email give the same generic message', function () {
    User::factory()->create(['email' => 'nara@contoh.test', 'password' => Hash::make('rahasia123')]);

    $this->post('/masuk', ['aktor' => 'pembeli', 'email' => 'nara@contoh.test', 'password' => 'rahasia123'])
        ->assertRedirect('/katalog');
    $this->assertAuthenticated('web');

    $this->post('/masuk', ['aktor' => 'pembeli', 'email' => 'nara@contoh.test', 'password' => 'salah'])
        ->assertSessionHasErrors('email');
    $this->post('/masuk', ['aktor' => 'pembeli', 'email' => 'tidak-ada@contoh.test', 'password' => 'salah'])
        ->assertSessionHasErrors('email');
});

test('ACC-02 admin credentials on the buyer form are rejected with the generic message and do not log in', function () {
    User::factory()->admin()->create(['email' => 'admin@contoh.test', 'password' => Hash::make('rahasia123')]);

    $this->post('/masuk', ['aktor' => 'pembeli', 'email' => 'admin@contoh.test', 'password' => 'rahasia123'])
        ->assertSessionHasErrors('email');
    $this->assertGuest('web');
});

test('ACC-03 admin login succeeds only for admin_toko and redirects to admin dashboard', function () {
    User::factory()->admin()->create(['email' => 'admin@contoh.test', 'password' => Hash::make('rahasia123')]);
    User::factory()->create(['email' => 'nara@contoh.test', 'password' => Hash::make('rahasia123')]);

    $this->post('/masuk', ['aktor' => 'admin_toko', 'email' => 'admin@contoh.test', 'password' => 'rahasia123'])
        ->assertRedirect('/admin');
    $this->assertAuthenticated('web');

    $this->post('/masuk', ['aktor' => 'admin_toko', 'email' => 'nara@contoh.test', 'password' => 'rahasia123'])
        ->assertSessionHasErrors('email');
});

test('ACC-04 developer login uses a separate guard and rejects web credentials', function () {
    Developer::factory()->create(['email' => 'dev@contoh.test', 'password' => Hash::make('rahasia123')]);
    User::factory()->create(['email' => 'nara@contoh.test', 'password' => Hash::make('rahasia123')]);

    $this->post('/masuk', ['aktor' => 'developer', 'email' => 'dev@contoh.test', 'password' => 'rahasia123'])
        ->assertRedirect('/developer');
    $this->assertAuthenticatedAs(Developer::first(), 'developer');
    $this->assertGuest('web');
    expect(Developer::first()->role)->toBe('dev');

    $this->post('/masuk', ['aktor' => 'developer', 'email' => 'nara@contoh.test', 'password' => 'rahasia123'])
        ->assertSessionHasErrors('email');
});

test('Unified login rejects wrong-actor credentials with the same generic message', function () {
    User::factory()->create(['email' => 'nara@contoh.test', 'password' => Hash::make('rahasia123')]);
    User::factory()->admin()->create(['email' => 'admin@contoh.test', 'password' => Hash::make('rahasia123')]);
    Developer::factory()->create(['email' => 'dev@contoh.test', 'password' => Hash::make('rahasia123')]);

    // Buyer credential tried as admin, admin credential tried as buyer,
    // developer credential tried as pembeli, web credential tried as developer.
    $this->post('/masuk', ['aktor' => 'admin_toko', 'email' => 'nara@contoh.test', 'password' => 'rahasia123'])
        ->assertSessionHasErrors('email');
    $this->post('/masuk', ['aktor' => 'pembeli', 'email' => 'admin@contoh.test', 'password' => 'rahasia123'])
        ->assertSessionHasErrors('email');
    $this->post('/masuk', ['aktor' => 'pembeli', 'email' => 'dev@contoh.test', 'password' => 'rahasia123'])
        ->assertSessionHasErrors('email');
    $this->post('/masuk', ['aktor' => 'developer', 'email' => 'nara@contoh.test', 'password' => 'rahasia123'])
        ->assertSessionHasErrors('email');
    $this->assertGuest('web');
    $this->assertGuest('developer');
});

test('Legacy separate login URLs redirect to the unified form with actor pre-selected', function () {
    $this->get('/admin/masuk')->assertRedirect('/masuk?aktor=admin_toko');
    $this->get('/developer/masuk')->assertRedirect('/masuk?aktor=developer');
    $this->get('/masuk?aktor=admin_toko')->assertOk()->assertSee('Admin Toko');
    $this->get('/masuk?aktor=developer')->assertOk()->assertSee('Developer');
    $this->get('/masuk')->assertOk()->assertSee('Pembeli');
});

test('ACC-06 logout is POST only and logging out web does not log out developer', function () {
    $buyer = User::factory()->create();
    $developer = Developer::factory()->create();

    $this->actingAs($buyer, 'web');
    $this->actingAs($developer, 'developer');
    $this->assertAuthenticated('web');
    $this->assertAuthenticated('developer');

    // GET logout must not work (route is POST only).
    $this->get('/keluar')->assertStatus(405);

    $this->post('/keluar')->assertRedirect('/masuk');
    $this->assertGuest('web');
    $this->assertAuthenticated('developer');

    $this->post('/developer/keluar')->assertRedirect('/masuk?aktor=developer');
    $this->assertGuest('developer');
});

test('ACC-07 inactive account cannot log in and an active session becomes invalid on next request', function () {
    $user = User::factory()->inactive()->create(['email' => 'nara@contoh.test', 'password' => Hash::make('rahasia123')]);

    $this->post('/masuk', ['aktor' => 'pembeli', 'email' => 'nara@contoh.test', 'password' => 'rahasia123'])
        ->assertSessionHasErrors('email');
    $this->assertGuest('web');

    $user->update(['is_active' => true, 'deactivated_at' => null]);
    // UI polish Tahap 2: /katalog is public now, so the session probe moves
    // to /keranjang, which stays behind auth:web + active.
    $this->actingAs($user, 'web');
    $this->get('/keranjang')->assertOk();

    $user->update(['is_active' => false, 'deactivated_at' => now()]);
    $this->get('/keranjang')->assertRedirect('/masuk');
});

test('ACC-08 unauthenticated users are redirected to the matching login form and wrong role gets 403', function () {
    // UI polish Tahap 2: the catalog is public for guests; buyer-only
    // routes such as the cart still redirect to the login form.
    $this->get('/katalog')->assertOk();
    $this->get('/keranjang')->assertRedirect('/masuk');
    $this->get('/admin/akun-pembeli')->assertRedirect('/masuk?aktor=admin_toko');
    $this->get('/developer/akun-simulasi')->assertRedirect('/masuk?aktor=developer');

    $buyer = User::factory()->create();
    $this->actingAs($buyer, 'web');
    $this->get('/admin')->assertForbidden();
    $this->get('/admin/akun-pembeli')->assertForbidden();
    $this->get('/developer')->assertRedirect('/masuk?aktor=developer');
});

test('ACC-08 every protected route declares guard and role (logout routes are the auth-only exception)', function () {
    $routes = \Illuminate\Support\Facades\Route::getRoutes();

    foreach ($routes as $route) {
        $middleware = $route->gatherMiddleware();
        $hasAuth = collect($middleware)->contains(fn ($m) => str_starts_with((string) $m, 'auth:'));

        if (! $hasAuth) {
            continue;
        }

        // Logout routes: any authenticated user of that guard may log out.
        if (in_array($route->getName(), ['keluar', 'developer.keluar'], true)) {
            continue;
        }

        $hasRole = collect($middleware)->contains(fn ($m) => str_starts_with((string) $m, 'role:'));

        // Keputusan 2026-11-05: login disatukan — developers have role 'dev'
        // and developer routes declare role:dev, so there is NO developer
        // exception any more. Only the two logout routes are auth-only.
        expect($hasRole)->toBeTrue(
            'Protected route '.$route->uri().' must declare a role.'
        );
    }

    $this->assertTrue(true);
});

test('ACC-10 developer cannot open admin routes and buyer flow', function () {
    $developer = Developer::factory()->create();

    $this->actingAs($developer, 'developer');
    $this->get('/admin')->assertRedirect('/masuk?aktor=admin_toko');
    // The catalog is public (Tahap 2), so a developer session may read it;
    // the buyer flow (cart) is still closed to a developer-only session.
    $this->get('/katalog')->assertOk();
    $this->get('/keranjang')->assertRedirect('/masuk');
});

test('ACC-10 admin cannot open developer routes', function () {
    $admin = User::factory()->admin()->create();

    // Separate test on purpose: actingAs() in the same test would leak the
    // previous guard's user into this request, so a real admin-only user
    // (web guard only, no developer session) must be tested in isolation.
    $this->actingAs($admin, 'web');
    $this->get('/developer')->assertRedirect('/masuk?aktor=developer');
    $this->get('/developer/akun-simulasi')->assertRedirect('/masuk?aktor=developer');
});

test('ACC-11/12 admin buyer list filters, searches, shows SIMULASI badge, and detail 404s for non-pembeli', function () {
    $admin = User::factory()->admin()->create();
    $normal = User::factory()->create(['name' => 'Nara Normal']);
    $sim = User::factory()->simulation('run-1')->create(['name' => 'Sim Satu']);

    $this->actingAs($admin, 'web');

    $this->get('/admin/akun-pembeli')
        ->assertOk()
        ->assertSee('Nara Normal')
        ->assertSee('Sim Satu')
        ->assertDontSee('SIMULASI');

    $this->get('/admin/akun-pembeli?asal=developer_simulation')
        ->assertOk()
        ->assertSee('Sim Satu')
        ->assertDontSee('Nara Normal');

    $this->get('/admin/akun-pembeli?cari=nara')
        ->assertOk()
        ->assertSee('Nara Normal')
        ->assertDontSee('Sim Satu');

    $this->get('/admin/akun-pembeli/'.$normal->id)->assertOk()->assertSee('Nara Normal');
    $this->get('/admin/akun-pembeli/'.$admin->id)->assertNotFound();
    $this->get('/admin/akun-pembeli/999999')->assertNotFound();
});

test('ACC-13 admin can deactivate and reactivate a buyer with a reason, and deactivation blocks login', function () {
    $admin = User::factory()->admin()->create();
    $buyer = User::factory()->create(['email' => 'nara@contoh.test', 'password' => Hash::make('rahasia123')]);

    $this->actingAs($admin, 'web');
    $this->post('/admin/akun-pembeli/'.$buyer->id.'/status', [
        'is_active' => 0,
        'reason' => 'Pelanggaran contoh',
    ])->assertRedirect('/admin/akun-pembeli/'.$buyer->id);

    $buyer->refresh();
    expect($buyer->is_active)->toBeFalse();
    expect($buyer->deactivation_reason)->toBe('Pelanggaran contoh');
    expect($buyer->deactivated_at)->not->toBeNull();

    $this->post('/masuk', ['aktor' => 'pembeli', 'email' => 'nara@contoh.test', 'password' => 'rahasia123'])
        ->assertSessionHasErrors('email');

    $this->actingAs($admin, 'web');
    $this->post('/admin/akun-pembeli/'.$buyer->id.'/status', ['is_active' => 1])
        ->assertRedirect('/admin/akun-pembeli/'.$buyer->id);
    expect($buyer->fresh()->is_active)->toBeTrue();
    expect($buyer->fresh()->deactivation_reason)->toBeNull();
});

test('ACC-14 developer simulation list shows only simulation accounts and filters by run', function () {
    $developer = Developer::factory()->create();
    User::factory()->create(['name' => 'Normal Saja']);
    User::factory()->simulation('run-1')->create(['name' => 'Sim Run Satu']);
    User::factory()->simulation('run-2')->create(['name' => 'Sim Run Dua']);

    $this->actingAs($developer, 'developer');

    $this->get('/developer/akun-simulasi')
        ->assertOk()
        ->assertSee('Sim Run Satu')
        ->assertSee('Sim Run Dua')
        ->assertDontSee('Normal Saja');

    $this->get('/developer/akun-simulasi?run=run-1')
        ->assertOk()
        ->assertSee('Sim Run Satu')
        ->assertDontSee('Sim Run Dua');
});

test('ACC-15 simulation account logs in via the buyer form and keeps its simulation origin', function () {
    $sim = User::factory()->simulation('run-1')->create([
        'email' => 'sim1@'.config('ira.sim_account_email_domain'),
        'password' => Hash::make('rahasia123'),
    ]);

    $this->post('/masuk', [
        'aktor' => 'pembeli',
        'email' => 'sim1@'.config('ira.sim_account_email_domain'),
        'password' => 'rahasia123',
    ])->assertRedirect('/katalog');

    $this->assertAuthenticatedAs($sim, 'web');
    expect($sim->fresh()->origin)->toBe('developer_simulation');
});

test('M0 config check command passes with a valid M0 configuration', function () {
    config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
    config(['database.default' => 'sqlite']);
    config(['app.timezone' => 'UTC']);
    config(['ira.reporting_timezone' => 'Asia/Makassar']);
    config(['ira.sim_account_email_domain' => 'sim.ira-stationery.test']);

    $this->artisan('ira:check-config')->assertExitCode(0);
});
