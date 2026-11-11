<?php

use App\Models\Address;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Developer;
use App\Models\Event;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\PaymentStatusTransition;
use App\Models\Product;
use App\Models\SimulationAuditLog;
use App\Models\SimulationRun;
use App\Models\StockMovement;
use App\Models\User;
use App\Simulation\BiodataGenerator;
use App\Simulation\FunnelPlanner;
use App\Simulation\SequentialBiodataGenerator;
use App\Simulation\SimulationAudit;
use App\Simulation\SimulationExecutor;
use App\Simulation\SimulationRequestValidator;
use Carbon\Carbon;
use Database\Seeders\CatalogSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

// M4 simulator (SIM-02..42) — suite utama. Cakupan per SIM lewat HTTP
// panel developer (guard developer, role dev) + beberapa tes langsung
// ke service/validator bila perilakunya tidak terjangkau HTTP (SIM-27).
// Pest memakai test double SequentialBiodataGenerator — tes tidak pernah
// shell-out ke Python (lihat SimulationGeneratorTest untuk integrasi
// Python). Periode uji 2026-10-01..07 sudah lampau, jadi aturan SIM-28
// "akhir tidak boleh di masa depan" tidak bergantung pada hari suite
// dijalankan.

beforeEach(function () {
    // Rebind ke test double (AppServiceProvider mengikat ke
    // PythonBiodataGenerator untuk runtime); kata sandi akun sim diisi
    // di sini — tanpa ini SIM-08 menolak setiap pembuatan akun.
    $this->app->bind(BiodataGenerator::class, SequentialBiodataGenerator::class);
    config(['simulation.account_password' => 'rahasia-sim-test']);
    config(['ira.sim_account_password' => 'rahasia-sim-test']);
});

// ---- Helpers (prefiks m4t — jangan bentrok dengan helper m3*/m4* di
// file tes lain; Pest memuat semua file tes dalam satu proses) ----------

function m4tDeveloper(): Developer
{
    return Developer::factory()->create();
}

function m4tProduct(array $attributes = []): Product
{
    return Product::factory()->create(array_merge(
        ['price_idr' => 25000, 'stock_on_hand' => 50, 'stock_reserved' => 0, 'is_active' => true],
        $attributes,
    ));
}

/** Hitungan baris semua tabel yang disentuh simulator (SIM-25/27/38/39). */
function m4tTableCounts(): array
{
    return [
        'users' => User::count(),
        'addresses' => Address::count(),
        'orders' => Order::count(),
        'order_items' => OrderItem::count(),
        'payments' => Payment::count(),
        'events' => Event::count(),
        'stock_movements' => StockMovement::count(),
        'order_status_histories' => OrderStatusHistory::count(),
        'payment_status_transitions' => PaymentStatusTransition::count(),
        'simulation_runs' => SimulationRun::count(),
        'simulation_audit_logs' => SimulationAuditLog::count(),
    ];
}

/**
 * Buat akun simulasi lewat HTTP (SIM-02). Mengembalikan [Collection<User>, SimulationRun].
 * assertSessionHas('status') membedakan sukses dari redirect-back
 * karena error validasi (keduanya 302).
 */
function m4tCreateAccounts($t, Developer $developer, int $count = 2, array $overrides = []): array
{
    $key = (string) Str::uuid();

    $t->actingAs($developer, 'developer');

    $t->post(route('developer.akun.store'), array_merge([
        'count' => $count,
        'mode' => 'deterministic',
        'seed' => 20261101,
        'offset' => 0,
        'idempotency_key' => $key,
    ], $overrides))
        ->assertRedirect()
        ->assertSessionHas('status');

    $run = SimulationRun::query()->where('idempotency_key', $key)->firstOrFail();
    $users = User::query()->where('simulation_run_id', $run->id)->orderBy('id')->get();

    return [$users, $run];
}

function m4tUserFirstPayload(array $userIds, array $overrides = []): array
{
    return array_merge([
        'user_ids' => $userIds,
        'transactions_per_account' => 2,
        'period_start' => '2026-10-01',
        'period_end' => '2026-10-07',
        'mode' => 'deterministic',
        'seed' => 20261101,
    ], $overrides);
}

function m4tPeriodFirstPayload(array $overrides = []): array
{
    return array_merge([
        'user_scope' => 'all',
        'transaction_count' => 4,
        'period_start' => '2026-10-01',
        'period_end' => '2026-10-07',
        'mode' => 'deterministic',
        'seed' => 20261101,
    ], $overrides);
}

function m4tFunnelPayload(array $userIds, array $overrides = []): array
{
    return array_merge([
        'user_ids' => $userIds,
        'period_start' => '2026-10-01',
        'period_end' => '2026-10-07',
        'view_count' => 10,
        'add_to_cart_count' => 6,
        'booking_count' => 4,
        'purchase_count' => 3,
        'mode' => 'deterministic',
        'seed' => 20261101,
    ], $overrides);
}

/**
 * Hash pratinjau seperti yang dihitung controller: validasi input
 * (validator service) lalu bangun plan (planner). Dipakai untuk
 * mengisi hidden field params_hash pada POST eksekusi.
 */
function m4tPreviewHash(array $payload, string $validateMethod, string $planMethod): string
{
    $params = app(SimulationRequestValidator::class)->$validateMethod($payload);
    $plan = app(FunnelPlanner::class)->$planMethod($params, $params['seed']);

    return $plan->paramsHash;
}

function m4tExecute($t, string $executeRoute, array $payload, string $hash, ?string $key = null)
{
    return $t->post(route($executeRoute), array_merge($payload, [
        'idempotency_key' => $key ?? (string) Str::uuid(),
        'params_hash' => $hash,
    ]));
}

/** Pratinjau + eksekusi user-first penuh; mengembalikan run-nya. */
function m4tRunUserFirst($t, Developer $developer, array $userIds, array $overrides = []): SimulationRun
{
    $t->actingAs($developer, 'developer');

    $payload = m4tUserFirstPayload($userIds, $overrides);

    $t->post(route('developer.tx-user.preview'), $payload)->assertOk();

    $hash = m4tPreviewHash($payload, 'validateUserFirst', 'plan');
    $key = (string) Str::uuid();

    m4tExecute($t, 'developer.tx-user.execute', $payload, $hash, $key)
        ->assertRedirect()
        ->assertSessionHas('status');

    return SimulationRun::query()->where('idempotency_key', $key)->firstOrFail();
}

/** Pratinjau + eksekusi period-first penuh; mengembalikan run-nya. */
function m4tRunPeriodFirst($t, Developer $developer, array $overrides = []): SimulationRun
{
    $t->actingAs($developer, 'developer');

    $payload = m4tPeriodFirstPayload($overrides);

    $t->post(route('developer.tx-period.preview'), $payload)->assertOk();

    $hash = m4tPreviewHash($payload, 'validatePeriodFirst', 'plan');
    $key = (string) Str::uuid();

    m4tExecute($t, 'developer.tx-period.execute', $payload, $hash, $key)
        ->assertRedirect()
        ->assertSessionHas('status');

    return SimulationRun::query()->where('idempotency_key', $key)->firstOrFail();
}

/** Pratinjau + eksekusi funnel penuh; mengembalikan run-nya. */
function m4tRunFunnel($t, Developer $developer, array $userIds, array $overrides = []): SimulationRun
{
    $t->actingAs($developer, 'developer');

    $payload = m4tFunnelPayload($userIds, $overrides);

    $t->post(route('developer.funnel.preview'), $payload)->assertOk();

    $hash = m4tPreviewHash($payload, 'validateFunnel', 'planFunnel');
    $key = (string) Str::uuid();

    m4tExecute($t, 'developer.funnel.execute', $payload, $hash, $key)
        ->assertRedirect()
        ->assertSessionHas('status');

    return SimulationRun::query()->where('idempotency_key', $key)->firstOrFail();
}

/**
 * Run nyangkut buatan: baris run dibuat lewat recordRunStart dengan
 * params dari run yang sudah berhasil, lalu dibiarkan 'running'
 * (eksekusi tidak pernah jalan) — persis keadaan run yang request-nya
 * mati di tengah jalan. $status 'gagal' menutupnya sebagai gagal
 * (untuk menguji retry dari run gagal).
 */
function m4tStuckRun(Developer $developer, SimulationRun $source, string $status = 'running'): SimulationRun
{
    $stuck = app(SimulationAudit::class)->recordRunStart(
        $developer,
        $source->action,
        $source->params,
        $source->seed,
        (string) Str::uuid(),
        $source->params['period_start'] ?? null,
        $source->params['period_end'] ?? null,
    );

    if ($status === 'gagal') {
        app(SimulationAudit::class)->recordRunFailed($stuck, 'gagal buatan untuk tes');
    }

    return $stuck;
}

// ---- Guard / akses panel -----------------------------------------------

test('panel developer menolak tamu (redirect ke /masuk?aktor=developer)', function () {
    $this->get('/developer')->assertRedirect(route('masuk', ['aktor' => 'developer']));
    $this->get('/developer/reset')->assertRedirect(route('masuk', ['aktor' => 'developer']));
    $this->post('/developer/akun', [])->assertRedirect(route('masuk', ['aktor' => 'developer']));
});

