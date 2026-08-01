<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_items', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('sale_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('pharmacy_medicine_id')
                ->constrained()
                ->restrictOnDelete();

            // Immutable receipt snapshots.
            $table->string('medicine_name', 191);
            $table->string('sku', 100)->nullable();

            $table->decimal('quantity', 15, 3);
            $table->decimal('unit_price', 15, 2);

            $table->decimal('discount_amount', 15, 2)
                ->default(0);

            $table->decimal('tax_rate', 8, 3)
                ->default(0);

            $table->decimal('tax_amount', 15, 2)
                ->default(0);

            $table->decimal('line_total', 15, 2)
                ->default(0);

            $table->decimal('cost_total', 15, 2)
                ->default(0);

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(
                ['sale_id', 'pharmacy_medicine_id'],
                'sale_items_sale_medicine_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_items');
    }
};