<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_item_batches', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('sale_item_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('medicine_batch_id')
                ->constrained()
                ->restrictOnDelete();

            $table->decimal('quantity', 15, 3);
            $table->decimal('unit_cost', 15, 2);
            $table->decimal('line_cost', 15, 2)->default(0);

            $table->timestamps();

            $table->unique(
                ['sale_item_id', 'medicine_batch_id'],
                'sib_item_batch_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_item_batches');
    }
};