test('panel developer menolak pembeli guard web', function () {
    $buyer = User::factory()->create();

    $this->actingAs($buyer, 'web');

    $this->get('/developer')->assertRedirect(route('masuk', ['aktor' => 'developer']));
    $this->get('/developer/run')->assertRedirect(route('masuk', ['aktor' => 'developer']));
});

test('developer dapat membuka semua halaman panel', function () {
    $developer = m4tDeveloper();

    $this->actingAs($developer, 'developer');

    foreach ([
        '/developer',
        '/developer/akun/buat',
        '/developer/transaksi/user',
        '/developer/transaksi/periode',
        '/developer/funnel',
        '/developer/run',
        '/developer/reset',
    ] as $url) {
        $this->get($url)->assertOk();
    }
});

// ---- SIM-02/03/04/05/07/08: pembuatan akun -----------------------------

test('SIM-02/07 membuat akun simulasi dengan penanda + alamat + run', function () {
    $developer = m4tDeveloper();

    [$users, $run] = m4tCreateAccounts($this, $developer, 3, ['seed' => 42, 'offset' => 0]);

    expect($run->action)->toBe('create_accounts');
    expect($run->status)->toBe('berhasil');
    expect($run->seed)->toBe(42);
    expect($run->counts)->toBe(['accounts' => 3]);
    expect($run->developer_id)->toBe($developer->id);
    expect($run->period_start)->toBeNull();
    expect($run->period_end)->toBeNull();
    expect($run->finished_at)->not->toBeNull();

    expect($users)->toHaveCount(3);

    foreach ($users as $index => $user) {
        $n = str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT);

        expect($user->origin)->toBe('developer_simulation');
        expect($user->role)->toBe('pembeli');
        expect($user->is_active)->toBeTrue();
        expect($user->simulation_run_id)->toBe($run->id);
        expect($user->name)->toBe("Simulasi {$n}");
        expect($user->email)->toBe("simulasi{$n}@sim.ira-stationery.test");
        expect($user->addresses)->toHaveCount(1);
        expect($user->addresses->first()->province)->toBe('Gorontalo');
        expect($user->addresses->first()->postal_code)->toBe('96115');
    }

    // Kata sandi bersama di-hash sekali dan dipakai ulang, tidak pernah disimpan polos.
    expect(Hash::check('rahasia-sim-test', $users[0]->password))->toBeTrue();
});

test('SIM-02 akun simulasi berbagi satu hash kata sandi (hash sekali per request)', function () {
    $developer = m4tDeveloper();

    [$users] = m4tCreateAccounts($this, $developer, 3, ['seed' => 42, 'offset' => 0]);

    expect($users)->toHaveCount(3);

    // Regression: hash per akun (±0,35 detik di laptop pengembangan)
    // membuat 82 akun menabrak batas 30 detik PHP (FatalError di
    // BcryptHasher). Semua akun memakai satu kata sandi bersama
    // (SIM-08), jadi satu hash dipakai ulang untuk semua akun.
    $hashes = $users->pluck('password')->unique();
    expect($hashes)->toHaveCount(1);

    foreach ($users as $user) {
        expect(Hash::check('rahasia-sim-test', $user->password))->toBeTrue();
    }
});

test('SIM-02/05 biodata deterministik: seed+offset+count sama → biodata identik', function () {
    $developer = m4tDeveloper();

    [$users1, $run1] = m4tCreateAccounts($this, $developer, 3, ['seed' => 42, 'offset' => 0]);

    $emails1 = $users1->pluck('email')->all();
    $names1 = $users1->pluck('name')->all();

    // Reset run pertama (akun ikut terhapus), lalu buat ulang batch yang sama.
    $this->actingAs($developer, 'developer');
    $this->post(route('developer.reset.run', $run1), ['phrase' => 'HAPUS RUN'])->assertRedirect();

    expect(User::where('origin', 'developer_simulation')->count())->toBe(0);

    [$users2] = m4tCreateAccounts($this, $developer, 3, ['seed' => 42, 'offset' => 0]);

    expect($users2->pluck('email')->all())->toEqual($emails1);
    expect($users2->pluck('name')->all())->toEqual($names1);
});

test('SIM-26 pembuatan akun: submit ganda dengan kunci sama = satu run', function () {
    $developer = m4tDeveloper();

    $this->actingAs($developer, 'developer');

    $key = (string) Str::uuid();
    $payload = [
        'count' => 3, 'mode' => 'deterministic', 'seed' => 42, 'offset' => 0,
        'idempotency_key' => $key,
    ];

    $this->post(route('developer.akun.store'), $payload)->assertRedirect()->assertSessionHas('status');
    $this->post(route('developer.akun.store'), $payload)->assertRedirect()->assertSessionHas('status');

    expect(User::where('origin', 'developer_simulation')->count())->toBe(3);
    expect(SimulationRun::where('action', 'create_accounts')->count())->toBe(1);
});

test('SIM-03 jumlah akun di luar 1–100 ditolak server-side', function () {
    $developer = m4tDeveloper();

    $this->actingAs($developer, 'developer');

    $this->post(route('developer.akun.store'), [
        'count' => 101, 'mode' => 'deterministic', 'seed' => 42, 'offset' => 0,
        'idempotency_key' => (string) Str::uuid(),
    ])->assertSessionHasErrors('count');

    $this->post(route('developer.akun.store'), [
        'count' => 0, 'mode' => 'deterministic', 'seed' => 42, 'offset' => 0,
        'idempotency_key' => (string) Str::uuid(),
    ])->assertSessionHasErrors('count');

    expect(User::where('origin', 'developer_simulation')->count())->toBe(0);
    expect(SimulationRun::count())->toBe(0);
});

test('SIM-04 mode random berhasil, seed tersimpan, email unik', function () {
    $developer = m4tDeveloper();

    // Catatan: test double bersifat sekuensial, jadi "acak" di sini
    // hanya menguji jalur mode random (seed dipilih generator +
    // disimpan di run). Keacakan nyata wrapper Python diuji oleh
    // SimulationGeneratorTest (tes integrasi, skip bila tanpa Python).
    [$users, $run] = m4tCreateAccounts($this, $developer, 2, ['mode' => 'random']);

    expect($run->status)->toBe('berhasil');
    expect($run->seed)->toBeInt();
    expect($users->pluck('email')->unique()->count())->toBe(2);
    expect($users->pluck('email')->every(fn ($email) => str_ends_with($email, '@sim.ira-stationery.test')))->toBeTrue();
});

test('SIM-05 seed+offset yang sama kedua kalinya ditolak (tabrakan email)', function () {
    $developer = m4tDeveloper();

    m4tCreateAccounts($this, $developer, 2, ['seed' => 42, 'offset' => 0]);

    $this->actingAs($developer, 'developer');

    $this->post(route('developer.akun.store'), [
        'count' => 2, 'mode' => 'deterministic', 'seed' => 42, 'offset' => 0,
        'idempotency_key' => (string) Str::uuid(),
    ])->assertSessionHasErrors('email');

    expect(User::where('origin', 'developer_simulation')->count())->toBe(2);
    expect(SimulationRun::where('action', 'create_accounts')->count())->toBe(1);
});

test('SIM-05 tabrakan dengan email akun normal ditolak utuh', function () {
    $developer = m4tDeveloper();

    User::factory()->create(['email' => 'simulasi0001@sim.ira-stationery.test']);

    $this->actingAs($developer, 'developer');

    $this->post(route('developer.akun.store'), [
        'count' => 2, 'mode' => 'deterministic', 'seed' => 42, 'offset' => 0,
        'idempotency_key' => (string) Str::uuid(),
    ])->assertSessionHasErrors('email');

    expect(User::where('origin', 'developer_simulation')->count())->toBe(0);
    expect(SimulationRun::count())->toBe(0);
});

test('SIM-08 SIM_ACCOUNT_PASSWORD kosong → pembuatan akun ditolak', function () {
    $developer = m4tDeveloper();

    config(['simulation.account_password' => '']);

    $this->actingAs($developer, 'developer');

    $this->post(route('developer.akun.store'), [
        'count' => 2, 'mode' => 'deterministic', 'seed' => 42, 'offset' => 0,
        'idempotency_key' => (string) Str::uuid(),
    ])->assertSessionHasErrors('sim_account_password');

    expect(User::where('origin', 'developer_simulation')->count())->toBe(0);
    expect(SimulationRun::count())->toBe(0);
});

// ---- SIM-09/18/19: cakupan user -----------------------------------------

test('SIM-09 akun normal yang disubmit manual ditolak', function () {
    $developer = m4tDeveloper();
    $normal = User::factory()->create();

    m4tProduct();

    [$users] = m4tCreateAccounts($this, $developer, 1);

    $this->actingAs($developer, 'developer');

    $this->post(route('developer.tx-user.preview'), m4tUserFirstPayload([$normal->id, $users[0]->id]))
        ->assertSessionHasErrors('user_ids');

    expect(SimulationRun::where('action', 'tx_user_first')->count())->toBe(0);
});

test('SIM-09 daftar akun kosong ditolak', function () {
    $developer = m4tDeveloper();

    m4tProduct();
    m4tCreateAccounts($this, $developer, 1);

    $this->actingAs($developer, 'developer');

    $this->post(route('developer.tx-user.preview'), m4tUserFirstPayload([]))
        ->assertSessionHasErrors('user_ids');
});

