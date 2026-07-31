<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_receipt_items', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('purchase_receipt_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('purchase_order_item_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('pharmacy_medicine_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('medicine_batch_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->string('batch_number', 100);

            $table->date('manufacturing_date')->nullable();
            $table->date('expiry_date');

            $table->decimal('quantity_received', 15, 3);
            $table->decimal('unit_cost', 15, 2);
            $table->decimal('line_cost', 15, 2)->default(0);

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(
                [
                    'purchase_receipt_id',
                    'purchase_order_item_id',
                    'batch_number',
                ],
                'pri_receipt_item_batch_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_receipt_items');
    }
};