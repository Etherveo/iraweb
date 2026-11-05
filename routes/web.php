<?php

use App\Http\Controllers\Admin\BuyerAccountController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Auth\BuyerAuthController;
use App\Http\Controllers\Auth\DeveloperAuthController;
use App\Http\Controllers\Developer\SimulationAccountController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\Shop\CartController;
use App\Http\Controllers\Shop\CatalogController;
use App\Http\Controllers\Shop\CheckoutController;
use App\Http\Controllers\Shop\OrderController as ShopOrderController;
use Illuminate\Support\Facades\Route;

// M0+M1 route map (blueprint §12, PRD §12).
// Keputusan 2026-11-05: login disatukan — ONE login form at /masuk for
// all three actors (aktor switch), guards/tables stay separate.
// Every protected route declares its guard + role (ACC-08); the only
// auth-only exceptions are the two logout routes, where any authenticated
// user of that guard may log out (that is the point of logout).
// M3a adds the buyer cart/checkout/order routes and the admin order
// routes below. No payment simulator, instruction page, webhook, or
// analytics routes yet: those are M3b/M4-M7.

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

// ---- Buyer surface (M2: real catalog, SHP-01..04; M3a: cart,
// checkout, orders — CRT/STK/ORD/EVT) ----
Route::middleware(['auth:web', 'active', 'role:pembeli', 'no-cache'])->group(function () {
    Route::get('/katalog', [CatalogController::class, 'index'])->name('katalog');
    Route::get('/katalog/cari', [CatalogController::class, 'index'])->name('katalog.cari');
    Route::get('/produk/{produk}', [CatalogController::class, 'show'])->name('produk.show');

    // M3a CRT-01..06: server-side cart, one per account.
    Route::get('/keranjang', [CartController::class, 'index'])->name('keranjang.index');
    Route::post('/keranjang/tambah', [CartController::class, 'store'])->name('keranjang.tambah');
    Route::put('/keranjang/{item}', [CartController::class, 'update'])->name('keranjang.update');
    Route::delete('/keranjang/{item}', [CartController::class, 'destroy'])->name('keranjang.destroy');

    // M3a ORD-01/02: checkout summary + atomic order creation.
    // Success redirects to the order detail page: the payment
    // instruction page is M3b (PAY-11), not M3a.
    Route::get('/checkout', [CheckoutController::class, 'show'])->name('checkout.show');
    Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');

    // M3a ORD-07..09: buyer order history, detail, cancel.
    Route::get('/pesanan', [ShopOrderController::class, 'index'])->name('pesanan.index');
    Route::get('/pesanan/{order}', [ShopOrderController::class, 'show'])->name('pesanan.show');
    Route::post('/pesanan/{order}/batal', [ShopOrderController::class, 'cancel'])->name('pesanan.batal');
});

// ---- Admin surface (ACC-11/12/13, M2 CAT/PRD; dashboard is an M5 placeholder) ----
Route::middleware(['auth:web', 'active', 'role:admin_toko', 'no-cache'])->group(function () {
    Route::get('/admin', [PageController::class, 'adminDashboard'])->name('admin.dashboard');
    Route::get('/admin/kategori', [CategoryController::class, 'index'])->name('admin.kategori.index');
    Route::get('/admin/kategori/tambah', [CategoryController::class, 'create'])->name('admin.kategori.create');
    Route::post('/admin/kategori', [CategoryController::class, 'store'])->name('admin.kategori.store');
    Route::get('/admin/kategori/{kategori}/ubah', [CategoryController::class, 'edit'])->name('admin.kategori.edit');
    Route::put('/admin/kategori/{kategori}', [CategoryController::class, 'update'])->name('admin.kategori.update');
    Route::delete('/admin/kategori/{kategori}', [CategoryController::class, 'destroy'])->name('admin.kategori.destroy');
    Route::get('/admin/produk', [ProductController::class, 'index'])->name('admin.produk.index');
    Route::get('/admin/produk/tambah', [ProductController::class, 'create'])->name('admin.produk.create');
    Route::post('/admin/produk', [ProductController::class, 'store'])->name('admin.produk.store');
    Route::get('/admin/produk/{produk}/ubah', [ProductController::class, 'edit'])->name('admin.produk.edit');
    Route::put('/admin/produk/{produk}', [ProductController::class, 'update'])->name('admin.produk.update');
    Route::post('/admin/produk/{produk}/toggle', [ProductController::class, 'toggle'])->name('admin.produk.toggle');
    Route::get('/admin/produk/{produk}/stok', [ProductController::class, 'stockForm'])->name('admin.produk.stok');
    Route::post('/admin/produk/{produk}/stok', [ProductController::class, 'adjustStock'])->name('admin.produk.stok.update');
    Route::get('/admin/produk/{produk}/riwayat-stok', [ProductController::class, 'history'])->name('admin.produk.riwayat');
    Route::get('/admin/akun-pembeli', [BuyerAccountController::class, 'index'])->name('admin.akun-pembeli.index');
    Route::get('/admin/akun-pembeli/{account}', [BuyerAccountController::class, 'show'])->name('admin.akun-pembeli.show');
    Route::post('/admin/akun-pembeli/{account}/status', [BuyerAccountController::class, 'updateStatus'])->name('admin.akun-pembeli.status');
    // M3a ORD-10..13: admin order list/detail + fulfillment only.
    // No admin cancel action (ORD-13) and no payment writes (PAY-53).
    Route::get('/admin/pesanan', [AdminOrderController::class, 'index'])->name('admin.pesanan.index');
    Route::get('/admin/pesanan/{order}', [AdminOrderController::class, 'show'])->name('admin.pesanan.show');
    Route::post('/admin/pesanan/{order}/status', [AdminOrderController::class, 'updateStatus'])->name('admin.pesanan.status');
});

// ---- Developer surface (ACC-14; full panel is M4) ----
// Keputusan 2026-11-05: developers have role 'dev', so these routes
// declare role:dev like every other protected route (ACC-08, no exception).
Route::middleware(['auth:developer', 'role:dev', 'no-cache'])->group(function () {
    Route::get('/developer', [PageController::class, 'developerDashboard'])->name('developer.dashboard');
    Route::get('/developer/akun-simulasi', [SimulationAccountController::class, 'index'])->name('developer.akun-simulasi.index');
});
