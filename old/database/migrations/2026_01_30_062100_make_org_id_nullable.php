<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jalankan migrasi untuk mengubah kolom menjadi nullable.
     */
    public function up(): void
    {
        Schema::table('positions', function (Blueprint $table) {
            // Ini adalah bagian yang kamu tanyakan
            $table->unsignedBigInteger('organization_id')->nullable()->change();
        });
    }

    /**
     * Kembalikan perubahan jika migrasi di-rollback.
     */
    public function down(): void
    {
        Schema::table('positions', function (Blueprint $table) {
            $table->unsignedBigInteger('organization_id')->nullable(false)->change();
        });
    }
};