<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'prescription_dispensing_items',
            function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();

                $table->foreignId(
                    'prescription_dispensing_id',
                )
                    ->constrained(
                        'prescription_dispensings',
                    )
                    ->restrictOnDelete();

                $table->foreignId('prescription_item_id')
                    ->constrained()
                    ->restrictOnDelete();

                $table->foreignId('sale_item_id')
                    ->unique()
                    ->constrained()
                    ->restrictOnDelete();

                $table->decimal(
                    'quantity_dispensed',
                    12,
                    3,
                );

                $table->timestamps();

                $table->unique(
                    [
                        'prescription_dispensing_id',
                        'prescription_item_id',
                    ],
                    'prescription_dispensing_item_unique',
                );

                $table->index(
                    [
                        'prescription_item_id',
                        'created_at',
                    ],
                    'prescription_item_dispensing_idx',
                );
            },
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'prescription_dispensing_items',
        );
    }
};