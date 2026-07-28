<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tambahkan ke tabel trainings
        Schema::table('trainings', function (Blueprint $table) {
            if (!Schema::hasColumn('trainings', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        // Tambahkan ke tabel users (jika belum ada)
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'deleted_at')) {
                $table->softDeletes();
            }
        });
        
        // Anda bisa tambah tabel lain di sini jika perlu
    }

    public function down(): void
    {
        Schema::table('trainings', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};