<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// M3a ORD-01..14, blueprint §6.2. Raw CREATE TABLE, M2 pattern.
// paid_at is set when the order becomes 'dibayar' (EVT-07: the order
// stores when it was created and when it was paid; the payment row
// holds paid_at too, this is the order-level read, not a copy of a
// computed figure). The origin/simulation_run_id CHECK follows
// blueprint §6.3: a simulation row must carry its run id.
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("CREATE TABLE orders (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            order_number VARCHAR NOT NULL,
            user_id INTEGER NOT NULL REFERENCES users (id) ON DELETE RESTRICT,
            status VARCHAR NOT NULL,
            total_idr INTEGER NOT NULL,
            payment_deadline_at DATETIME NOT NULL,
            paid_at DATETIME NULL,
            checkout_key VARCHAR NOT NULL,
            origin VARCHAR NOT NULL DEFAULT 'normal',
            simulation_run_id VARCHAR NULL,
            created_at DATETIME NULL,
            updated_at DATETIME NULL,
            CHECK (status IN ('menunggu_pembayaran', 'dibayar', 'diproses', 'selesai', 'dibatalkan', 'kedaluwarsa')),
            CHECK (total_idr >= 1),
            CHECK (origin IN ('normal', 'developer_simulation')),
            CHECK (origin = 'normal' OR simulation_run_id IS NOT NULL)
        )");
        DB::statement('CREATE UNIQUE INDEX orders_order_number_unique ON orders (order_number)');
        DB::statement('CREATE UNIQUE INDEX orders_user_checkout_key_unique ON orders (user_id, checkout_key)');
        DB::statement('CREATE INDEX orders_status_deadline_index ON orders (status, payment_deadline_at)');
        DB::statement('CREATE INDEX orders_user_created_index ON orders (user_id, created_at)');
        DB::statement('CREATE INDEX orders_origin_created_index ON orders (origin, created_at)');

        DB::statement("CREATE TABLE order_items (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            order_id INTEGER NOT NULL REFERENCES orders (id) ON DELETE RESTRICT,
            product_id INTEGER NOT NULL REFERENCES products (id) ON DELETE RESTRICT,
            product_name_snapshot VARCHAR NOT NULL,
            unit_price_idr INTEGER NOT NULL,
            quantity INTEGER NOT NULL,
            subtotal_idr INTEGER NOT NULL,
            origin VARCHAR NOT NULL DEFAULT 'normal',
            simulation_run_id VARCHAR NULL,
            created_at DATETIME NULL,
            updated_at DATETIME NULL,
            CHECK (quantity BETWEEN 1 AND 99),
            CHECK (unit_price_idr BETWEEN 1 AND 100000000),
            CHECK (subtotal_idr = unit_price_idr * quantity),
            CHECK (origin IN ('normal', 'developer_simulation')),
            CHECK (origin = 'normal' OR simulation_run_id IS NOT NULL)
        )");
        DB::statement('CREATE INDEX order_items_order_index ON order_items (order_id)');
        DB::statement('CREATE INDEX order_items_product_index ON order_items (product_id)');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS order_items');
        DB::statement('DROP TABLE IF EXISTS orders');
    }
};
