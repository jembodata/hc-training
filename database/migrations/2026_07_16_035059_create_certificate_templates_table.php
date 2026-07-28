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
        Schema::create('certificate_templates', function (Blueprint $table) {
            $table->id();

            $table->string('name');

            $table->string('kind', 50)
                ->default('completion')
                ->index();

            $table->string('design', 50)
                ->default('minimal_academic');

            $table->string('custom_background_path')
                ->nullable();

            $table->string('font_family', 100)
                ->nullable();

            $table->string('title_font_family', 100)
                ->nullable();

            $table->string('header_text')
                ->default('Certificate of Completion');

            $table->text('body_text');

            $table->string('signature_line')
                ->nullable();

            $table->boolean('digital_signature_enabled')
                ->default(false);

            $table->string('signature_provider', 50)
                ->nullable();

            $table->string('signer_label')
                ->nullable();

            $table->string('signer_position')
                ->nullable();

            $table->string('signature_layout', 10)->default('right');

            $table->json('layout_settings')
                ->nullable();

            $table->boolean('is_default')
                ->default(false)
                ->index();

            $table->timestamp('archived_at')
                ->nullable()
                ->index();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index([
                'kind',
                'archived_at',
            ]);

            $table->index([
                'kind',
                'is_default',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('certificate_templates');
    }
};
