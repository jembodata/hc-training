<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'issued_certificates',
            function (Blueprint $table): void {
                $table->id();
                $table->string('certificate_number', 64)->unique();
                $table->uuid('request_key')->unique();

                $table->foreignId('training_id')
                    ->constrained('trainings')
                    ->restrictOnDelete();

                $table->foreignId('employee_id')
                    ->constrained('employees')
                    ->restrictOnDelete();

                $table->foreignId('certificate_template_id')
                    ->nullable()
                    ->constrained('certificate_templates')
                    ->nullOnDelete();

                $table->foreignId('supersedes_id')
                    ->nullable()
                    ->unique()
                    ->constrained('issued_certificates')
                    ->restrictOnDelete();

                $table->string('status', 20)->index();
                $table->json('template_snapshot');
                $table->json('participant_snapshot');
                $table->json('variables_snapshot');

                $table->date('issued_on');
                $table->date('expires_at')->nullable();

                $table->string('pdf_disk', 64)->nullable();
                $table->string('pdf_path')->nullable();
                $table->char('pdf_checksum', 64)->nullable();
                $table->unsignedBigInteger('pdf_bytes')->nullable();

                $table->foreignId('issued_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->timestamp('processing_started_at')->nullable();
                $table->timestamp('issued_at')->nullable();
                $table->timestamp('revoked_at')->nullable();

                $table->foreignId('revoked_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->text('revocation_reason')->nullable();
                $table->text('failure_message')->nullable();
                $table->timestamps();

                $table->index(
                    ['training_id', 'employee_id', 'status'],
                    'issued_certificates_participant_status_index'
                );
                $table->index('issued_at');
                $table->index('revoked_at');
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('issued_certificates');
    }
};
