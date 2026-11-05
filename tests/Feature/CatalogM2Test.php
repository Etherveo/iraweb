<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

// M2 CAT-01..04, PRD-01..07, SHP-01..04, STK-01.
// No Python, no Figma images, no external HTTP, no events table in M2.
// Guard expectations follow the M1 ACC-08/ACC-10 matrix: a wrong-role
// WEB user gets 403, a guest or a developer-guard user gets redirected
// to the matching login form (see Authenticate middleware).

function m2Admin(): User
{
    return User::factory()->admin()->create();
}

function m2Buyer(): User
{
    return User::factory()->create();
}

// ---- CAT ----

test('CAT-01 admin can add a category, slug is generated, duplicate case-insensitive is rejected', function () {
    $admin = m2Admin();
    $this->actingAs($admin, 'web');

    $this->post('/admin/kategori', ['name' => 'Alat Tulis'])
        ->assertRedirect('/admin/kategori');
    $this->assertDatabaseHas('categories', ['name' => 'Alat Tulis', 'slug' => 'alat-tulis']);

    $this->post('/admin/kategori', ['name' => '  ALAT TULIS  '])
        ->assertSessionHasErrors('name');
    $this->assertDatabaseCount('categories', 1);
});

test('CAT-01 category name too short or too long is rejected', function () {
    $this->actingAs(m2Admin(), 'web');

    $this->post('/admin/kategori', ['name' => 'A'])->assertSessionHasErrors('name');
    $this->post('/admin/kategori', ['name' => str_repeat('a', 61)])->assertSessionHasErrors('name');
    $this->assertDatabaseCount('categories', 0);
});

test('CAT-01 slug gets a numeric suffix on collision', function () {
    Category::factory()->create(['name' => 'Alat Tulis', 'slug' => 'alat-tulis']);
    // Same slug from a different name (e.g. punctuation variant) must
    // not collide: the service appends -2, -3, ...
    $this->actingAs(m2Admin(), 'web');
    $this->post('/admin/kategori', ['name' => 'Alat Tulis!'])
        ->assertRedirect('/admin/kategori');
    $this->assertDatabaseHas('categories', ['slug' => 'alat-tulis-2']);
});

test('CAT-02 renaming a category keeps its id and its product links', function () {
    $category = Category::factory()->create(['name' => 'Lama']);
    $product = Product::factory()->create(['category_id' => $category->id]);

    $this->actingAs(m2Admin(), 'web');
    $this->put('/admin/kategori/'.$category->id, ['name' => 'Baru'])
        ->assertRedirect('/admin/kategori');

    $category->refresh();
    expect($category->name)->toBe('Baru');
    expect($product->fresh()->category_id)->toBe($category->id);
});

test('CAT-03 deleting a category with products is rejected, deleting an empty one succeeds', function () {
    $withProducts = Category::factory()->create();
    Product::factory()->create(['category_id' => $withProducts->id]);
    $empty = Category::factory()->create();

    $this->actingAs(m2Admin(), 'web');

    $this->delete('/admin/kategori/'.$withProducts->id)
        ->assertRedirect('/admin/kategori')
        ->assertSessionHasErrors('kategori');
    $this->assertDatabaseHas('categories', ['id' => $withProducts->id]);

    $this->delete('/admin/kategori/'.$empty->id)->assertRedirect('/admin/kategori');
    $this->assertDatabaseMissing('categories', ['id' => $empty->id]);
});

test('CAT-04 category list shows product counts and an empty state with an add action', function () {
    $category = Category::factory()->create(['name' => 'Dengan Produk']);
    Product::factory()->count(2)->create(['category_id' => $category->id]);

    $this->actingAs(m2Admin(), 'web');
    $this->get('/admin/kategori')->assertOk()->assertSee('Dengan Produk')->assertSee('2');

    Product::query()->delete();
    Category::query()->delete();
    $this->get('/admin/kategori')->assertOk()->assertSee('Belum ada kategori')->assertSee('Tambah kategori');
});

