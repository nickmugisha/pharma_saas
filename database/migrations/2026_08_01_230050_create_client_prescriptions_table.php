<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_prescriptions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('prescription_number', 100)->unique();
            $table->string('status', 30)->default('submitted')->index();
            $table->string('prescriber_name')->nullable();
            $table->string('prescriber_facility')->nullable();
            $table->date('issued_at')->nullable();
            $table->date('valid_until')->nullable()->index();
            $table->string('disk', 50)->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status', 'valid_until'], 'client_rx_user_status_valid_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_prescriptions');
    }
};
