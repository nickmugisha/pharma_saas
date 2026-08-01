<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'prescription_items',
            function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();

                $table->foreignId('prescription_id')
                    ->constrained()
                    ->restrictOnDelete();

                $table->foreignId('medicine_id')
                    ->nullable()
                    ->constrained()
                    ->nullOnDelete();

                $table->foreignId('pharmacy_medicine_id')
                    ->nullable()
                    ->constrained('pharmacy_medicines')
                    ->nullOnDelete();

                $table->string('prescribed_name', 191);
                $table->string('strength', 100)->nullable();
                $table->string('dosage_form', 100)->nullable();
                $table->string('dosage', 191)->nullable();
                $table->string('frequency', 191)->nullable();
                $table->string('duration', 191)->nullable();

                $table->decimal(
                    'quantity_prescribed',
                    12,
                    3,
                );

                $table->decimal(
                    'quantity_dispensed',
                    12,
                    3,
                )->default(0);

                $table->text('instructions')->nullable();

                $table->boolean('substitution_allowed')
                    ->default(false);

                $table->string('status', 30)
                    ->default('pending');

                $table->timestamps();

                $table->index(
                    ['prescription_id', 'status'],
                    'prescription_items_status_idx',
                );
            },
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('prescription_items');
    }
};