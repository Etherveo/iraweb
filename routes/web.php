<?php

use App\Http\Controllers\Admin\BuyerAccountController;
use App\Http\Controllers\Auth\AdminAuthController;
use App\Http\Controllers\Auth\BuyerAuthController;
use App\Http\Controllers\Auth\DeveloperAuthController;
use App\Http\Controllers\Developer\SimulationAccountController;
use App\Http\Controllers\PageController;
use Illuminate\Support\Facades\Route;

// M0+M1 route map (blueprint §12, PRD §12).
// Every protected route declares its guard + role (ACC-08); the only
// auth-only exceptions are the two logout routes, where any authenticated
// user of that guard may log out (that is the point of logout).
// No catalog, cart, order, payment, simulator, or analytics routes yet:
// those are M2-M7.

Route::get('/', function () {
    return view('welcome');
})->name('home');

// ---- Buyer auth (public forms, ACC-01/02/06) ----
Route::get('/daftar', [BuyerAuthController::class, 'showRegister'])->name('daftar');
Route::post('/daftar', [BuyerAuthController::class, 'register']);
Route::get('/masuk', [BuyerAuthController::class, 'showLogin'])->name('masuk');
Route::post('/masuk', [BuyerAuthController::class, 'login']);
Route::post('/keluar', [BuyerAuthController::class, 'logout'])
    ->middleware('auth:web')->name('keluar');

// ---- Admin auth (public form, ACC-03) ----
Route::get('/admin/masuk', [AdminAuthController::class, 'showLogin'])->name('admin.masuk');
Route::post('/admin/masuk', [AdminAuthController::class, 'login']);

// ---- Developer auth (public form, ACC-04) ----
Route::get('/developer/masuk', [DeveloperAuthController::class, 'showLogin'])->name('developer.masuk');
Route::post('/developer/masuk', [DeveloperAuthController::class, 'login']);
Route::post('/developer/keluar', [DeveloperAuthController::class, 'logout'])
    ->middleware('auth:developer')->name('developer.keluar');

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
Route::middleware(['auth:developer', 'no-cache'])->group(function () {
    Route::get('/developer', [PageController::class, 'developerDashboard'])->name('developer.dashboard');
    Route::get('/developer/akun-simulasi', [SimulationAccountController::class, 'index'])->name('developer.akun-simulasi.index');
});
