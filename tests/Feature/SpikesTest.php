<?php

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// M0 spikes (blueprint §16). These are investigation tests, not production
// features. B-01's full payment adapter is M3b; B-02's full simulator is M4.
// No Python is involved in either spike.

// B-01: an internal sub-request dispatched through the HTTP kernel (no
// external HTTP) can return a response. M3b must still prove, with a
// payment-specific regression test, that such a dispatch does not leak
// request/session/transaction state into the outer request.
test('M0 spike B-01 internal sub-request through the kernel returns a response', function () {
    $kernel = app(\Illuminate\Contracts\Http\Kernel::class);
    $response = $kernel->handle(Request::create('/', 'GET'));
    $kernel->terminate(Request::create('/', 'GET'), $response);

    expect($response->getStatusCode())->toBe(200);
});

// B-02: bulk inserts in chunks inside one transaction complete for 1,000
// rows. The threshold is deliberately generous (30s) so a slow laptop does
// not flake; the point is completion + correct count, not a benchmark
// number. M4 will revisit chunk size if it ever exceeds this.
test('M0 spike B-02 bulk insert of 1000 rows in one transaction completes', function () {
    $rows = [];
    for ($i = 0; $i < 1000; $i++) {
        $rows[] = [
            'name' => 'Bulk '.$i,
            'email' => 'bulk'.$i.'@contoh.test',
            'password' => 'hash-placeholder',
            'role' => 'pembeli',
            'origin' => 'normal',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    $started = microtime(true);
    DB::transaction(function () use ($rows) {
        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('users')->insert($chunk);
        }
    });
    $elapsed = microtime(true) - $started;

    expect(User::count())->toBe(1000);
    expect($elapsed)->toBeLessThan(30);
});
