<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// M1 (ACC-01..15, blueprint §6.2). New migration on top of the stock
// 0001_01_01_000000 migration, so an already-migrated laptop DB is updated
// by `php artisan migrate` without needing migrate:fresh.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('pembeli');
            $table->boolean('is_active')->default(true);
            $table->timestamp('deactivated_at')->nullable();
            $table->string('deactivation_reason', 255)->nullable();
            $table->string('origin')->default('normal');
            $table->string('simulation_run_id')->nullable();
            $table->index(['role', 'origin']);
        });

        // SQLite UNIQUE is case-sensitive by default; ACC-01 requires
        // case-insensitive email uniqueness, so add an expression index.
        // Emails are also lowercased on save (User model mutator).
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS users_email_lower_unique ON users (lower(email))');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS users_email_lower_unique');

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role', 'origin']);
            $table->dropColumn(['role', 'is_active', 'deactivated_at', 'deactivation_reason', 'origin', 'simulation_run_id']);
        });
    }
};
