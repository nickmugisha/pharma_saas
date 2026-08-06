<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_orders', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('order_number', 100)->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('client_wallet_id')->constrained()->restrictOnDelete();
            $table->foreignId('pharmacy_id')->constrained()->restrictOnDelete();
            $table->foreignId('pharmacy_branch_id')->constrained('pharmacy_branches')->restrictOnDelete();
            $table->string('status', 40)->default('draft')->index();
            $table->string('prescription_status', 40)->default('not_required')->index();
            $table->string('fulfillment_method', 20)->default('pickup')->index();
            $table->string('client_name');
            $table->string('client_email');
            $table->string('client_phone', 50)->nullable();
            $table->string('address_label', 80)->nullable();
            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('province', 100)->nullable();
            $table->string('country', 100)->nullable();
            $table->text('delivery_instructions')->nullable();
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('delivery_fee', 15, 2)->default(0);
            $table->decimal('grand_total', 15, 2)->default(0);
            $table->string('currency', 3)->default('BIF');
            $table->timestamp('reservation_expires_at')->nullable()->index();
            $table->timestamp('placed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['pharmacy_id', 'pharmacy_branch_id', 'status'], 'mp_order_pharm_branch_status_idx');
            $table->index(['user_id', 'status', 'created_at'], 'mp_order_user_status_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_orders');
    }
};
