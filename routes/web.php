<?php

use App\Http\Controllers\Admin\AnalyticsController;
use App\Http\Controllers\Admin\BuyerAccountController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\PaymentController as AdminPaymentController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Auth\BuyerAuthController;
use App\Http\Controllers\Auth\DeveloperAuthController;
use App\Http\Controllers\Developer\AccountCreationController;
use App\Http\Controllers\Developer\DashboardController;
use App\Http\Controllers\Developer\FunnelController;
use App\Http\Controllers\Developer\ResetController;
use App\Http\Controllers\Developer\RunController;
use App\Http\Controllers\Developer\SimulationAccountController;
use App\Http\Controllers\Developer\TransactionController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\Payment\SimulationPageController;
use App\Http\Controllers\Shop\CartController;
use App\Http\Controllers\Shop\CatalogController;
use App\Http\Controllers\Shop\CheckoutController;
use App\Http\Controllers\Shop\HomeController;
use App\Http\Controllers\Shop\OrderController as ShopOrderController;
use App\Http\Controllers\Shop\PaymentController as ShopPaymentController;
use App\Http\Controllers\Shop\PaymentInstructionController;
use App\Http\Controllers\Shop\ProfileController;
use Illuminate\Support\Facades\Route;

// M0+M1 route map (blueprint §12, PRD §12).
// Keputusan 2026-11-05: login disatukan — ONE login form at /masuk for
// all three actors (aktor switch), guards/tables stay separate.
// Every protected route declares its guard + role (ACC-08); the only
// auth-only exceptions are the two logout routes, where any authenticated
// user of that guard may log out (that is the point of logout).
// M3a adds the buyer cart/checkout/order routes and the admin order
// routes below. M3b adds the payment instruction page, the public
// simulator page (token capability, no login), and the webhook /
// internal gateway API routes (routes/api.php). No analytics routes
// yet: those are M4-M7.

Route::get('/', [HomeController::class, 'index'])->name('home');

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

// ---- Public catalog (UI polish Tahap 2): guests may browse; buying
// stays behind the buyer group below (POST /keranjang/tambah is auth:web). ----
Route::get('/katalog', [CatalogController::class, 'index'])->name('katalog');
Route::get('/katalog/cari', [CatalogController::class, 'index'])->name('katalog.cari');
Route::get('/produk/{produk}', [CatalogController::class, 'show'])->name('produk.show');

// ---- Buyer surface (M2: real catalog, SHP-01..04; M3a: cart,
// checkout, orders — CRT/STK/ORD/EVT) ----
Route::middleware(['auth:web', 'active', 'role:pembeli', 'no-cache'])->group(function () {

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

    // M3b PAY-11/PAY-13/PAY-31: payment instruction page, status
    // polling (owner only), and repay after a failed payment.
    Route::get('/pesanan/{order}/bayar', [PaymentInstructionController::class, 'show'])->name('pesanan.bayar');
    Route::post('/pesanan/{order}/bayar-ulang', [PaymentInstructionController::class, 'repay'])->name('pesanan.bayar-ulang');
    Route::get('/pembayaran/{payment}/status', [PaymentInstructionController::class, 'status'])->name('pembayaran.status');

    // M3c PAY-49/50: buyer payment history + detail (owner only).
    Route::get('/pembayaran', [ShopPaymentController::class, 'index'])->name('pembayaran.index');
    Route::get('/pembayaran/{payment}', [ShopPaymentController::class, 'show'])->name('pembayaran.show');
    // M3c PAY-45..47: one result page, variant by payment status.
    Route::get('/pembayaran/{payment}/hasil', [ShopPaymentController::class, 'result'])->name('pembayaran.hasil');
    // M3c PAY-48: printable receipt, paid payments only.
    Route::get('/pembayaran/{payment}/struk', [ShopPaymentController::class, 'receipt'])->name('pembayaran.struk');

    // Buyer profile & single-address CRUD (decision Ren 2026-11-06).
    Route::get('/profil', [ProfileController::class, 'show'])->name('profil.show');
    Route::get('/profil/ubah', [ProfileController::class, 'edit'])->name('profil.edit');
    Route::put('/profil', [ProfileController::class, 'update'])->name('profil.update');
    Route::put('/profil/alamat', [ProfileController::class, 'updateAddress'])->name('profil.alamat.update');
});

// ---- Simulator page (M3b PAY-15..23): public, no login — the
// payment token is a limited capability (one payment, expires with
// it). Rate limited; the page itself never shows buyer personal data.
Route::middleware('throttle:60,1')->group(function () {
    Route::get('/pay/sim/{token}', [SimulationPageController::class, 'show'])->name('pay.sim.show');
    Route::post('/pay/sim/{token}/bayar', [SimulationPageController::class, 'pay'])->name('pay.sim.bayar');
    Route::post('/pay/sim/{token}/gagal', [SimulationPageController::class, 'fail'])->name('pay.sim.gagal');
});

