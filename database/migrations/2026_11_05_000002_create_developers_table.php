<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// M1 ACC-04/ACC-05, blueprint §6.2: developers are a separate table/guard.
// No is_active column per the blueprint table definition.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('developers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS developers_email_lower_unique ON developers (lower(email))');
    }

    public function down(): void
    {
        Schema::dropIfExists('developers');
    }
};
