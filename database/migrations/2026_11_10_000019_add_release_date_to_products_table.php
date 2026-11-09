<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Release date produk (keputusan Ren 2026-11-10): tanggal produk mulai
// dijual, dipakai sebagai active_since di analitik tren (FR-02).
// Nullable: kosong berarti modul memakai minggu pertama yang punya
// aktivitas sebagai awal deret (fallback blueprint §11).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->date('release_date')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('release_date');
        });
    }
};
