<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_alerts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('pharmacy_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('pharmacy_branch_id')
                ->constrained('pharmacy_branches')
                ->restrictOnDelete();

            $table->foreignId('pharmacy_medicine_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('medicine_batch_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->foreignId('acknowledged_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('resolved_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('alert_key', 191)->unique();

            $table->string('alert_type', 40)->index();
            $table->string('severity', 20)
                ->default('warning')
                ->index();

            $table->decimal('current_value', 15, 3)->nullable();
            $table->decimal('threshold_value', 15, 3)->nullable();

            $table->text('message');

            $table->string('status', 30)
                ->default('open')
                ->index();

            $table->timestamp('detected_at')->index();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('resolved_at')->nullable();

            $table->timestamps();

            $table->index(
                ['pharmacy_id', 'status', 'severity'],
                'ia_pharm_status_severity_idx',
            );

            $table->index(
                ['pharmacy_branch_id', 'alert_type', 'status'],
                'ia_branch_type_status_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_alerts');
    }
};