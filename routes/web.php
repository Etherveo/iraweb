<?php

use App\Http\Controllers\Admin\BuyerAccountController;
use App\Http\Controllers\Auth\BuyerAuthController;
use App\Http\Controllers\Auth\DeveloperAuthController;
use App\Http\Controllers\Developer\SimulationAccountController;
use App\Http\Controllers\PageController;
use Illuminate\Support\Facades\Route;

// M0+M1 route map (blueprint §12, PRD §12).
// Keputusan 2026-11-05: login disatukan — ONE login form at /masuk for
// all three actors (aktor switch), guards/tables stay separate.
// Every protected route declares its guard + role (ACC-08); the only
// auth-only exceptions are the two logout routes, where any authenticated
// user of that guard may log out (that is the point of logout).
// No catalog, cart, order, payment, simulator, or analytics routes yet:
// those are M2-M7.

Route::get('/', function () {
    return view('welcome');
})->name('home');

// ---- Register (pembeli-only, ACC-01) ----
Route::get('/daftar', [BuyerAuthController::class, 'showRegister'])->name('daftar');
Route::post('/daftar', [BuyerAuthController::class, 'register']);

// ---- Unified login (ACC-02/03/04): one form, one POST ----
Route::get('/masuk', [BuyerAuthController::class, 'showLogin'])->name('masuk');
Route::post('/masuk', [BuyerAuthController::class, 'login']);
Route::post('/keluar', [BuyerAuthController::class, 'logout'])
    ->middleware('auth:web')->name('keluar');
Route::post('/developer/keluar', [DeveloperAuthController::class, 'logout'])
    ->middleware('auth:developer')->name('developer.keluar');

// ---- Legacy separate-login URLs: redirects, not forms ----
// Old bookmarks / Figma screens pointing at the separate forms land on
// the unified form with the actor pre-selected. There is deliberately
// NO POST /admin/masuk or POST /developer/masuk any more.
Route::get('/admin/masuk', function () {
    return redirect()->route('masuk', ['aktor' => 'admin_toko']);
})->name('admin.masuk');
Route::get('/developer/masuk', function () {
    return redirect()->route('masuk', ['aktor' => 'developer']);
})->name('developer.masuk');

// ---- Buyer surface (M1: only the katalog placeholder, M2 replaces it) ----
Route::middleware(['auth:web', 'active', 'role:pembeli', 'no-cache'])->group(function () {
    Route::get('/katalog', [PageController::class, 'katalog'])->name('katalog');
});

// ---- Admin surface (ACC-11/12/13; dashboard is an M5 placeholder) ----
Route::middleware(['auth:web', 'active', 'role:admin_toko', 'no-cache'])->group(function () {
    Route::get('/admin', [PageController::class, 'adminDashboard'])->name('admin.dashboard');
    Route::get('/admin/akun-pembeli', [BuyerAccountController::class, 'index'])->name('admin.akun-pembeli.index');
    Route::get('/admin/akun-pembeli/{account}', [BuyerAccountController::class, 'show'])->name('admin.akun-pembeli.show');
    Route::post('/admin/akun-pembeli/{account}/status', [BuyerAccountController::class, 'updateStatus'])->name('admin.akun-pembeli.status');
});

// ---- Developer surface (ACC-14; full panel is M4) ----
// Keputusan 2026-11-05: developers have role 'dev', so these routes
// declare role:dev like every other protected route (ACC-08, no exception).
Route::middleware(['auth:developer', 'role:dev', 'no-cache'])->group(function () {
    Route::get('/developer', [PageController::class, 'developerDashboard'])->name('developer.dashboard');
    Route::get('/developer/akun-simulasi', [SimulationAccountController::class, 'index'])->name('developer.akun-simulasi.index');
});