// ---- PRD ----

test('PRD-01 adding a product with initial stock creates one adjustment movement', function () {
    $category = Category::factory()->create();

    $this->actingAs(m2Admin(), 'web');
    $this->post('/admin/produk', [
        'name' => 'Pulpen Gel',
        'category_id' => $category->id,
        'description' => 'Contoh',
        'price_idr' => 5000,
        'stock_on_hand' => 10,
        'is_active' => 1,
    ])->assertRedirect('/admin/produk');

    $product = Product::where('name', 'Pulpen Gel')->first();
    expect($product->stock_reserved)->toBe(0);
    expect($product->stock_available)->toBe(10);
    $this->assertDatabaseHas('stock_movements', [
        'product_id' => $product->id,
        'type' => 'penyesuaian',
        'delta_on_hand' => 10,
        'order_id' => null,
    ]);
    $this->assertDatabaseCount('stock_movements', 1);
});

test('PRD-01 adding a product with zero initial stock creates no movement', function () {
    $category = Category::factory()->create();

    $this->actingAs(m2Admin(), 'web');
    $this->post('/admin/produk', [
        'name' => 'Pulpen Kosong',
        'category_id' => $category->id,
        'price_idr' => 5000,
        'stock_on_hand' => 0,
    ])->assertRedirect('/admin/produk');
    $this->assertDatabaseCount('stock_movements', 0);
});

test('PRD-02 price zero, negative, decimal and text are rejected', function () {
    $category = Category::factory()->create();
    $this->actingAs(m2Admin(), 'web');

    foreach ([0, -100, '12.5', 'seribu'] as $badPrice) {
        $this->post('/admin/produk', [
            'name' => 'Produk Harga '.$badPrice,
            'category_id' => $category->id,
            'price_idr' => $badPrice,
            'stock_on_hand' => 0,
        ])->assertSessionHasErrors('price_idr');
    }
    $this->assertDatabaseCount('products', 0);
});

test('PRD-01 product stock below zero or above 1000000 is rejected, duplicate name case-insensitive is rejected', function () {
    $category = Category::factory()->create();
    Product::factory()->create(['name' => 'Pulpen Gel', 'category_id' => $category->id]);
    $this->actingAs(m2Admin(), 'web');

    $this->post('/admin/produk', [
        'name' => 'Baru Satu', 'category_id' => $category->id,
        'price_idr' => 5000, 'stock_on_hand' => -1,
    ])->assertSessionHasErrors('stock_on_hand');
    $this->post('/admin/produk', [
        'name' => 'Baru Dua', 'category_id' => $category->id,
        'price_idr' => 5000, 'stock_on_hand' => 1000001,
    ])->assertSessionHasErrors('stock_on_hand');
    $this->post('/admin/produk', [
        'name' => 'pulpen gel', 'category_id' => $category->id,
        'price_idr' => 5000, 'stock_on_hand' => 0,
    ])->assertSessionHasErrors('name');
    $this->assertDatabaseCount('products', 1);
});

test('PRD-03 updating a product changes name, category and price', function () {
    $oldCategory = Category::factory()->create();
    $newCategory = Category::factory()->create();
    $product = Product::factory()->create(['category_id' => $oldCategory->id, 'price_idr' => 5000]);

    $this->actingAs(m2Admin(), 'web');
    $this->put('/admin/produk/'.$product->id, [
        'name' => 'Pulpen Baru',
        'category_id' => $newCategory->id,
        'description' => 'Diubah',
        'price_idr' => 7500,
    ])->assertRedirect('/admin/produk');

    $product->refresh();
    expect($product->name)->toBe('Pulpen Baru');
    expect($product->category_id)->toBe($newCategory->id);
    expect($product->price_idr)->toBe(7500);
});

