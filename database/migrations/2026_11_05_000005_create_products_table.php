<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// M2 PRD-01..06, STK-01, blueprint §6.2.
// FK category_id is RESTRICT (a category with products cannot be deleted,
// CAT-03, enforced at the DB level as well as in the controller).
// Price/stock bounds are CHECK constraints, not only validation rules,
// so a direct DB write cannot create a negative or out-of-range product.
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("CREATE TABLE products (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            category_id INTEGER NOT NULL REFERENCES categories (id) ON DELETE RESTRICT,
            name VARCHAR NOT NULL,
            description TEXT NULL,
            price_idr INTEGER NOT NULL,
            stock_on_hand INTEGER NOT NULL DEFAULT 0,
            stock_reserved INTEGER NOT NULL DEFAULT 0,
            is_active INTEGER NOT NULL DEFAULT 1,
            created_at DATETIME NULL,
            updated_at DATETIME NULL,
            CHECK (price_idr BETWEEN 1 AND 100000000),
            CHECK (stock_on_hand >= 0),
            CHECK (stock_reserved >= 0),
            CHECK (stock_reserved <= stock_on_hand)
        )");
        DB::statement('CREATE UNIQUE INDEX products_name_lower_unique ON products (lower(name))');
        DB::statement('CREATE INDEX products_category_active_index ON products (category_id, is_active)');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS products');
    }
};
