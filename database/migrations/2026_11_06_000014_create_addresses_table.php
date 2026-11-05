<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// M4 slice 1 (buyer profile, Data_Pembeli §2). One buyer may have many
// addresses; the table is the MD §2 address block, nothing more (no
// is_primary column — the MD does not define one). Raw CREATE TABLE,
// following the M2/M3 pattern: CHECK / FK constraints are not expressible
// through the Blueprint API. Deleting a user deletes its addresses
// (ON DELETE CASCADE).
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("CREATE TABLE addresses (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            user_id INTEGER NOT NULL REFERENCES users (id) ON DELETE CASCADE,
            recipient_name VARCHAR NOT NULL,
            recipient_phone VARCHAR NULL,
            province VARCHAR NOT NULL,
            city VARCHAR NOT NULL,
            district VARCHAR NOT NULL,
            village VARCHAR NOT NULL,
            postal_code VARCHAR NOT NULL,
            street_address TEXT NOT NULL,
            address_type VARCHAR NOT NULL DEFAULT 'Rumah',
            created_at DATETIME NULL,
            updated_at DATETIME NULL,
            CHECK (address_type IN ('Rumah', 'Kantor', 'Lainnya')),
            CHECK (length(postal_code) = 5)
        )");
        DB::statement('CREATE INDEX addresses_user_id_index ON addresses (user_id)');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS addresses');
    }
};