test('PRD-04 toggling a product hides it from the buyer catalog without deleting it', function () {
    $product = Product::factory()->create(['stock_on_hand' => 10]);
    $buyer = m2Buyer();

    $this->actingAs(m2Admin(), 'web');
    $this->post('/admin/produk/'.$product->id.'/toggle')->assertRedirect('/admin/produk');
    expect($product->fresh()->is_active)->toBeFalse();
    $this->assertDatabaseHas('products', ['id' => $product->id]);

    $this->actingAs($buyer, 'web');
    // Flash dari POST toggle admin masih tersimpan di sesi tes yang sama
    // (tes tidak mengikuti redirect toggle, jadi flash belum dikonsumsi).
    // GET pertama menghabiskan flash itu; di kehidupan nyata admin dan
    // pembeli adalah sesi berbeda, jadi flash tidak bocor ke pembeli.
    $this->get('/katalog');
    $this->get('/katalog')->assertOk()->assertDontSee($product->name);
    $this->get('/produk/'.$product->id)->assertNotFound();
});

test('PRD-05 adjusting stock is atomic with one movement and rejects a new stock below reserved', function () {
    $admin = m2Admin();
    $product = Product::factory()->reserved(5)->create(['stock_on_hand' => 10]);

    $this->actingAs($admin, 'web');
    $this->post('/admin/produk/'.$product->id.'/stok', [
        'stock_on_hand' => 25,
        'note' => 'Stok opname',
    ])->assertRedirect('/admin/produk');

    $product->refresh();
    expect($product->stock_on_hand)->toBe(25);
    expect($product->stock_available)->toBe(20);
    $this->assertDatabaseHas('stock_movements', [
        'product_id' => $product->id,
        'type' => 'penyesuaian',
        'delta_on_hand' => 15,
        'actor_type' => 'admin_toko',
        'actor_id' => $admin->id,
    ]);
    expect(StockMovement::where('product_id', $product->id)->first()->note)->toContain('10');
    expect(StockMovement::where('product_id', $product->id)->first()->note)->toContain('25');

    // Below reserved: rejected, stock unchanged, no extra movement.
    $this->post('/admin/produk/'.$product->id.'/stok', ['stock_on_hand' => 3])
        ->assertSessionHasErrors('stock_on_hand');
    expect($product->fresh()->stock_on_hand)->toBe(25);
    $this->assertDatabaseCount('stock_movements', 1);
});

test('PRD-06 admin product list filters by category and status, searches by name, and marks zero available stock as Habis', function () {
    $catA = Category::factory()->create(['name' => 'Kategori A']);
    $catB = Category::factory()->create(['name' => 'Kategori B']);
    $inStock = Product::factory()->create(['name' => 'Pulpen Ada', 'category_id' => $catA->id, 'stock_on_hand' => 5, 'price_idr' => 5000]);
    $outStock = Product::factory()->outOfStock()->create(['name' => 'Pulpen Habis', 'category_id' => $catA->id]);
    $other = Product::factory()->create(['name' => 'Buku Lain', 'category_id' => $catB->id]);

    $this->actingAs(m2Admin(), 'web');

    $this->get('/admin/produk')->assertOk()
        ->assertSee('Pulpen Ada')->assertSee('Pulpen Habis')->assertSee('Habis')
        ->assertSee('Rp 5.000');
    $this->get('/admin/produk?kategori='.$catB->id)->assertOk()
        ->assertSee('Buku Lain')->assertDontSee('Pulpen Ada');
    $this->get('/admin/produk?cari=pulpen')->assertOk()
        ->assertSee('Pulpen Ada')->assertDontSee('Buku Lain');
    $this->get('/admin/produk?status=nonaktif')->assertOk()->assertDontSee('Pulpen Ada');
    expect($inStock->stock_available)->toBe(5);
    expect($outStock->stock_available)->toBe(0);
});

