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
        Schema::table('trainings', function (Blueprint $table) {
            //
            $table
                ->foreignId('certificate_template_id')
                ->nullable()
                ->after('is_certified')
                ->constrained('certificate_templates')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trainings', function (Blueprint $table) {
            //
            $table->dropConstrainedForeignId(
                'certificate_template_id'
            );
        });
    }
};