test('SIM-19 akun simulasi nonaktif dilewati, bukan ditolak', function () {
    $developer = m4tDeveloper();

    m4tProduct();
    m4tProduct();
    m4tProduct();

    [$users] = m4tCreateAccounts($this, $developer, 2);

    $active = $users[0];
    $inactive = $users[1];
    $inactive->update(['is_active' => false]);

    $this->actingAs($developer, 'developer');

    $payload = m4tUserFirstPayload([$active->id, $inactive->id], ['transactions_per_account' => 1]);

    $this->post(route('developer.tx-user.preview'), $payload)->assertOk();

    $hash = m4tPreviewHash($payload, 'validateUserFirst', 'plan');
    $key = (string) Str::uuid();

    m4tExecute($this, 'developer.tx-user.execute', $payload, $hash, $key)
        ->assertRedirect()
        ->assertSessionHas('status');

    $run = SimulationRun::query()->where('idempotency_key', $key)->firstOrFail();

    expect($run->status)->toBe('berhasil');
    expect($run->params['skipped_inactive_ids'])->toBe([$inactive->id]);
    expect(Order::where('simulation_run_id', $run->id)->pluck('user_id')->unique()->all())
        ->toBe([$active->id]);
});

test('SIM-18/19 cakupan user period-first: random deterministik + error', function () {
    $developer = m4tDeveloper();

    [$users] = m4tCreateAccounts($this, $developer, 3);

    $validator = app(SimulationRequestValidator::class);

    // Cakupan random dengan seed sama → pilihan user yang sama.
    $first = $validator->resolveUserScope('random', [], 2, 20261101);
    $second = $validator->resolveUserScope('random', [], 2, 20261101);

    expect($first['user_ids'])->toEqual($second['user_ids']);
    expect($first['user_ids'])->toHaveCount(2);
    expect($first['seed'])->toBe(20261101);

    // k melebihi akun tersedia, 'one' dengan 2 pilihan, 'several' kosong.
    expect(fn () => $validator->resolveUserScope('random', [], 99, 20261101))
        ->toThrow(ValidationException::class);
    expect(fn () => $validator->resolveUserScope('one', [$users[0]->id, $users[1]->id]))
        ->toThrow(ValidationException::class);
    expect(fn () => $validator->resolveUserScope('several', []))
        ->toThrow(ValidationException::class);
});

test('SIM-09 cakupan "Semua" dibatasi ke 100 akun pertama, bukan ditolak', function () {
    $developer = m4tDeveloper();

    // 101 akun simulasi: satu di atas batas per request. Dibuat dalam
    // dua request karena pembuatan akun sendiri dibatasi 100/request
    // (SIM-03); offset kedua menjaga email tetap unik.
    [$first] = m4tCreateAccounts($this, $developer, 100);
    [$second] = m4tCreateAccounts($this, $developer, 1, ['offset' => 100]);

    $users = $first->merge($second)->sortBy('id')->values();

    $validator = app(SimulationRequestValidator::class);

    $resolved = $validator->resolveUserScope('all');

    expect($resolved['user_ids'])->toHaveCount(100);
    expect($resolved['user_ids'])->toEqual($users->take(100)->pluck('id')->all());
    expect($resolved['scope_total_sim_accounts'])->toBe(101);
    expect($resolved['scope_capped_out'])->toBe(1);
    expect($resolved['scope_cap'])->toBe(100);

    // Pilih eksplisit > 100 akun lewat checklist tetap ditolak (SIM-09).
    expect(fn () => $validator->validateUserScope($users->pluck('id')->all()))
        ->toThrow(ValidationException::class);
});

test('SIM-19 period-first tanpa akun simulasi ditolak', function () {
    $developer = m4tDeveloper();

    m4tProduct();

    $this->actingAs($developer, 'developer');

    $this->post(route('developer.tx-period.preview'), m4tPeriodFirstPayload())
        ->assertSessionHasErrors('user_scope');

    expect(SimulationRun::count())->toBe(0);
});

// ---- SIM-11/17/28: validasi batas ---------------------------------------

test('SIM-28 periode tidak valid ditolak', function () {
    $developer = m4tDeveloper();

    m4tProduct();

    [$users] = m4tCreateAccounts($this, $developer, 1);

    $this->actingAs($developer, 'developer');

    $base = m4tUserFirstPayload($users->pluck('id')->all());

    // Mulai setelah akhir.
    $this->post(route('developer.tx-user.preview'), array_merge($base, [
        'period_start' => '2026-10-07', 'period_end' => '2026-10-01',
    ]))->assertSessionHasErrors('period');

    // Akhir di masa depan (WITA).
    $today = Carbon::now('Asia/Makassar')->format('Y-m-d');
    $tomorrow = Carbon::now('Asia/Makassar')->addDay()->format('Y-m-d');
    $this->post(route('developer.tx-user.preview'), array_merge($base, [
        'period_start' => $today, 'period_end' => $tomorrow,
    ]))->assertSessionHasErrors('period');

    // Panjang > 366 hari (2025-10-01..2026-10-02 = 367 hari).
    $this->post(route('developer.tx-user.preview'), array_merge($base, [
        'period_start' => '2025-10-01', 'period_end' => '2026-10-02',
    ]))->assertSessionHasErrors('period');

    expect(SimulationRun::where('action', 'tx_user_first')->count())->toBe(0);
});

test('SIM-17 jumlah transaksi di atas batas ditolak', function () {
    $developer = m4tDeveloper();

    m4tProduct();

    [$users] = m4tCreateAccounts($this, $developer, 2);

    $this->actingAs($developer, 'developer');

    // 501 transaksi period-first.
    $this->post(route('developer.tx-period.preview'), m4tPeriodFirstPayload(['transaction_count' => 501]))
        ->assertSessionHasErrors('transaction_count');

    // Total 2 akun × 300 = 600 > 500.
    $this->post(route('developer.tx-user.preview'), m4tUserFirstPayload(
        $users->pluck('id')->all(),
        ['transactions_per_account' => 300],
    ))->assertSessionHasErrors('transactions_per_account');

    expect(SimulationRun::whereIn('action', ['tx_user_first', 'tx_period_first'])->count())->toBe(0);
});

test('SIM-11 cakupan produk tidak valid ditolak', function () {
    $developer = m4tDeveloper();

    $inactive = m4tProduct(['is_active' => false]);
    $active = m4tProduct();

    [$users] = m4tCreateAccounts($this, $developer, 1);

    $this->actingAs($developer, 'developer');

    $ids = $users->pluck('id')->all();

    // Produk nonaktif dipilih.
    $this->post(route('developer.tx-user.preview'), m4tUserFirstPayload($ids, [
        'product_ids' => [$inactive->id],
    ]))->assertSessionHasErrors('product_ids');

    // Item per transaksi melebihi produk terpilih.
    $this->post(route('developer.tx-user.preview'), m4tUserFirstPayload($ids, [
        'product_ids' => [$active->id],
        'items_per_tx' => ['min' => 1, 'max' => 5],
    ]))->assertSessionHasErrors('items_per_tx');

    expect(SimulationRun::where('action', 'tx_user_first')->count())->toBe(0);
});

// ---- SIM-25/26: pratinjau + idempotensi ---------------------------------

test('SIM-25 pratinjau ketiga aksi tidak menulis apa pun ke DB', function () {
    $developer = m4tDeveloper();

    m4tProduct();
    m4tProduct();
    m4tProduct();

    [$users] = m4tCreateAccounts($this, $developer, 2);

    $before = m4tTableCounts();

    $this->actingAs($developer, 'developer');

    $ids = $users->pluck('id')->all();

    $this->post(route('developer.tx-user.preview'), m4tUserFirstPayload($ids))
        ->assertOk()->assertViewHas('plan');
    $this->post(route('developer.tx-period.preview'), m4tPeriodFirstPayload())
        ->assertOk()->assertViewHas('plan');
    $this->post(route('developer.funnel.preview'), m4tFunnelPayload($ids))
        ->assertOk()->assertViewHas('plan');

    expect(m4tTableCounts())->toEqual($before);
});

test('SIM-26 eksekusi ganda dengan kunci sama = satu run, tanpa duplikasi', function () {
    $developer = m4tDeveloper();

    m4tProduct();
    m4tProduct();
    m4tProduct();

    [$users] = m4tCreateAccounts($this, $developer, 2);

    $this->actingAs($developer, 'developer');

    $payload = m4tUserFirstPayload($users->pluck('id')->all());
    $hash = m4tPreviewHash($payload, 'validateUserFirst', 'plan');
    $key = (string) Str::uuid();

    $first = m4tExecute($this, 'developer.tx-user.execute', $payload, $hash, $key);
    $first->assertRedirect()->assertSessionHas('status');

    $run = SimulationRun::query()->where('idempotency_key', $key)->firstOrFail();

    expect($run->status)->toBe('berhasil');
    expect(Order::where('simulation_run_id', $run->id)->count())->toBe(4);

    // Submit ulang yang sama persis → replay run yang sudah berhasil.
    $second = m4tExecute($this, 'developer.tx-user.execute', $payload, $hash, $key);
    $second->assertRedirect(route('developer.run.show', $run));

    expect(SimulationRun::where('action', 'tx_user_first')->count())->toBe(1);
    expect(Order::where('simulation_run_id', $run->id)->count())->toBe(4);
    expect(Order::where('origin', 'developer_simulation')->count())->toBe(4);
});

