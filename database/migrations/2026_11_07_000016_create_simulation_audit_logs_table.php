<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// M4 simulator (SIM-36, blueprint §6.2). Append-only audit log: who did
// what, how many, in which period, with what outcome. run_id refers to
// simulation_runs.id but has NO foreign key on purpose — the audit must
// survive a run reset (SIM-38/39 never touch this table). There are no
// created_at/updated_at columns; occurred_at is the single timestamp.
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE TABLE simulation_audit_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            developer_id INTEGER NOT NULL REFERENCES developers (id),
            action VARCHAR NOT NULL,
            run_id VARCHAR NULL,
            summary TEXT NOT NULL,
            outcome VARCHAR NOT NULL,
            occurred_at DATETIME NOT NULL
        )');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS simulation_audit_logs');
    }
};
