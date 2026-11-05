<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// M3a ORD-14, blueprint §6.2. Append-only: deliberately no updated_at
// column and no update/delete code path for this table.
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("CREATE TABLE order_status_histories (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            order_id INTEGER NOT NULL REFERENCES orders (id) ON DELETE RESTRICT,
            from_status VARCHAR NULL,
            to_status VARCHAR NOT NULL,
            trigger VARCHAR NOT NULL,
            actor_type VARCHAR NULL,
            actor_id INTEGER NULL,
            reason VARCHAR NULL,
            occurred_at DATETIME NOT NULL,
            origin VARCHAR NOT NULL DEFAULT 'normal',
            simulation_run_id VARCHAR NULL,
            created_at DATETIME NULL,
            CHECK (to_status IN ('menunggu_pembayaran', 'dibayar', 'diproses', 'selesai', 'dibatalkan', 'kedaluwarsa')),
            CHECK (origin IN ('normal', 'developer_simulation')),
            CHECK (origin = 'normal' OR simulation_run_id IS NOT NULL)
        )");
        DB::statement('CREATE INDEX order_status_histories_order_occurred_index ON order_status_histories (order_id, occurred_at)');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS order_status_histories');
    }
};
