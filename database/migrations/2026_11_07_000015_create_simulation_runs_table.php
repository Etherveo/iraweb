<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// M4 simulator (SIM-35, blueprint §6.2). One row per simulation run:
// the developer, the action, its parameters (incl. seed), the period,
// the outcome, and the row counts. Raw CREATE TABLE, following the
// M2/M3 pattern: CHECK / FK constraints are not expressible through
// the Blueprint API. The CHECKs claimed here MUST stay real (lesson
// from the users.origin drift: a documented CHECK with no CHECK in
// the database). There are no created_at/updated_at columns — the run
// lifecycle is tracked by started_at/finished_at/reset_at instead.
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("CREATE TABLE simulation_runs (
            id VARCHAR NOT NULL PRIMARY KEY,
            developer_id INTEGER NOT NULL REFERENCES developers (id),
            action VARCHAR NOT NULL,
            params TEXT NOT NULL,
            seed INTEGER NOT NULL,
            period_start DATE NULL,
            period_end DATE NULL,
            status VARCHAR NOT NULL DEFAULT 'running',
            counts TEXT NULL,
            error_message TEXT NULL,
            idempotency_key VARCHAR NOT NULL,
            started_at DATETIME NOT NULL,
            finished_at DATETIME NULL,
            reset_at DATETIME NULL,
            CHECK (action IN ('create_accounts', 'tx_user_first', 'tx_period_first', 'funnel_events')),
            CHECK (status IN ('running', 'berhasil', 'gagal', 'direset'))
        )");
        DB::statement('CREATE UNIQUE INDEX simulation_runs_developer_id_idempotency_key_unique ON simulation_runs (developer_id, idempotency_key)');
        DB::statement('CREATE INDEX simulation_runs_developer_id_started_at_index ON simulation_runs (developer_id, started_at)');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS simulation_runs');
    }
};
