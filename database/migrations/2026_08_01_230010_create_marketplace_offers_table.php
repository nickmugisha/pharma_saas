<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_offers', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('pharmacy_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('pharmacy_branch_id')
                ->constrained('pharmacy_branches')
                ->cascadeOnDelete();

            $table->foreignId('pharmacy_medicine_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->decimal('online_price', 15, 2)->nullable();
            $table->string('currency', 3)->default('BIF');
            $table->boolean('pickup_enabled')->default(true);
            $table->boolean('delivery_enabled')->default(false);
            $table->decimal('delivery_fee', 15, 2)->default(0);
            $table->decimal('max_order_quantity', 15, 3)->nullable();
            $table->unsignedSmallInteger('preparation_minutes')->default(30);
            $table->text('marketplace_description')->nullable();
            $table->string('status', 30)->default('active')->index();
            $table->timestamps();

            $table->unique(
                ['pharmacy_branch_id', 'pharmacy_medicine_id'],
                'mp_offer_branch_listing_unique',
            );

            $table->index(
                ['pharmacy_id', 'status'],
                'mp_offer_pharmacy_status_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_offers');
    }
};
