<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_cart_items', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('marketplace_cart_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pharmacy_id')->constrained()->restrictOnDelete();
            $table->foreignId('pharmacy_branch_id')->constrained('pharmacy_branches')->restrictOnDelete();
            $table->foreignId('pharmacy_medicine_id')->constrained()->restrictOnDelete();
            $table->foreignId('marketplace_offer_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('quantity', 15, 3);
            $table->decimal('unit_price_snapshot', 15, 2);
            $table->string('currency', 3)->default('BIF');
            $table->string('fulfillment_method', 20)->default('pickup');
            $table->string('online_sale_mode', 40)->default('otc');
            $table->timestamps();

            $table->unique(
                ['marketplace_cart_id', 'pharmacy_branch_id', 'pharmacy_medicine_id', 'fulfillment_method'],
                'mp_cart_branch_listing_method_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_cart_items');
    }
};
