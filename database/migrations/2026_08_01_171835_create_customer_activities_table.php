<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'customer_activities',
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

                $table->foreignId('customer_id')
                    ->constrained()
                    ->restrictOnDelete();

                $table->foreignId('actor_user_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->string('activity_type', 100)
                    ->index();

                $table->string('subject_type', 191)
                    ->nullable();

                $table->unsignedBigInteger('subject_id')
                    ->nullable();

                $table->string('title', 191);
                $table->text('description')->nullable();

                $table->json('metadata')->nullable();

                $table->timestamp('occurred_at')->index();

                $table->timestamps();

                $table->index(
                    ['subject_type', 'subject_id'],
                    'customer_activities_subject_idx',
                );

                $table->index(
                    [
                        'pharmacy_id',
                        'customer_id',
                        'occurred_at',
                    ],
                    'customer_activities_timeline_idx',
                );
            },
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_activities');
    }
};