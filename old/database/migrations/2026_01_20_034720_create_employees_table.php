<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('nik', 50)->unique();
            $table->string('name', 255);

            // Dibuat nullable agar tidak error jika belum dipilih
            $table->foreignId('org_id')->nullable()->constrained('organizations')->onDelete('cascade');
            $table->foreignId('position_id')->nullable()->constrained('positions')->onDelete('cascade');

            // TAMBAHKAN KOLOM INI:
            $table->string('status_employee', 50)->nullable(); // Untuk Permanent/Contract

            $table->string('status', 50)->default('Active'); // Contoh: Active/Inactive
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
