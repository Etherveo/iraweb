<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// M3b PAY-37/PAY-44, blueprint §8.7. The HTTP trace table: one row
// per real payment HTTP event (create_payment, callback,
// expired_callback). Append-only, never updated. The PANEL that
// displays these rows is M3c; M3b only writes them. payment_id and
// order_id are nullable because a rejected attempt (bad JSON, bad
// signature) may arrive before any payment is known.
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("CREATE TABLE payment_http_traces (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            payment_id VARCHAR NULL REFERENCES payments (id) ON DELETE RESTRICT,
            order_id INTEGER NULL REFERENCES orders (id) ON DELETE RESTRICT,
            request_id VARCHAR NOT NULL,
            kind VARCHAR NOT NULL,
            method VARCHAR NOT NULL,
            path VARCHAR NOT NULL,
            request_headers_json TEXT NULL,
            request_body_json TEXT NULL,
            response_status INTEGER NOT NULL,
            response_body_json TEXT NULL,
            occurred_at DATETIME NOT NULL,
            origin VARCHAR NOT NULL DEFAULT 'normal',
            simulation_run_id VARCHAR NULL,
            created_at DATETIME NULL,
            updated_at DATETIME NULL,
            CHECK (kind IN ('create_payment', 'callback', 'expired_callback')),
            CHECK (origin IN ('normal', 'developer_simulation')),
            CHECK (origin = 'normal' OR simulation_run_id IS NOT NULL)
        )");
        DB::statement('CREATE UNIQUE INDEX payment_http_traces_request_id_unique ON payment_http_traces (request_id)');
        DB::statement('CREATE INDEX payment_http_traces_payment_occurred_index ON payment_http_traces (payment_id, occurred_at)');
        DB::statement('CREATE INDEX payment_http_traces_order_occurred_index ON payment_http_traces (order_id, occurred_at)');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS payment_http_traces');
    }
};
