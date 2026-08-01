<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'prescription_activities',
            function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();

                $table->foreignId('pharmacy_id')
                    ->constrained()
                    ->restrictOnDelete();

                $table->foreignId('pharmacy_branch_id')
                    ->nullable()
                    ->constrained('pharmacy_branches')
                    ->nullOnDelete();

                $table->foreignId('prescription_id')
                    ->constrained()
                    ->restrictOnDelete();

                $table->foreignId('actor_user_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->string('activity_type', 100)
                    ->index();

                $table->string('title', 191);
                $table->text('description')->nullable();
                $table->json('metadata')->nullable();

                $table->timestamp('occurred_at')
                    ->index();

                $table->timestamps();

                $table->index(
                    [
                        'pharmacy_id',
                        'prescription_id',
                        'occurred_at',
                    ],
                    'prescription_activities_timeline_idx',
                );
            },
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'prescription_activities',
        );
    }
};