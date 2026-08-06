<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_stock_reservations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('marketplace_order_id')->constrained()->restrictOnDelete();
            $table->foreignId('marketplace_order_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('pharmacy_id')->constrained()->restrictOnDelete();
            $table->foreignId('pharmacy_branch_id')->constrained('pharmacy_branches')->restrictOnDelete();
            $table->foreignId('pharmacy_medicine_id')->constrained()->restrictOnDelete();
            $table->foreignId('medicine_batch_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 15, 3);
            $table->string('status', 30)->default('held')->index();
            $table->timestamp('expires_at')->index();
            $table->timestamp('released_at')->nullable();
            $table->text('release_reason')->nullable();
            $table->timestamps();

            $table->unique(
                ['marketplace_order_item_id', 'medicine_batch_id'],
                'mp_reservation_item_batch_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_stock_reservations');
    }
};
