<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Jadwal pengambilan (keputusan Ren 2026-11-09). Additive migration:
// nullable pickup_at on orders, diisi saat checkout dari tanggal+jam
// pilihan pembeli (WITA) yang dikonversi ke UTC. NULL untuk pesanan
// lama dan pesanan simulasi (simulator tidak punya jadwal).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('pickup_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('pickup_at');
        });
    }
};
