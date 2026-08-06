<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_management_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('pharmacy_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('pharmacy_branch_id')
                ->nullable()
                ->constrained('pharmacy_branches')
                ->nullOnDelete();
            $table->foreignId('actor_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('target_user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->string('event_type', 60);
            $table->string('old_role', 80)->nullable();
            $table->string('new_role', 80)->nullable();
            $table->foreignId('old_branch_id')
                ->nullable()
                ->constrained('pharmacy_branches')
                ->nullOnDelete();
            $table->foreignId('new_branch_id')
                ->nullable()
                ->constrained('pharmacy_branches')
                ->nullOnDelete();
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['pharmacy_id', 'occurred_at']);
            $table->index(['pharmacy_branch_id', 'occurred_at']);
            $table->index(['target_user_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_management_events');
    }
};
