<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'prescription_attachments',
            function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();

                $table->foreignId('prescription_id')
                    ->constrained()
                    ->restrictOnDelete();

                $table->foreignId('uploaded_by_user_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->string('attachment_type', 30)
                    ->default('prescription');

                $table->string('disk', 50)
                    ->default('public');

                $table->string('path', 2048);
                $table->string('original_name', 255);
                $table->string('mime_type', 100)->nullable();

                $table->unsignedBigInteger('size_bytes')
                    ->nullable();

                $table->timestamps();

                $table->index(
                    ['prescription_id', 'attachment_type'],
                    'prescription_attachments_type_idx',
                );
            },
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'prescription_attachments',
        );
    }
};