// ---- Admin surface (ACC-11/12/13, M2 CAT/PRD; dashboard adalah M5 analitik) ----
Route::middleware(['auth:web', 'active', 'role:admin_toko', 'no-cache'])->group(function () {
    // M5 FR-01..03: halaman analitik admin (Dashboard/Tren/Funnel).
    // M6 FR-04/FR-05: Perbandingan, Aktivitas & Minat, Riwayat Aktivitas.
    // M7 FR-06: Kategori & Karakteristik (ANL-61..65). BUKAN /admin/kategori
    // — itu CRUD kategori produk (TOKO).
    // PageController::adminDashboard tidak dihapus (method + view
    // placeholder dibiarkan apa adanya), tetapi rute /admin kini memakai
    // AnalyticsController.
    Route::get('/admin', [AnalyticsController::class, 'dashboard'])->name('admin.dashboard');
    Route::get('/admin/tren', [AnalyticsController::class, 'trend'])->name('admin.tren');
    Route::get('/admin/funnel', [AnalyticsController::class, 'funnel'])->name('admin.funnel');
    Route::get('/admin/perbandingan', [AnalyticsController::class, 'compare'])->name('admin.perbandingan');
    Route::get('/admin/aktivitas', [AnalyticsController::class, 'interest'])->name('admin.aktivitas');
    Route::get('/admin/aktivitas/riwayat', [AnalyticsController::class, 'history'])->name('admin.aktivitas.riwayat');
    Route::get('/admin/kategori-analisis', [AnalyticsController::class, 'category'])->name('admin.kategori-analisis');
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
    // M3c PAY-51/52: admin payment list/detail, read-only (PAY-53:
    // no route or control changes a payment).
    Route::get('/admin/pembayaran', [AdminPaymentController::class, 'index'])->name('admin.pembayaran.index');
    Route::get('/admin/pembayaran/{payment}', [AdminPaymentController::class, 'show'])->name('admin.pembayaran.show');
});

// ---- Developer surface (ACC-14; full panel is M4) ----
// Keputusan 2026-11-05: developers have role 'dev', so these routes
// declare role:dev like every other protected route (ACC-08, no exception).
// M4 SIM-01: Beranda + Buat Akun + Transaksi per User/Periode + Funnel +
// Akun Simulasi + Jejak & Audit + Reset. Transaction/funnel actions are
// preview-mandatory (SIM-25): pratinjau first, eksekusi after.
Route::middleware(['auth:developer', 'role:dev', 'no-cache'])->group(function () {
    Route::get('/developer', [DashboardController::class, 'index'])->name('developer.dashboard');
    Route::get('/developer/akun-simulasi', [SimulationAccountController::class, 'index'])->name('developer.akun-simulasi.index');

    // SIM-02..08: bulk simulation-account creation (no preview — SIM-25
    // mandates previews only for actions 2, 3 and 4).
    Route::get('/developer/akun/buat', [AccountCreationController::class, 'create'])->name('developer.akun.create');
    Route::post('/developer/akun', [AccountCreationController::class, 'store'])->name('developer.akun.store');

    // SIM-09..15: aksi 2, user-first.
    Route::get('/developer/transaksi/user', [TransactionController::class, 'userForm'])->name('developer.tx-user.create');
    Route::post('/developer/transaksi/user/pratinjau', [TransactionController::class, 'userPreview'])->name('developer.tx-user.preview');
    Route::post('/developer/transaksi/user/eksekusi', [TransactionController::class, 'userExecute'])->name('developer.tx-user.execute');

    // SIM-16..24: aksi 3, period-first.
    Route::get('/developer/transaksi/periode', [TransactionController::class, 'periodForm'])->name('developer.tx-period.create');
    Route::post('/developer/transaksi/periode/pratinjau', [TransactionController::class, 'periodPreview'])->name('developer.tx-period.preview');
    Route::post('/developer/transaksi/periode/eksekusi', [TransactionController::class, 'periodExecute'])->name('developer.tx-period.execute');

    // SIM-31: explicit funnel (no presets — SIM-32/33 are M7).
    Route::get('/developer/funnel', [FunnelController::class, 'create'])->name('developer.funnel.create');
    Route::post('/developer/funnel/pratinjau', [FunnelController::class, 'preview'])->name('developer.funnel.preview');
    Route::post('/developer/funnel/eksekusi', [FunnelController::class, 'execute'])->name('developer.funnel.execute');

    // SIM-35/37: run trace.
    Route::get('/developer/run', [RunController::class, 'index'])->name('developer.run.index');
    Route::get('/developer/run/{run}', [RunController::class, 'show'])->name('developer.run.show');

    // SIM-38/39: reset per run + reset-all (type-the-phrase).
    Route::get('/developer/reset', [ResetController::class, 'index'])->name('developer.reset.index');
    Route::post('/developer/reset/run/{run}', [ResetController::class, 'resetRun'])->name('developer.reset.run');
    Route::post('/developer/reset/semua', [ResetController::class, 'resetAll'])->name('developer.reset.all');
});
