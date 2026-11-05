<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// M2 CAT-01..04, blueprint §6.2.
// Raw CREATE TABLE is used deliberately: SQLite CHECK / expression-unique
// constraints are not expressible through the Blueprint API, and the
// unique lower(name) rule (CAT-01) needs an expression index, following
// the M1 users_email_lower_unique precedent.
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("CREATE TABLE categories (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            name VARCHAR NOT NULL,
            slug VARCHAR NOT NULL,
            created_at DATETIME NULL,
            updated_at DATETIME NULL
        )");
        DB::statement('CREATE UNIQUE INDEX categories_slug_unique ON categories (slug)');
        DB::statement('CREATE UNIQUE INDEX categories_name_lower_unique ON categories (lower(name))');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS categories');
    }
};