test('SIM-26 parameter berubah setelah pratinjau → wajib pratinjau ulang', function () {
    $developer = m4tDeveloper();

    m4tProduct();
    m4tProduct();
    m4tProduct();

    [$users] = m4tCreateAccounts($this, $developer, 2);

    $this->actingAs($developer, 'developer');

    $payload = m4tUserFirstPayload($users->pluck('id')->all());
    $this->post(route('developer.tx-user.preview'), $payload)->assertOk();

    $hash = m4tPreviewHash($payload, 'validateUserFirst', 'plan');

    // Ubah jumlah transaksi per akun setelah pratinjau, hash lama dipakai.
    $changed = m4tUserFirstPayload($users->pluck('id')->all(), ['transactions_per_account' => 3]);

    m4tExecute($this, 'developer.tx-user.execute', $changed, $hash)
        ->assertSessionHasErrors('params_hash');

    expect(SimulationRun::where('action', 'tx_user_first')->count())->toBe(0);
    expect(Order::count())->toBe(0);
});

// ---- SIM-13/14/15/24/29/30: eksekusi user-first --------------------------

test('SIM-13/14/15/24/29/30 eksekusi user-first membentuk pesanan+event+pembayaran yang konsisten', function () {
    $developer = m4tDeveloper();

    // SIM-15 (3): periode lampau, produk dibuat "sekarang" (setelah
    // periode) tetap boleh dipakai — tanggal pembuatan produk tidak
    // divalidasi terhadap periode.
    $p1 = m4tProduct(['price_idr' => 25000, 'stock_on_hand' => 50]);
    $p2 = m4tProduct(['price_idr' => 25000, 'stock_on_hand' => 50]);

    [$users] = m4tCreateAccounts($this, $developer, 2);

    $run = m4tRunUserFirst($this, $developer, $users->pluck('id')->all(), [
        'transactions_per_account' => 5,
        'items_per_tx' => ['min' => 1, 'max' => 1],
        'qty_per_item' => ['min' => 1, 'max' => 1],
    ]);

    // 10 pesanan × 1 item: carts_total = ceil(10/0.6) = 17,
    // views_total = ceil(17/0.4) = 43 → 33 event yatim.
    expect($run->status)->toBe('berhasil');
    expect($run->counts)->toBe([
        'accounts' => 0, 'orders' => 10, 'order_items' => 10,
        'payments' => 10, 'events' => 73,
    ]);
    expect($run->period_start->format('Y-m-d'))->toBe('2026-10-01');
    expect($run->period_end->format('Y-m-d'))->toBe('2026-10-07');

    // SIM-14: semua pesanan berakhir di status final, campuran 8/1/1.
    $orders = Order::where('simulation_run_id', $run->id)->get();

    expect($orders)->toHaveCount(10);
    expect($orders->where('status', 'selesai')->count())->toBe(8);
    expect($orders->where('status', 'dibatalkan')->count())->toBe(1);
    expect($orders->where('status', 'kedaluwarsa')->count())->toBe(1);
    expect($orders->whereNotIn('status', ['selesai', 'dibatalkan', 'kedaluwarsa'])->count())->toBe(0);

    $startUtc = Carbon::parse('2026-10-01', 'Asia/Makassar')->startOfDay()->utc();
    $endUtc = Carbon::parse('2026-10-07', 'Asia/Makassar')->endOfDay()->utc();

    foreach ($orders as $order) {
        expect($order->origin)->toBe('developer_simulation');
        expect($order->total_idr)->toBe(25000);
        expect($order->created_at->between($startUtc, $endUtc))->toBeTrue();
        expect($order->payment_deadline_at->equalTo($order->created_at->copy()->addMinutes(30)))->toBeTrue();

        if ($order->status === 'selesai') {
            expect($order->paid_at)->not->toBeNull();
            expect($order->paid_at->between($order->created_at, $order->payment_deadline_at))->toBeTrue();
        } else {
            expect($order->paid_at)->toBeNull();
        }
    }

    // SIM-15 (2): harga item = harga produk saat eksekusi.
    $items = OrderItem::where('simulation_run_id', $run->id)->get();

    expect($items)->toHaveCount(10);

    foreach ($items as $item) {
        expect($item->unit_price_idr)->toBe(25000);
        expect($item->quantity)->toBe(1);
        expect($item->subtotal_idr)->toBe(25000);
    }

    $payments = Payment::where('simulation_run_id', $run->id)->get();

    expect($payments)->toHaveCount(10);
    expect($payments->where('status', 'paid')->count())->toBe(8);
    expect($payments->where('status', 'cancelled')->count())->toBe(1);
    expect($payments->where('status', 'expired')->count())->toBe(1);

    foreach ($payments as $payment) {
        expect($payment->provider)->toBe('simulator');
        expect($payment->is_simulated)->toBeTrue();
        expect($payment->origin)->toBe('developer_simulation');
        expect($payment->qr_url)->toStartWith(config('app.url').'/pay/sim/');
        expect($payment->reference_code)->toMatch('/^(SIMQR-[A-Z2-9]{10}|999\d{13})$/');
        expect($payment->expires_at->equalTo($payment->order->payment_deadline_at))->toBeTrue();

        if ($payment->status === 'paid') {
            expect($payment->paid_at)->not->toBeNull();
            expect($payment->paid_at->between($startUtc, $endUtc))->toBeTrue();
        }
    }

    // SIM-15 (1): stok tidak berubah, tidak ada stock_movements.
    expect($p1->fresh()->stock_on_hand)->toBe(50);
    expect($p1->fresh()->stock_reserved)->toBe(0);
    expect($p2->fresh()->stock_on_hand)->toBe(50);
    expect(StockMovement::count())->toBe(0);

    // SIM-24: funnel menyusut — view ≥ cart ≥ booking ≥ purchase.
    $byType = Event::where('simulation_run_id', $run->id)->get()->countBy('event_type');

    expect($byType['view'])->toBe(36);
    expect($byType['add_to_cart'])->toBe(17);
    expect($byType['booking'])->toBe(10);
    expect($byType['purchase'])->toBe(8);
    expect($byType['cancel'])->toBe(2);

    // SIM-29: semua stempel event di dalam periode.
    foreach (Event::where('simulation_run_id', $run->id)->get() as $event) {
        expect($event->occurred_at->between($startUtc, $endUtc))->toBeTrue();
    }

    // Rantai per item: booking ≤ purchase/cancel, booking = waktu pesanan.
    foreach ($items as $item) {
        $booking = Event::where('order_item_id', $item->id)->where('event_type', 'booking')->firstOrFail();
        $end = Event::where('order_item_id', $item->id)->whereIn('event_type', ['purchase', 'cancel'])->firstOrFail();

        expect($booking->occurred_at->lessThanOrEqualTo($end->occurred_at))->toBeTrue();
        expect($booking->occurred_at->equalTo($item->order->created_at))->toBeTrue();

        if ($item->order->status === 'selesai') {
            expect($end->occurred_at->equalTo($item->order->paid_at))->toBeTrue();
        }
    }

    // Riwayat + transisi ditulis state machine yang sama.
    expect(OrderStatusHistory::where('simulation_run_id', $run->id)->count())->toBe(36);
    expect(PaymentStatusTransition::where('simulation_run_id', $run->id)->count())->toBe(20);

    // SIM-30: trigger panel_developer di kedua state machine.
    expect(PaymentStatusTransition::where('simulation_run_id', $run->id)
        ->where('trigger', 'panel_developer')->where('to_status', 'paid')->count())->toBe(8);
    expect(OrderStatusHistory::where('simulation_run_id', $run->id)
        ->where('trigger', 'panel_developer')->where('actor_type', 'developer')->count())->toBeGreaterThan(0);
});

// ---- SIM-20/21/22/23: distribusi + campuran ----------------------------

