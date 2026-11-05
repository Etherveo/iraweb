<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// M3a EVT-01..09, blueprint §6.2. Append-only: the app never updates
// or deletes events. The partial unique index guarantees one
// booking/purchase/cancel event per order item (EVT-04/05/06
// idempotency); view/add_to_cart are intentionally NOT unique (a
// buyer may view/add repeatedly, EVT-02 dedup is enforced in
// ActivityRecorder, not by the schema).
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("CREATE TABLE events (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            event_type VARCHAR NOT NULL,
            user_id INTEGER NOT NULL REFERENCES users (id) ON DELETE RESTRICT,
            product_id INTEGER NOT NULL REFERENCES products (id) ON DELETE RESTRICT,
            session_ref VARCHAR NULL,
            order_id INTEGER NULL REFERENCES orders (id) ON DELETE RESTRICT,
            order_item_id INTEGER NULL REFERENCES order_items (id) ON DELETE RESTRICT,
            reason VARCHAR NULL,
            occurred_at DATETIME NOT NULL,
            origin VARCHAR NOT NULL DEFAULT 'normal',
            simulation_run_id VARCHAR NULL,
            created_at DATETIME NULL,
            updated_at DATETIME NULL,
            CHECK (event_type IN ('view', 'add_to_cart', 'booking', 'purchase', 'cancel')),
            CHECK (origin IN ('normal', 'developer_simulation')),
            CHECK (origin = 'normal' OR simulation_run_id IS NOT NULL)
        )");
        DB::statement('CREATE INDEX events_type_occurred_index ON events (event_type, occurred_at)');
        DB::statement('CREATE INDEX events_product_occurred_index ON events (product_id, occurred_at)');
        DB::statement('CREATE INDEX events_user_product_occurred_index ON events (user_id, product_id, occurred_at)');
        DB::statement("CREATE UNIQUE INDEX events_order_item_type_unique ON events (order_item_id, event_type) WHERE event_type IN ('booking', 'purchase', 'cancel') AND order_item_id IS NOT NULL");
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS events');
    }
};
