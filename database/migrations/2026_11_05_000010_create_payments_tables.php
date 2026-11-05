<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// M3a PAY-03/PAY-28/PAY-63, blueprint §6.2. payments.id is a ULID
// (26 chars). qr_url stays NULL in M3a (instruction/simulator page is
// M3b). The two partial unique indexes enforce at most one pending and
// at most one paid payment per order (PAY-63). provider_payment_id is
// reserved for a future real provider (blueprint §6.2).
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("CREATE TABLE payments (
            id VARCHAR NOT NULL PRIMARY KEY,
            order_id INTEGER NOT NULL REFERENCES orders (id) ON DELETE RESTRICT,
            user_id INTEGER NOT NULL REFERENCES users (id) ON DELETE RESTRICT,
            provider VARCHAR NOT NULL DEFAULT 'simulator',
            provider_payment_id VARCHAR NULL,
            payment_method VARCHAR NOT NULL,
            amount_idr INTEGER NOT NULL,
            currency VARCHAR NOT NULL DEFAULT 'IDR',
            status VARCHAR NOT NULL,
            reference_code VARCHAR NOT NULL,
            payment_token VARCHAR NOT NULL,
            qr_url VARCHAR NULL,
            expires_at DATETIME NOT NULL,
            paid_at DATETIME NULL,
            is_simulated INTEGER NOT NULL DEFAULT 1,
            origin VARCHAR NOT NULL DEFAULT 'normal',
            simulation_run_id VARCHAR NULL,
            created_at DATETIME NULL,
            updated_at DATETIME NULL,
            CHECK (provider = 'simulator'),
            CHECK (payment_method IN ('qris', 'dana', 'gopay', 'va_bri', 'va_bca', 'va_bni')),
            CHECK (status IN ('pending', 'paid', 'failed', 'expired', 'cancelled')),
            CHECK (amount_idr >= 1),
            CHECK (currency = 'IDR'),
            CHECK (is_simulated = 1),
            CHECK (origin IN ('normal', 'developer_simulation')),
            CHECK (origin = 'normal' OR simulation_run_id IS NOT NULL)
        )");
        DB::statement('CREATE UNIQUE INDEX payments_reference_code_unique ON payments (reference_code)');
        DB::statement('CREATE UNIQUE INDEX payments_payment_token_unique ON payments (payment_token)');
        DB::statement("CREATE UNIQUE INDEX payments_order_pending_unique ON payments (order_id) WHERE status = 'pending'");
        DB::statement("CREATE UNIQUE INDEX payments_order_paid_unique ON payments (order_id) WHERE status = 'paid'");
        DB::statement('CREATE INDEX payments_status_paid_index ON payments (status, paid_at)');
        DB::statement('CREATE INDEX payments_user_created_index ON payments (user_id, created_at)');

        DB::statement("CREATE TABLE payment_status_transitions (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            payment_id VARCHAR NOT NULL REFERENCES payments (id) ON DELETE RESTRICT,
            from_status VARCHAR NULL,
            to_status VARCHAR NOT NULL,
            trigger VARCHAR NOT NULL,
            request_id VARCHAR NULL,
            occurred_at DATETIME NOT NULL,
            origin VARCHAR NOT NULL DEFAULT 'normal',
            simulation_run_id VARCHAR NULL,
            created_at DATETIME NULL,
            CHECK (to_status IN ('pending', 'paid', 'failed', 'expired', 'cancelled')),
            CHECK (origin IN ('normal', 'developer_simulation')),
            CHECK (origin = 'normal' OR simulation_run_id IS NOT NULL)
        )");
        DB::statement('CREATE INDEX payment_status_transitions_payment_occurred_index ON payment_status_transitions (payment_id, occurred_at)');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS payment_status_transitions');
        DB::statement('DROP TABLE IF EXISTS payments');
    }
};