test('SIM-20/21/22/23 distribusi waktu/user/produk dan campuran status', function () {
    $developer = m4tDeveloper();

    $p1 = m4tProduct(['price_idr' => 10000]);
    $p2 = m4tProduct(['price_idr' => 20000]);
    m4tProduct();

    [$users] = m4tCreateAccounts($this, $developer, 3);

    $this->actingAs($developer, 'developer');
    $ids = $users->pluck('id')->all();

    // SIM-20 'single': semua booking di satu titik waktu yang sama.
    $single = m4tRunPeriodFirst($this, $developer, [
        'user_scope' => 'all',
        'transaction_count' => 3,
        'time_distribution' => 'single',
        'single_point' => '2026-10-03 10:00',
    ]);

    expect(Order::where('simulation_run_id', $single->id)->pluck('created_at')
        ->map(fn ($c) => $c->format('Y-m-d H:i:s'))->unique()->all())
        ->toBe(['2026-10-03 02:00:00']);

    // SIM-21 'even': 3 akun × 6 transaksi → masing-masing tepat 2 pesanan.
    $even = m4tRunPeriodFirst($this, $developer, [
        'user_scope' => 'all',
        'transaction_count' => 6,
        'user_distribution' => 'even',
    ]);

    foreach ($ids as $userId) {
        expect(Order::where('simulation_run_id', $even->id)->where('user_id', $userId)->count())->toBe(2);
    }

    // SIM-22 subset produk: semua item hanya dari produk terpilih.
    $subset = m4tRunUserFirst($this, $developer, $ids, [
        'transactions_per_account' => 1,
        'product_ids' => [$p1->id],
        'items_per_tx' => ['min' => 1, 'max' => 1],
    ]);

    expect(OrderItem::where('simulation_run_id', $subset->id)->distinct()->pluck('product_id')->all())
        ->toBe([$p1->id]);

    // SIM-23 campuran 100/0/0 → semua selesai (dan pembayarannya paid).
    $allPaid = m4tRunUserFirst($this, $developer, $ids, [
        'transactions_per_account' => 1,
        'status_mix' => ['selesai' => 100, 'dibatalkan' => 0, 'kedaluwarsa' => 0],
    ]);

    expect(Order::where('simulation_run_id', $allPaid->id)->where('status', '!=', 'selesai')->count())->toBe(0);
    expect(Payment::where('simulation_run_id', $allPaid->id)->where('status', '!=', 'paid')->count())->toBe(0);

    // Campuran yang jumlahnya bukan 100% ditolak.
    $this->post(route('developer.tx-user.preview'), m4tUserFirstPayload($ids, [
        'status_mix' => ['selesai' => 80, 'dibatalkan' => 10, 'kedaluwarsa' => 0],
    ]))->assertSessionHasErrors('status_mix');
});

// ---- SIM-31: funnel eksplisit -------------------------------------------

test('SIM-31 funnel menghasilkan jumlah persis seperti yang diminta', function () {
    $developer = m4tDeveloper();

    m4tProduct();
    m4tProduct();

    [$users] = m4tCreateAccounts($this, $developer, 2);

    $run = m4tRunFunnel($this, $developer, $users->pluck('id')->all());

    expect($run->status)->toBe('berhasil');
    expect($run->action)->toBe('funnel_events');

    $orders = Order::where('simulation_run_id', $run->id)->get();

    expect($orders)->toHaveCount(4);
    expect($orders->where('status', 'selesai')->count())->toBe(3);
    expect($orders->whereNotIn('status', ['selesai', 'dibatalkan', 'kedaluwarsa'])->count())->toBe(0);

    // Setiap booking = 1 pesanan 1 item qty 1.
    $items = OrderItem::where('simulation_run_id', $run->id)->get();

    expect($items)->toHaveCount(4);
    expect($items->pluck('quantity')->unique()->all())->toBe([1]);

    // view 10 + cart 6 + booking 4 + purchase/cancel 4 = 24 event.
    $byType = Event::where('simulation_run_id', $run->id)->get()->countBy('event_type');

    expect($byType['view'])->toBe(10);
    expect($byType['add_to_cart'])->toBe(6);
    expect($byType['booking'])->toBe(4);
    expect($byType['purchase'])->toBe(3);
    expect($byType['cancel'])->toBe(1);
    expect(Event::where('simulation_run_id', $run->id)->count())->toBe(24);

    // Pembelian = pesanan yang dibayar.
    expect(Payment::where('simulation_run_id', $run->id)->where('status', 'paid')->count())->toBe(3);
});

test('SIM-31 jumlah funnel yang tidak menyusut atau melewati batas ditolak', function () {
    $developer = m4tDeveloper();

    m4tProduct();

    [$users] = m4tCreateAccounts($this, $developer, 1);

    $this->actingAs($developer, 'developer');

    $ids = $users->pluck('id')->all();
    $base = m4tFunnelPayload($ids);

    // view < cart.
    $this->post(route('developer.funnel.preview'), array_merge($base, [
        'view_count' => 5, 'add_to_cart_count' => 6,
    ]))->assertSessionHasErrors('view_count');

    // purchase > booking.
    $this->post(route('developer.funnel.preview'), array_merge($base, [
        'purchase_count' => 5,
    ]))->assertSessionHasErrors('view_count');

    // booking 0 (minimal 1 booking).
    $this->post(route('developer.funnel.preview'), array_merge($base, [
        'booking_count' => 0, 'purchase_count' => 0,
    ]))->assertSessionHasErrors('booking_count');

    // Total event 1500+1200+2×800 = 4300 > 2000.
    $this->post(route('developer.funnel.preview'), array_merge($base, [
        'view_count' => 1500, 'add_to_cart_count' => 1200,
        'booking_count' => 800, 'purchase_count' => 500,
    ]))->assertSessionHasErrors('view_count');

    expect(SimulationRun::where('action', 'funnel_events')->count())->toBe(0);
});

// ---- SIM-27: rollback ----------------------------------------------------

test('SIM-27 kegagalan di tengah eksekusi → rollback total, run gagal, audit tetap ada', function () {
    $developer = m4tDeveloper();

    m4tProduct();

    [$users] = m4tCreateAccounts($this, $developer, 2);

    // Bangun plan dengan SEMUA pesanan memakai satu metode (qris), lalu
    // nonaktifkan metode itu SETELAH plan dibuat — kegagalan terjadi di
    // dalam transaksi eksekutor (pesanan + item sudah ditulis) saat
    // pembayaran pertama dibuat, jadi rollback harus menghapus semuanya.
    $payload = m4tUserFirstPayload($users->pluck('id')->all(), [
        'transactions_per_account' => 2,
        'items_per_tx' => ['min' => 1, 'max' => 1],
        'qty_per_item' => ['min' => 1, 'max' => 1],
    ]);

    $params = app(SimulationRequestValidator::class)->validateUserFirst($payload);
    $params['payment_methods'] = ['qris'];
    $plan = app(FunnelPlanner::class)->plan($params, $params['seed']);

    $run = app(SimulationAudit::class)->recordRunStart(
        $developer, 'tx_user_first', $params, $params['seed'],
        (string) Str::uuid(), $params['period_start'], $params['period_end'],
    );

    config(['payment.methods.qris.active' => false]);

    expect(fn () => app(SimulationExecutor::class)->execute($plan, $run))
        ->toThrow(ValidationException::class);

    // Tidak ada data simulasi yang tersisa dari run yang gagal.
    expect(Order::where('origin', 'developer_simulation')->count())->toBe(0);
    expect(OrderItem::where('simulation_run_id', $run->id)->count())->toBe(0);
    expect(Payment::where('origin', 'developer_simulation')->count())->toBe(0);
    expect(Event::where('origin', 'developer_simulation')->count())->toBe(0);
    expect(OrderStatusHistory::where('simulation_run_id', $run->id)->count())->toBe(0);
    expect(PaymentStatusTransition::where('simulation_run_id', $run->id)->count())->toBe(0);
    expect(StockMovement::count())->toBe(0);

    // Run tercatat gagal + pesan, dan baris auditnya tetap ada.
    $fresh = $run->fresh();

    expect($fresh->status)->toBe('gagal');
    expect($fresh->error_message)->not->toBeNull();
    expect($fresh->error_message)->not->toBe('');
    expect(SimulationAuditLog::where('run_id', $run->id)->where('outcome', 'gagal')->count())->toBe(1);
});

// ---- SIM-35/36/37: run, audit, jejak ------------------------------------

test('SIM-35 run tercatat lengkap (developer, aksi, params, seed, periode, status, counts)', function () {
    $developer = m4tDeveloper();

    m4tProduct();
    m4tProduct();
    m4tProduct();

    [$users, $accountRun] = m4tCreateAccounts($this, $developer, 2, ['seed' => 42]);

    expect($accountRun->developer_id)->toBe($developer->id);
    expect($accountRun->action)->toBe('create_accounts');
    expect($accountRun->params['count'])->toBe(2);
    expect($accountRun->params['seed'])->toBe(42);
    expect($accountRun->seed)->toBe(42);
    expect($accountRun->period_start)->toBeNull();
    expect($accountRun->period_end)->toBeNull();
    expect($accountRun->status)->toBe('berhasil');
    expect($accountRun->counts)->toBe(['accounts' => 2]);
    expect($accountRun->started_at)->not->toBeNull();
    expect($accountRun->finished_at)->not->toBeNull();
    expect($accountRun->idempotency_key)->not->toBeNull();

    $txRun = m4tRunUserFirst($this, $developer, $users->pluck('id')->all(), [
        'transactions_per_account' => 1,
    ]);

    expect($txRun->action)->toBe('tx_user_first');
    expect($txRun->seed)->toBe(20261101);
    expect($txRun->params['seed'])->toBe(20261101);
    expect($txRun->period_start->format('Y-m-d'))->toBe('2026-10-01');
    expect($txRun->period_end->format('Y-m-d'))->toBe('2026-10-07');
    expect($txRun->status)->toBe('berhasil');
    expect($txRun->counts['orders'])->toBe(2);
});

