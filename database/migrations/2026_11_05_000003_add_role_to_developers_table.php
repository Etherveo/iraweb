<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Keputusan 2026-11-05: login disatukan — developers gain role 'dev' so
// developer routes can declare role:dev (ACC-08) without an exception.
// ADDITIVE migration only: it must run cleanly on a laptop DB that already
// applied 2026_11_05_000002 (developers without role). Existing rows get
// the default 'dev'. SQLite ADD COLUMN with a CHECK is used directly
// because the Schema builder cannot add a CHECK to an existing column.
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('developers', 'role')) {
            return;
        }

        DB::statement("ALTER TABLE developers ADD COLUMN role VARCHAR NOT NULL DEFAULT 'dev' CHECK (role = 'dev')");
        DB::statement("UPDATE developers SET role = 'dev' WHERE role IS NULL OR role = ''");
    }

    public function down(): void
    {
        if (Schema::hasColumn('developers', 'role')) {
            Schema::table('developers', function ($table) {
                $table->dropColumn('role');
            });
        }
    }
};
