<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// M3a CRT-01..06, blueprint §6.2. Raw CREATE TABLE, following the M2
// pattern: CHECK / partial-unique constraints are not expressible
// through the Blueprint API.
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("CREATE TABLE carts (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            user_id INTEGER NOT NULL REFERENCES users (id) ON DELETE RESTRICT,
            created_at DATETIME NULL,
            updated_at DATETIME NULL
        )");
        DB::statement('CREATE UNIQUE INDEX carts_user_id_unique ON carts (user_id)');

        DB::statement("CREATE TABLE cart_items (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            cart_id INTEGER NOT NULL REFERENCES carts (id) ON DELETE CASCADE,
            product_id INTEGER NOT NULL REFERENCES products (id) ON DELETE RESTRICT,
            quantity INTEGER NOT NULL,
            created_at DATETIME NULL,
            updated_at DATETIME NULL,
            CHECK (quantity BETWEEN 1 AND 99)
        )");
        DB::statement('CREATE UNIQUE INDEX cart_items_cart_product_unique ON cart_items (cart_id, product_id)');
        DB::statement('CREATE INDEX cart_items_product_index ON cart_items (product_id)');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS cart_items');
        DB::statement('DROP TABLE IF EXISTS carts');
    }
};