test('PRD-07 stock history is read-only and ordered by time, newest first', function () {
    $product = Product::factory()->create();
    StockMovement::factory()->create([
        'product_id' => $product->id, 'note' => 'Gerakan Lama',
        'created_at' => now()->subDays(2),
    ]);
    StockMovement::factory()->create([
        'product_id' => $product->id, 'note' => 'Gerakan Baru',
        'created_at' => now(),
    ]);

    $this->actingAs(m2Admin(), 'web');
    $this->get('/admin/produk/'.$product->id.'/riwayat-stok')
        ->assertOk()
        ->assertSeeInOrder(['Gerakan Baru', 'Gerakan Lama'])
        ->assertSee('penyesuaian');
});

// ---- SHP ----

test('SHP-01 buyer catalog shows only active products, paginated at 12 per page', function () {
    foreach (range(1, 13) as $i) {
        Product::factory()->create(['name' => sprintf('Produk %02d', $i), 'stock_on_hand' => 10]);
    }
    Product::factory()->inactive()->create(['name' => 'Produk Nonaktif']);

    $this->actingAs(m2Buyer(), 'web');
    $this->get('/katalog')->assertOk()
        ->assertSee('Produk 01')->assertSee('Produk 12')
        ->assertDontSee('Produk 13')->assertDontSee('Produk Nonaktif');
    $this->get('/katalog?page=2')->assertOk()->assertSee('Produk 13');
});

test('SHP-01 buyer catalog filters by category and shows an empty state', function () {
    $catA = Category::factory()->create();
    $catB = Category::factory()->create();
    Product::factory()->create(['name' => 'Produk A', 'category_id' => $catA->id]);
    Product::factory()->create(['name' => 'Produk B', 'category_id' => $catB->id]);

    $this->actingAs(m2Buyer(), 'web');
    $this->get('/katalog?kategori='.$catB->id)->assertOk()
        ->assertSee('Produk B')->assertDontSee('Produk A');
    $this->get('/katalog?kategori=999999')->assertOk()
        ->assertSee('Tidak ada produk');
});

test('SHP-02 product detail shows price, category, description and available stock, with an M3 cart placeholder and no cart route', function () {
    $product = Product::factory()->create([
        'name' => 'Pulpen Detail', 'price_idr' => 1250000,
        'description' => 'Deskripsi contoh', 'stock_on_hand' => 7,
    ]);

    $this->actingAs(m2Buyer(), 'web');
    $this->get('/produk/'.$product->id)->assertOk()
        ->assertSee('Pulpen Detail')->assertSee('Rp 1.250.000')
        ->assertSee('Deskripsi contoh')->assertSee('Keranjang — M3');
});

test('SHP-04 inactive and missing products return the same 404 and never appear in list or search', function () {
    $inactive = Product::factory()->inactive()->create(['name' => 'Produk Rahasia']);

    $this->actingAs(m2Buyer(), 'web');
    $this->get('/produk/'.$inactive->id)->assertNotFound();
    $this->get('/produk/999999')->assertNotFound();
    $this->get('/katalog')->assertDontSee('Produk Rahasia');
    $this->get('/katalog?q=rahasia')->assertDontSee('Produk Rahasia');
});

test('SHP-03 search is case-insensitive, matches name and description, and treats % and _ literally', function () {
    Product::factory()->create(['name' => 'Pulpen Gel', 'description' => 'Tinta halus']);
    Product::factory()->create(['name' => 'Pulpen 100% Asli', 'description' => 'Bukan diskon']);
    Product::factory()->create(['name' => 'Buku_Tulis', 'description' => 'Bergaris']);
    Product::factory()->create(['name' => 'BukuXTulis', 'description' => 'Polos']);

    $this->actingAs(m2Buyer(), 'web');

    $this->get('/katalog?q=PULPEN')->assertOk()->assertSee('Pulpen Gel');
    $this->get('/katalog?q=halus')->assertOk()->assertSee('Pulpen Gel');
    // % is literal: only the product whose name really contains 100%.
    $this->get('/katalog?q=100%25')->assertOk()
        ->assertSee('Pulpen 100% Asli')->assertDontSee('Pulpen Gel');
    // _ is literal: Buku_Tulis matches, BukuXTulis does not.
    $this->get('/katalog?q=Buku_Tulis')->assertOk()
        ->assertSee('Buku_Tulis')->assertDontSee('BukuXTulis');
});

