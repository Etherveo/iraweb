<?php

use App\Models\Category;
use App\Models\Developer;
use App\Models\Product;
use App\Models\User;

// CRUD katalog di Panel Developer (keputusan Ren 2026-11-10): form yang
// sama dengan admin, ditambah release_date di form produk. release_date
// dipakai sebagai active_since di analitik (FR-02).

function devCatDeveloper(): Developer
{
    return Developer::factory()->create();
}

function devCatAdmin(): User
{
    return User::factory()->admin()->create();
}

function devCatCategory(array $attributes = []): Category
{
    return Category::factory()->create($attributes);
}

function devCatProduct(array $attributes = []): Product
{
    return Product::factory()->create(array_merge(
        ['price_idr' => 25000, 'stock_on_hand' => 50, 'stock_reserved' => 0, 'is_active' => true],
        $attributes,
    ));
}

function devCatProductPayload(array $overrides = []): array
{
    $category = Category::factory()->create();

    return array_merge([
        'name' => 'Pulpen Tes',
        'category_id' => $category->id,
        'description' => null,
        'price_idr' => 5000,
        'stock_on_hand' => 10,
        'release_date' => '2026-09-01',
    ], $overrides);
}

test('developer can create a product with release_date, initial stock is recorded as developer', function () {
    $developer = devCatDeveloper();
    $this->actingAs($developer, 'developer');

    $this->post(route('developer.produk.store'), devCatProductPayload())
        ->assertRedirect(route('developer.produk.index'));

    $product = Product::where('name', 'Pulpen Tes')->firstOrFail();
    expect($product->release_date->toDateString())->toBe('2026-09-01');
    $this->assertDatabaseHas('stock_movements', [
        'product_id' => $product->id,
        'actor_type' => 'developer',
        'actor_id' => $developer->id,
        'delta_on_hand' => 10,
    ]);
});

test('developer product create rejects an invalid release_date', function () {
    $this->actingAs(devCatDeveloper(), 'developer');

    $this->post(route('developer.produk.store'), devCatProductPayload(['release_date' => 'bukan-tanggal']))
        ->assertSessionHasErrors('release_date');
    $this->assertDatabaseCount('products', 0);
});

test('developer can update and clear release_date', function () {
    $product = devCatProduct(['release_date' => '2026-09-01']);
    $this->actingAs(devCatDeveloper(), 'developer');

    $this->put(route('developer.produk.update', $product), [
        'name' => $product->name,
        'category_id' => $product->category_id,
        'price_idr' => $product->price_idr,
        'release_date' => '2026-08-15',
    ])->assertRedirect(route('developer.produk.index'));
    expect($product->fresh()->release_date->toDateString())->toBe('2026-08-15');

    $this->put(route('developer.produk.update', $product), [
        'name' => $product->name,
        'category_id' => $product->category_id,
        'price_idr' => $product->price_idr,
        'release_date' => '',
    ])->assertRedirect(route('developer.produk.index'));
    expect($product->fresh()->release_date)->toBeNull();
});

test('admin product update without release_date keeps the existing release_date', function () {
    $product = devCatProduct(['release_date' => '2026-09-01']);
    $this->actingAs(devCatAdmin(), 'web');

    // Form admin tidak punya input release_date: pembaruan dari admin
    // tidak boleh menghapus tanggal yang diset developer.
    $this->put(route('admin.produk.update', $product), [
        'name' => 'Pulpen Tes Baru',
        'category_id' => $product->category_id,
        'price_idr' => 6000,
    ])->assertRedirect(route('admin.produk.index'));
    expect($product->fresh()->release_date->toDateString())->toBe('2026-09-01');
});

test('developer can manage categories, only an empty category may be deleted', function () {
    $this->actingAs(devCatDeveloper(), 'developer');

    $this->post(route('developer.kategori.store'), ['name' => 'Alat Tulis'])
        ->assertRedirect(route('developer.kategori.index'));
    $category = Category::where('name', 'Alat Tulis')->firstOrFail();
    expect($category->slug)->toBe('alat-tulis');

    $this->put(route('developer.kategori.update', $category), ['name' => 'Alat Kantor'])
        ->assertRedirect(route('developer.kategori.index'));
    expect($category->fresh()->name)->toBe('Alat Kantor');

    $this->delete(route('developer.kategori.destroy', $category))
        ->assertRedirect(route('developer.kategori.index'));
    $this->assertDatabaseCount('categories', 0);

    $withProduct = devCatCategory(['name' => 'Berisi']);
    devCatProduct(['category_id' => $withProduct->id]);
    $this->delete(route('developer.kategori.destroy', $withProduct))
        ->assertSessionHasErrors('kategori');
    $this->assertDatabaseHas('categories', ['name' => 'Berisi']);
});

test('developer produk index shows the release date', function () {
    devCatProduct(['name' => 'Pulpen Rilis', 'release_date' => '2026-09-01']);
    $this->actingAs(devCatDeveloper(), 'developer');

    $this->get(route('developer.produk.index'))
        ->assertOk()
        ->assertSee('Pulpen Rilis')
        ->assertSee('01 Sep 2026');
});

test('developer can toggle a product', function () {
    $product = devCatProduct(['is_active' => true]);
    $this->actingAs(devCatDeveloper(), 'developer');

    $this->post(route('developer.produk.toggle', $product))
        ->assertRedirect(route('developer.produk.index'));
    expect($product->fresh()->is_active)->toBeFalse();
});

test('guest is redirected to the developer login from developer catalog routes', function () {
    $this->get('/developer/produk')->assertRedirect('/masuk?aktor=developer');
    $this->get('/developer/kategori')->assertRedirect('/masuk?aktor=developer');
});
