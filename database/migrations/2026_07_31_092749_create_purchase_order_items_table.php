<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_order_items', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('purchase_order_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('pharmacy_medicine_id')
                ->constrained()
                ->restrictOnDelete();

            $table->decimal('quantity_ordered', 15, 3);
            $table->decimal('quantity_received', 15, 3)->default(0);

            $table->decimal('unit_cost', 15, 2);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('line_total', 15, 2)->default(0);

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(
    [
        'purchase_order_id',
        'pharmacy_medicine_id',
    ],
    'po_items_order_medicine_unique',
);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
    }
};