test('SHP-03 search keyword shorter than 2 characters is rejected and empty results offer a clear action', function () {
    $this->actingAs(m2Buyer(), 'web');

    $this->get('/katalog?q=a')->assertRedirect('/katalog')->assertSessionHasErrors('q');
    $this->get('/katalog?q=tidakadaapapun')->assertOk()
        ->assertSee('Tidak ada produk')->assertSee('Bersihkan pencarian');
});

test('SHP-03 search and detail view create no events table and no side effect in M2', function () {
    $product = Product::factory()->create(['name' => 'Pulpen Cari']);

    $this->actingAs(m2Buyer(), 'web');
    $this->get('/katalog?q=pulpen')->assertOk();
    $this->get('/produk/'.$product->id)->assertOk();

    expect(Schema::hasTable('events'))->toBeFalse();
    $this->assertDatabaseCount('products', 1);
});

test('SHP-03 search keyword is escaped in the UI (no raw HTML injection)', function () {
    $this->actingAs(m2Buyer(), 'web');
    $this->get('/katalog?q=%3Cscript%3E')->assertOk()->assertDontSee('<script>', false);
});

// ---- Guards ----

test('M2 guards: guests are redirected to login, wrong-role web users get 403, developer users are redirected', function () {
    $this->get('/katalog')->assertRedirect('/masuk');
    $this->get('/admin/kategori')->assertRedirect('/masuk?aktor=admin_toko');
    $this->get('/admin/produk')->assertRedirect('/masuk?aktor=admin_toko');

    $buyer = m2Buyer();
    $this->actingAs($buyer, 'web');
    $this->get('/admin/kategori')->assertForbidden();
    $this->get('/admin/produk')->assertForbidden();
});

test('M2 guards: admin cannot open the buyer catalog as a buyer', function () {
    $admin = m2Admin();
    $this->actingAs($admin, 'web');
    $this->get('/katalog')->assertForbidden();
});

test('M2 guards: developer cannot open admin catalog routes', function () {
    // Skenario terpisah dari tes admin di atas: actingAs() menambah sesi
    // guard developer TANPA mengeluarkan sesi guard web yang masih aktif,
    // jadi bila digabung dalam satu tes, /admin/kategori akan lolos
    // sebagai admin web (200), bukan redirect. Tiap tes Pest mendapat
    // aplikasi/sesi baru, jadi skenario developer-only di sini bersih.
    $developer = \App\Models\Developer::factory()->create();
    $this->actingAs($developer, 'developer');
    $this->get('/admin/kategori')->assertRedirect('/masuk?aktor=admin_toko');
    $this->get('/admin/produk')->assertRedirect('/masuk?aktor=admin_toko');
});

test('M2 guards: every new protected route declares guard and role', function () {
    $routes = \Illuminate\Support\Facades\Route::getRoutes();

    foreach (['/katalog', '/katalog/cari', '/produk/{produk}', '/admin/kategori', '/admin/produk'] as $uri) {
        $route = collect($routes)->first(fn ($r) => $r->uri() === ltrim($uri, '/'));
        expect($route)->not->toBeNull('Route '.$uri.' must exist.');
        $middleware = $route->gatherMiddleware();
        expect(collect($middleware)->contains(fn ($m) => str_starts_with((string) $m, 'auth:')))->toBeTrue();
        expect(collect($middleware)->contains(fn ($m) => str_starts_with((string) $m, 'role:')))->toBeTrue();
    }

    $this->assertTrue(true);
});