test('SIM-36 audit append-only: baris hasil run selamat dari reset', function () {
    $developer = m4tDeveloper();

    m4tProduct();
    m4tProduct();
    m4tProduct();

    [$users, $accountRun] = m4tCreateAccounts($this, $developer, 1);

    $log = SimulationAuditLog::where('run_id', $accountRun->id)->firstOrFail();

    expect($log->action)->toBe('create_accounts');
    expect($log->outcome)->toBe('berhasil');
    expect($log->developer_id)->toBe($developer->id);
    expect($log->summary['counts'])->toBe(['accounts' => 1]);
    expect($log->summary['seed'])->toBe(20261101);
    expect($log->occurred_at)->not->toBeNull();

    m4tRunUserFirst($this, $developer, $users->pluck('id')->all(), ['transactions_per_account' => 1]);

    $before = SimulationAuditLog::where('run_id', $accountRun->id)->count();

    $this->actingAs($developer, 'developer');
    $this->post(route('developer.reset.all'), ['phrase' => 'HAPUS SEMUA SIMULASI'])->assertRedirect();

    // Baris audit run lama tidak berkurang; reset hanya menambah barisnya sendiri.
    expect(SimulationAuditLog::where('run_id', $accountRun->id)->count())->toBe($before);
    expect(SimulationAuditLog::where('action', 'reset_all')->where('outcome', 'berhasil')->count())->toBe(1);
});

test('SIM-37 daftar run bisa difilter dan detail run menampilkan hasil', function () {
    $developer = m4tDeveloper();

    m4tProduct();
    m4tProduct();
    m4tProduct();

    [$users, $accountRun] = m4tCreateAccounts($this, $developer, 1);
    $txRun = m4tRunUserFirst($this, $developer, $users->pluck('id')->all(), [
        'transactions_per_account' => 1,
    ]);

    $this->actingAs($developer, 'developer');

    // Filter aksi: hanya run buat-akun.
    $this->get(route('developer.run.index', ['action' => 'create_accounts']))
        ->assertOk()
        ->assertViewHas('runs', fn ($runs) => $runs->total() === 1 && $runs->first()->id === $accountRun->id)
        ->assertSee(substr($accountRun->id, 0, 8));

    // Filter status: keduanya berhasil.
    $this->get(route('developer.run.index', ['status' => 'berhasil']))
        ->assertOk()
        ->assertViewHas('runs', fn ($runs) => $runs->total() === 2);

    // Filter tanggal (tanggal UTC saat run dibuat).
    $date = $accountRun->started_at->format('Y-m-d');
    $this->get(route('developer.run.index', ['date' => $date]))
        ->assertOk()
        ->assertViewHas('runs', fn ($runs) => $runs->total() === 2);

    // Tanggal lain: tidak ada run.
    $this->get(route('developer.run.index', ['date' => '2026-01-01']))
        ->assertOk()
        ->assertViewHas('runs', fn ($runs) => $runs->total() === 0);

    // Detail run transaksi: id, seed, pesanan, dan jejak audit tampil.
    $order = Order::where('simulation_run_id', $txRun->id)->firstOrFail();

    $this->get(route('developer.run.show', $txRun))
        ->assertOk()
        ->assertSee($txRun->id)
        ->assertSee((string) $txRun->seed)
        ->assertSee($order->order_number)
        ->assertSee('berhasil');

    // Detail run akun: email akun hasil tampil.
    $this->get(route('developer.run.show', $accountRun))
        ->assertOk()
        ->assertSee($users[0]->email);
});

// ---- SIM-38: reset per run ----------------------------------------------

test('SIM-38 reset run transaksi menghapus hanya data run itu', function () {
    $developer = m4tDeveloper();

    m4tProduct();
    m4tProduct();
    m4tProduct();

    [$users, $accountRun] = m4tCreateAccounts($this, $developer, 2);

    $txRun = m4tRunUserFirst($this, $developer, $users->pluck('id')->all(), [
        'transactions_per_account' => 1,
    ]);

    expect(Order::where('simulation_run_id', $txRun->id)->count())->toBe(2);

    $this->actingAs($developer, 'developer');

    $this->post(route('developer.reset.run', $txRun), ['phrase' => 'HAPUS RUN'])
        ->assertRedirect(route('developer.reset.index'))
        ->assertSessionHas('reset_summary');

    expect(Order::where('simulation_run_id', $txRun->id)->count())->toBe(0);
    expect(OrderItem::where('simulation_run_id', $txRun->id)->count())->toBe(0);
    expect(Payment::where('simulation_run_id', $txRun->id)->count())->toBe(0);
    expect(Event::where('simulation_run_id', $txRun->id)->count())->toBe(0);
    expect(OrderStatusHistory::where('simulation_run_id', $txRun->id)->count())->toBe(0);
    expect(PaymentStatusTransition::where('simulation_run_id', $txRun->id)->count())->toBe(0);

    // Akun (milik run lain) selamat; run akun tetap berhasil.
    expect(User::where('origin', 'developer_simulation')->count())->toBe(2);
    expect($accountRun->fresh()->status)->toBe('berhasil');

    // Run transaksi menjadi direset + audit reset tercatat.
    $fresh = $txRun->fresh();

    expect($fresh->status)->toBe('direset');
    expect($fresh->reset_at)->not->toBeNull();
    expect(SimulationAuditLog::where('run_id', $txRun->id)->where('action', 'reset_run')->count())->toBe(1);
});

test('SIM-38 reset run akun menghapus akun beserta alamatnya', function () {
    $developer = m4tDeveloper();

    [$users, $run] = m4tCreateAccounts($this, $developer, 3);

    $userIds = $users->pluck('id')->all();

    expect(Address::whereIn('user_id', $userIds)->count())->toBe(3);

    $this->actingAs($developer, 'developer');

    $this->post(route('developer.reset.run', $run), ['phrase' => 'HAPUS RUN'])->assertRedirect();

    expect(User::whereIn('id', $userIds)->count())->toBe(0);
    expect(Address::whereIn('user_id', $userIds)->count())->toBe(0);
    expect($run->fresh()->status)->toBe('direset');
});

test('SIM-38 akun dengan data normal dinonaktifkan, bukan dihapus', function () {
    $developer = m4tDeveloper();

    [$users, $run] = m4tCreateAccounts($this, $developer, 1);

    $sim = $users[0];

    // Pesanan normal milik akun simulasi (mis. akun dipakai berbelanja
    // lewat sistem asli sebelum reset).
    $normalOrder = Order::factory()->create([
        'user_id' => $sim->id, 'origin' => 'normal', 'simulation_run_id' => null,
    ]);

    $this->actingAs($developer, 'developer');

    $this->post(route('developer.reset.run', $run), ['phrase' => 'HAPUS RUN'])
        ->assertRedirect()
        ->assertSessionHas('reset_summary');

    $fresh = $sim->fresh();

    expect($fresh)->not->toBeNull();
    expect($fresh->is_active)->toBeFalse();
    expect($fresh->deactivated_at)->not->toBeNull();
    expect($fresh->deactivation_reason)->not->toBeNull();

    // Pesanan normal dan alamat akun tetap ada.
    expect(Order::find($normalOrder->id))->not->toBeNull();
    expect(Address::where('user_id', $sim->id)->count())->toBe(1);
});

test('SIM-38/R8 cart akun dihapus sebelum akunnya (FK RESTRICT)', function () {
    $developer = m4tDeveloper();

    [$users, $run] = m4tCreateAccounts($this, $developer, 1);

    $sim = $users[0];

    Cart::factory()->create(['user_id' => $sim->id]);

    $this->actingAs($developer, 'developer');

    $this->post(route('developer.reset.run', $run), ['phrase' => 'HAPUS RUN'])->assertRedirect();

    expect(Cart::where('user_id', $sim->id)->count())->toBe(0);
    expect(User::find($sim->id))->toBeNull();
});

test('SIM-38 run gagal tidak bisa direset; frasa salah ditolak', function () {
    $developer = m4tDeveloper();

    $gagalRun = app(SimulationAudit::class)->recordRunStart(
        $developer, 'tx_user_first', ['seed' => 1], 1,
        (string) Str::uuid(), '2026-10-01', '2026-10-07',
    );
    app(SimulationAudit::class)->recordRunFailed($gagalRun, 'gagal buatan untuk tes');

    $this->actingAs($developer, 'developer');

    $this->post(route('developer.reset.run', $gagalRun), ['phrase' => 'HAPUS RUN'])
        ->assertSessionHasErrors('run');

    expect($gagalRun->fresh()->status)->toBe('gagal');

    // Frasa salah: run berhasil tidak ikut terhapus.
    [$users, $run] = m4tCreateAccounts($this, $developer, 1);

    $this->post(route('developer.reset.run', $run), ['phrase' => 'HAPUS'])
        ->assertSessionHasErrors('phrase');

    expect($run->fresh()->status)->toBe('berhasil');
    expect(User::where('origin', 'developer_simulation')->count())->toBe(1);
});

// ---- SIM-39: reset semua -------------------------------------------------

