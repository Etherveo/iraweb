<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// M2 PRD-07, STK-05, blueprint §6.2.
// Append-only: there is deliberately no updated_at column and no
// update/delete code path for this table in M2. The type CHECK already
// covers the M3 types (cadangkan/konfirmasi/lepas) so M3 does not need
// a schema change to write them; M2 only ever writes 'penyesuaian'.
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("CREATE TABLE stock_movements (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            product_id INTEGER NOT NULL REFERENCES products (id) ON DELETE RESTRICT,
            type VARCHAR NOT NULL,
            delta_on_hand INTEGER NOT NULL DEFAULT 0,
            delta_reserved INTEGER NOT NULL DEFAULT 0,
            order_id INTEGER NULL,
            actor_type VARCHAR NOT NULL,
            actor_id INTEGER NULL,
            note TEXT NULL,
            origin VARCHAR NOT NULL DEFAULT 'normal',
            simulation_run_id VARCHAR NULL,
            created_at DATETIME NULL,
            CHECK (type IN ('penyesuaian', 'cadangkan', 'konfirmasi', 'lepas'))
        )");
        DB::statement('CREATE INDEX stock_movements_product_created_index ON stock_movements (product_id, created_at)');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS stock_movements');
    }
};
