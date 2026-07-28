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
    Schema::create('trainings', function (Blueprint $table) {
        $table->id(); 
        $table->string('title', 255);
        $table->string('held_by', 255);
        
        // Pastikan nama kolom ini SAMA PERSIS dengan yang ada di fungsi save()
        $table->enum('is_certified', ['Yes', 'No'])->default('No');
        $table->string('activity_name', 100)->nullable(); 
        $table->string('skill_name', 100)->nullable();

        $table->bigInteger('trainer_employee_id')->nullable();
        $table->string('trainer_external_name', 255)->nullable();
        $table->date('training_date');
        $table->time('start_time');
        $table->time('finish_time');
        $table->decimal('fee', 15, 2)->default(0);
        $table->timestamps();
    });
}
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trainings');
    }
};
