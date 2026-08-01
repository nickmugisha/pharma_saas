<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'prescription_dispensings',
            function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();

                $table->foreignId('pharmacy_id')
                    ->constrained()
                    ->restrictOnDelete();

                $table->foreignId('pharmacy_branch_id')
                    ->constrained('pharmacy_branches')
                    ->restrictOnDelete();

                $table->foreignId('prescription_id')
                    ->constrained()
                    ->restrictOnDelete();

                $table->foreignId('sale_id')
                    ->unique()
                    ->constrained()
                    ->restrictOnDelete();

                $table->foreignId('dispensed_by_user_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->foreignId('voided_by_user_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->string('dispensing_number', 100);

                $table->string('status', 30)
                    ->default('completed')
                    ->index();

                $table->timestamp('dispensed_at')
                    ->index();

                $table->timestamp('voided_at')
                    ->nullable();

                $table->text('void_reason')->nullable();
                $table->text('notes')->nullable();

                $table->timestamps();

                $table->unique(
                    [
                        'pharmacy_id',
                        'dispensing_number',
                    ],
                    'prescription_dispensing_number_unique',
                );

                $table->index(
                    [
                        'pharmacy_id',
                        'prescription_id',
                        'status',
                    ],
                    'prescription_dispensings_status_idx',
                );
            },
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'prescription_dispensings',
        );
    }
};