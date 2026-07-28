<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::table('trainings', function (Blueprint $table) {

            if (Schema::hasColumn('trainings', 'trainer_internal_name')) {
                $table->dropColumn('trainer_internal_name');
            }


            if (!Schema::hasColumn('trainings', 'trainer_employee_id')) {
                $table->unsignedBigInteger('trainer_employee_id')->nullable()->after('held_by');
            }

            if (!Schema::hasColumn('trainings', 'trainer_external_name')) {
                $table->string('trainer_external_name')->nullable()->after('trainer_employee_id');
            }
        });
    }


    public function down(): void
    {
        Schema::table('trainings', function (Blueprint $table) {

            if (!Schema::hasColumn('trainings', 'trainer_internal_name')) {
                $table->string('trainer_internal_name')->nullable()->after('held_by');
            }
        });
    }
};