test('SIM-39 reset semua: baris normal identik, data simulasi hilang, audit selamat', function () {
    $developer = m4tDeveloper();

    // Data normal (sistem asli) — harus utuh setelah reset.
    $normalUser = User::factory()->create();
    $admin = User::factory()->admin()->create();
    $product = m4tProduct();
    m4tProduct();
    m4tProduct();
    $normalOrder = Order::factory()->create(['user_id' => $normalUser->id]);
    $normalEvent = Event::factory()->create([
        'user_id' => $normalUser->id, 'product_id' => $product->id, 'origin' => 'normal',
    ]);
    $normalPayment = Payment::factory()->create([
        'order_id' => $normalOrder->id, 'user_id' => $normalUser->id,
        'origin' => 'normal',
    ]);
    $normalAddress = Address::factory()->create(['user_id' => $normalUser->id]);

    // Data simulasi.
    [$users] = m4tCreateAccounts($this, $developer, 2);
    $txRun = m4tRunUserFirst($this, $developer, $users->pluck('id')->all(), [
        'transactions_per_account' => 1,
    ]);

    // Satu run gagal — harus tetap gagal setelah reset.
    $gagalRun = app(SimulationAudit::class)->recordRunStart(
        $developer, 'tx_user_first', ['seed' => 1], 1,
        (string) Str::uuid(), '2026-10-01', '2026-10-07',
    );
    app(SimulationAudit::class)->recordRunFailed($gagalRun, 'gagal buatan untuk tes');

    $normalCounts = [
        'users' => User::where('origin', 'normal')->count(),
        'orders' => Order::where('origin', 'normal')->count(),
        'events' => Event::where('origin', 'normal')->count(),
        'payments' => Payment::where('origin', 'normal')->count(),
        'addresses' => Address::whereIn('user_id', User::where('origin', 'normal')->pluck('id'))->count(),
        'stock_movements' => StockMovement::count(),
    ];

    $auditBefore = SimulationAuditLog::count();

    $this->actingAs($developer, 'developer');

    // Frasa salah dulu: tidak ada yang terhapus.
    $this->post(route('developer.reset.all'), ['phrase' => 'HAPUS'])
        ->assertSessionHasErrors('phrase');

    expect(User::where('origin', 'developer_simulation')->count())->toBe(2);

    $this->post(route('developer.reset.all'), ['phrase' => 'HAPUS SEMUA SIMULASI'])
        ->assertRedirect(route('developer.reset.index'))
        ->assertSessionHas('reset_summary');

    // Baris normal identik sebelum/sesudah.
    expect([
        'users' => User::where('origin', 'normal')->count(),
        'orders' => Order::where('origin', 'normal')->count(),
        'events' => Event::where('origin', 'normal')->count(),
        'payments' => Payment::where('origin', 'normal')->count(),
        'addresses' => Address::whereIn('user_id', User::where('origin', 'normal')->pluck('id'))->count(),
        'stock_movements' => StockMovement::count(),
    ])->toEqual($normalCounts);

    expect(Order::find($normalOrder->id))->not->toBeNull();
    expect(Event::find($normalEvent->id))->not->toBeNull();
    expect(Payment::find($normalPayment->id))->not->toBeNull();
    expect(Address::find($normalAddress->id))->not->toBeNull();

    // Semua data simulasi hilang.
    expect(User::where('origin', 'developer_simulation')->count())->toBe(0);
    expect(Order::where('origin', 'developer_simulation')->count())->toBe(0);
    expect(Event::where('origin', 'developer_simulation')->count())->toBe(0);
    expect(Payment::where('origin', 'developer_simulation')->count())->toBe(0);

    // Run berhasil → direset; run gagal tetap gagal.
    expect(SimulationRun::where('status', 'berhasil')->count())->toBe(0);
    expect($txRun->fresh()->status)->toBe('direset');
    expect($gagalRun->fresh()->status)->toBe('gagal');

    // Audit tidak berkurang — reset hanya menambah baris reset_all.
    expect(SimulationAuditLog::count())->toBeGreaterThanOrEqual($auditBefore);
    expect(SimulationAuditLog::where('action', 'reset_all')->count())->toBe(1);
});

// ---- SIM-40/41: perlindungan data normal ---------------------------------

test('SIM-40/41 panel tidak mengubah data normal apa pun', function () {
    $admin = User::factory()->admin()->create();
    $buyer = User::factory()->create();

    $developer = m4tDeveloper();

    m4tProduct();
    m4tProduct();
    m4tProduct();

    [$users] = m4tCreateAccounts($this, $developer, 1);

    $this->actingAs($developer, 'developer');

    // ID normal yang disubmit manual ke aksi simulasi ditolak.
    $this->post(route('developer.tx-user.preview'), m4tUserFirstPayload([$buyer->id]))
        ->assertSessionHasErrors('user_ids');

    // Siklus simulasi penuh + reset semua.
    m4tRunUserFirst($this, $developer, $users->pluck('id')->all(), ['transactions_per_account' => 1]);
    $this->post(route('developer.reset.all'), ['phrase' => 'HAPUS SEMUA SIMULASI'])->assertRedirect();

    expect($admin->fresh()->role)->toBe('admin_toko');
    expect($admin->fresh()->is_active)->toBeTrue();
    expect($buyer->fresh()->role)->toBe('pembeli');
    expect($buyer->fresh()->is_active)->toBeTrue();
    expect($buyer->fresh()->name)->toBe($buyer->name);
});

// ---- SIM-42: seeder contoh ------------------------------------------------

test('SIM-42 seeder: 6 kategori, 40 produk aktif, idempoten', function () {
    $this->seed(CatalogSeeder::class);

    expect(Category::count())->toBe(6);
    expect(Product::where('is_active', true)->count())->toBe(40);
    expect(Product::where('is_active', false)->count())->toBe(1);
    expect(Product::count())->toBe(41);

    foreach (Category::all() as $category) {
        expect(Product::where('category_id', $category->id)->where('is_active', true)->count())
            ->toBeGreaterThanOrEqual(5);
    }

    // Contoh yang dirujuk PRD tetap ada.
    expect(Product::where('name', 'Kotak Pensil Kayu Edisi Lama')->where('is_active', false)->exists())->toBeTrue();
    expect(Product::where('name', 'Isi Stapler No. 10')->where('stock_on_hand', 0)->exists())->toBeTrue();
    expect(Product::where('name', 'Tas Ransel Sekolah Polos')->exists())->toBeTrue();

    $movements = StockMovement::count();

    // Dijalankan kedua kali: hitungan tetap (idempoten).
    $this->seed(CatalogSeeder::class);

    expect(Category::count())->toBe(6);
    expect(Product::where('is_active', true)->count())->toBe(40);
    expect(Product::count())->toBe(41);
    expect(StockMovement::count())->toBe($movements);
});

// ---- M7 SIM-34 / SIM-32 / SIM-33 ------------------------------------------

test('M7 SIM-34 validator menerima distribusi waktu menaik dan menurun (Aksi 2 + Aksi 3)', function () {
    $developer = m4tDeveloper();

    m4tProduct();
    m4tProduct();
    m4tProduct();

    [$users] = m4tCreateAccounts($this, $developer, 2);

    $this->actingAs($developer, 'developer');

    // Aksi 2 (user-first) + Aksi 3 (period-first) berbagi validateCommonTx.
    $this->post(route('developer.tx-user.preview'), m4tUserFirstPayload(
        [$users[0]->id],
        ['transactions_per_account' => 1, 'time_distribution' => 'rising'],
    ))->assertOk();

    $this->post(route('developer.tx-period.preview'), m4tPeriodFirstPayload(
        ['transaction_count' => 2, 'time_distribution' => 'falling'],
    ))->assertOk();
});

test('M7 SIM-34 validator menerima distribusi waktu menaik dan menurun (Aksi 4 funnel)', function () {
    $developer = m4tDeveloper();

    m4tProduct();

    [$users] = m4tCreateAccounts($this, $developer, 1);

    $this->actingAs($developer, 'developer');

    $this->post(route('developer.funnel.preview'), m4tFunnelPayload(
        [$users[0]->id],
        ['time_distribution' => 'rising'],
    ))->assertOk();

    $this->post(route('developer.funnel.preview'), m4tFunnelPayload(
        [$users[0]->id],
        ['time_distribution' => 'falling'],
    ))->assertOk();
});

test('M7 SIM-34 validator menolak distribusi waktu yang tidak dikenal', function () {
    $developer = m4tDeveloper();

    m4tProduct();

    [$users] = m4tCreateAccounts($this, $developer, 1);

    $this->actingAs($developer, 'developer');

    $this->post(route('developer.tx-period.preview'), m4tPeriodFirstPayload(
        ['time_distribution' => 'zigzag'],
    ))->assertSessionHasErrors('time_distribution');
});

test('M7 SIM-32/33 tombol preset tampil di form funnel dan nilainya lolos validasi SIM-31', function () {
    $developer = m4tDeveloper();

    m4tProduct();

    [$users] = m4tCreateAccounts($this, $developer, 1);

    $page = $this->actingAs($developer, 'developer')
        ->get(route('developer.funnel.create'))
        ->assertOk();

    // Dua tombol preset + radio distribusi waktu baru (SIM-34).
    $page->assertSee('Minat tanpa pembelian')
        ->assertSee('Funnel lengkap')
        ->assertSee('data-view="200"', false)
        ->assertSee('data-booking="40"', false)
        ->assertSee('data-booking="60"', false)
        ->assertSee('Menaik')
        ->assertSee('Menurun');

    // SIM-32: view 200, add_to_cart 120, booking 40, purchase 0
    // (total event 200 + 120 + 2×40 = 400 ≤ 2000).
    $this->post(route('developer.funnel.preview'), m4tFunnelPayload(
        [$users[0]->id],
        ['view_count' => 200, 'add_to_cart_count' => 120, 'booking_count' => 40, 'purchase_count' => 0],
    ))->assertOk();

    // SIM-33: view 200, add_to_cart 120, booking 60, purchase 40
    // (total event 200 + 120 + 2×60 = 440 ≤ 2000).
    $this->post(route('developer.funnel.preview'), m4tFunnelPayload(
        [$users[0]->id],
        ['view_count' => 200, 'add_to_cart_count' => 120, 'booking_count' => 60, 'purchase_count' => 40],
    ))->assertOk();
});

