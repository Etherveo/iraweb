<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// M5 langkah 1 (penanda ambil-&-bayar, keputusan 2026-11-08). Additive
// migration: nullable picked_up_at on orders, set by OrderStateMachine
// when the order transitions to selesai — the auditable moment the buyer
// picked the order up (and paid, for the real store flow). NULL until then.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('picked_up_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('picked_up_at');
        });
    }
};
