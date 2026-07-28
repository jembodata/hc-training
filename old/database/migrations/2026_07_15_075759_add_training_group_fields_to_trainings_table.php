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
            $table->foreignId('training_group_id')
                ->nullable()
                ->constrained('training_groups')
                ->restrictOnDelete();

            $table->unsignedSmallInteger('batch_number')
                ->nullable();

            $table->string('batch_name', 100)
                ->nullable();

            $table->unique(
                ['training_group_id', 'batch_number'],
                'trainings_group_batch_unique'
            );

            $table->index(
                ['training_group_id', 'training_date'],
                'trainings_group_date_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trainings', function (Blueprint $table) {
            //
            $table->dropUnique('trainings_group_batch_unique');
            $table->dropIndex('trainings_group_date_index');
            $table->dropForeign(['training_group_id']);

            $table->dropColumn([
                'training_group_id',
                'batch_number',
                'batch_name',
            ]);
        });
    }
};
