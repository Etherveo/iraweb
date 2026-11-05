<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// M4 slice 1 (buyer profile, Data_Pembeli §1). Additive migration: new
// nullable profile columns on users, complementing the existing fields
// (name, email, password, role, is_active, origin, simulation_run_id) —
// nothing existing is changed. The gender CHECK rides on the SQLite ADD
// COLUMN itself (000003 precedent) because the Schema builder cannot add
// a CHECK to an existing column. A NULL gender passes the CHECK (SQLite
// CHECK fails only on false), so the column stays nullable.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable()->unique();
            $table->string('phone')->nullable();
            $table->date('birthdate')->nullable();
        });

        DB::statement("ALTER TABLE users ADD COLUMN gender VARCHAR NULL CHECK (gender IN ('laki-laki', 'perempuan'))");
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_username_unique');
            $table->dropColumn(['username', 'phone', 'gender', 'birthdate']);
        });
    }
};