// ---- Retry/cancel run nyangkut (keputusan Ren 2026-11-11) -------------
// Run yang request eksekusinya mati di tengah jalan (500/timeout)
// tertinggal 'Berjalan' selamanya. "Coba lagi" membuat run BARU
// dengan params identik; "Batalkan" hanya menandai run 'Gagal'.

test('retry run period-first yang nyangkut membuat run baru dengan params identik dan selesai', function () {
    $developer = m4tDeveloper();

    m4tProduct();
    m4tProduct();
    m4tProduct();

    [$users] = m4tCreateAccounts($this, $developer, 2);

    $original = m4tRunPeriodFirst($this, $developer);

    expect($original->status)->toBe('berhasil');

    $stuck = m4tStuckRun($developer, $original);

    expect($stuck->status)->toBe('running');

    $this->actingAs($developer, 'developer');

    // Tombol tampil di daftar dan detail run yang nyangkut.
    $this->get(route('developer.run.index'))->assertOk()->assertSee('Coba lagi')->assertSee('Batalkan');
    $this->get(route('developer.run.show', $stuck))->assertOk()->assertSee('Coba lagi')->assertSee('Batalkan');

    $response = $this->post(route('developer.run.retry', $stuck));

    $newRun = SimulationRun::query()
        ->where('action', 'tx_period_first')
        ->whereNotIn('id', [$original->id, $stuck->id])
        ->firstOrFail();

    $response->assertRedirect(route('developer.run.show', $newRun))->assertSessionHas('status');

    expect($newRun->status)->toBe('berhasil');
    expect($newRun->params)->toEqual($stuck->params);
    expect($newRun->seed)->toBe($stuck->seed);
    expect($newRun->period_start->format('Y-m-d'))->toBe('2026-10-01');
    expect($newRun->period_end->format('Y-m-d'))->toBe('2026-10-07');
    expect($newRun->counts['orders'])->toBe(4);
    expect(Order::where('simulation_run_id', $newRun->id)->count())->toBe(4);

    // Run lama tidak disentuh: tetap 'Berjalan', tanpa finished_at.
    $fresh = $stuck->fresh();

    expect($fresh->status)->toBe('running');
    expect($fresh->finished_at)->toBeNull();
    expect(Order::where('simulation_run_id', $stuck->id)->count())->toBe(0);
});

test('retry run funnel yang gagal membuat run baru yang selesai', function () {
    $developer = m4tDeveloper();

    m4tProduct();
    m4tProduct();

    [$users] = m4tCreateAccounts($this, $developer, 2);

    $original = m4tRunFunnel($this, $developer, $users->pluck('id')->all());

    $failed = m4tStuckRun($developer, $original, 'gagal');

    expect($failed->status)->toBe('gagal');

    $this->actingAs($developer, 'developer');

    $this->post(route('developer.run.retry', $failed))->assertRedirect()->assertSessionHas('status');

    $newRun = SimulationRun::query()
        ->where('action', 'funnel_events')
        ->whereNotIn('id', [$original->id, $failed->id])
        ->firstOrFail();

    expect($newRun->status)->toBe('berhasil');
    expect($newRun->params)->toEqual($failed->params);
    expect($newRun->counts['orders'])->toBe(4);
    expect(Order::where('simulation_run_id', $newRun->id)->count())->toBe(4);

    expect($failed->fresh()->status)->toBe('gagal');
});

test('retry run buat-akun yang nyangkut membuat akun yang sama', function () {
    $developer = m4tDeveloper();

    // Run buat-akun nyangkut: baris run dibuat, akun belum pernah dibuat.
    $stuck = app(SimulationAudit::class)->recordRunStart(
        $developer, 'create_accounts',
        ['count' => 2, 'mode' => 'deterministic', 'seed' => 42, 'offset' => 0],
        42, (string) Str::uuid(),
    );

    expect($stuck->status)->toBe('running');

    $this->actingAs($developer, 'developer');

    $this->post(route('developer.run.retry', $stuck))->assertRedirect()->assertSessionHas('status');

    $newRun = SimulationRun::query()
        ->where('action', 'create_accounts')
        ->where('id', '!=', $stuck->id)
        ->firstOrFail();

    expect($newRun->status)->toBe('berhasil');
    expect($newRun->params)->toEqual($stuck->params);
    expect($newRun->counts)->toBe(['accounts' => 2]);

    $users = User::where('simulation_run_id', $newRun->id)->orderBy('id')->get();

    expect($users)->toHaveCount(2);
    expect($users->pluck('email')->all())->toBe([
        'simulasi0001@sim.ira-stationery.test',
        'simulasi0002@sim.ira-stationery.test',
    ]);

    expect($stuck->fresh()->status)->toBe('running');
    expect(User::where('simulation_run_id', $stuck->id)->count())->toBe(0);
});

test('retry run yang sudah berhasil ditolak', function () {
    $developer = m4tDeveloper();

    m4tProduct();

    [$users] = m4tCreateAccounts($this, $developer, 1);

    $run = m4tRunUserFirst($this, $developer, $users->pluck('id')->all(), [
        'transactions_per_account' => 1,
    ]);

    expect($run->status)->toBe('berhasil');

    $this->actingAs($developer, 'developer');

    // Tombol tidak tampil di detail run yang sudah berhasil.
    $this->get(route('developer.run.show', $run))
        ->assertOk()
        ->assertDontSee('Coba lagi')
        ->assertDontSee('Batalkan');

    $this->post(route('developer.run.retry', $run))->assertSessionHasErrors('run');

    expect($run->fresh()->status)->toBe('berhasil');
    expect(SimulationRun::where('action', 'tx_user_first')->count())->toBe(1);
    expect(Order::where('origin', 'developer_simulation')->count())->toBe(1);
});

test('batalkan run yang nyangkut menandainya gagal + jejak audit', function () {
    $developer = m4tDeveloper();

    m4tProduct();

    [$users] = m4tCreateAccounts($this, $developer, 1);

    $original = m4tRunUserFirst($this, $developer, $users->pluck('id')->all(), [
        'transactions_per_account' => 1,
    ]);

    $stuck = m4tStuckRun($developer, $original);

    $this->actingAs($developer, 'developer');

    $this->post(route('developer.run.cancel', $stuck))
        ->assertRedirect(route('developer.run.index'))
        ->assertSessionHas('status');

    $fresh = $stuck->fresh();

    expect($fresh->status)->toBe('gagal');
    expect($fresh->finished_at)->not->toBeNull();
    expect($fresh->error_message)->toContain('Dibatalkan manual');

    $log = SimulationAuditLog::where('run_id', $stuck->id)->where('outcome', 'gagal')->firstOrFail();

    expect($log->action)->toBe('tx_user_first');
    expect($log->summary['error'])->toContain('Dibatalkan manual');

    // Pembatalan tidak membuat/menghapus data apa pun.
    expect(Order::where('simulation_run_id', $stuck->id)->count())->toBe(0);
    expect($original->fresh()->status)->toBe('berhasil');
});

test('batalkan run yang sudah berhasil ditolak', function () {
    $developer = m4tDeveloper();

    m4tProduct();

    [$users] = m4tCreateAccounts($this, $developer, 1);

    $run = m4tRunUserFirst($this, $developer, $users->pluck('id')->all(), [
        'transactions_per_account' => 1,
    ]);

    $this->actingAs($developer, 'developer');

    $this->post(route('developer.run.cancel', $run))->assertSessionHasErrors('run');

    expect($run->fresh()->status)->toBe('berhasil');
    expect(SimulationAuditLog::where('run_id', $run->id)->where('outcome', 'gagal')->count())->toBe(0);
});

test('retry run distribusi satu titik: hash params selamat dari round-trip JSON', function () {
    $developer = m4tDeveloper();

    m4tProduct();

    [$users] = m4tCreateAccounts($this, $developer, 2);

    $original = m4tRunPeriodFirst($this, $developer, [
        'transaction_count' => 2,
        'time_distribution' => 'single',
        'single_point' => '2026-10-03 10:00',
    ]);

    expect($original->status)->toBe('berhasil');

    $stuck = m4tStuckRun($developer, $original);

    $this->actingAs($developer, 'developer');

    $this->post(route('developer.run.retry', $stuck))->assertRedirect()->assertSessionHas('status');

    $newRun = SimulationRun::query()
        ->where('action', 'tx_period_first')
        ->whereNotIn('id', [$original->id, $stuck->id])
        ->firstOrFail();

    expect($newRun->status)->toBe('berhasil');
    expect($newRun->params)->toEqual($stuck->params);

    // Semua booking tetap di titik yang sama (10:00 WITA = 02:00 UTC).
    expect(Order::where('simulation_run_id', $newRun->id)->pluck('created_at')
        ->map(fn ($c) => $c->format('Y-m-d H:i:s'))->unique()->all())
        ->toBe(['2026-10-03 02:00:00']);
});
