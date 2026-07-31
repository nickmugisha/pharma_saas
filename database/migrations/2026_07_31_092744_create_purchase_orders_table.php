<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('pharmacy_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('pharmacy_branch_id')
                ->constrained('pharmacy_branches')
                ->restrictOnDelete();

            $table->foreignId('supplier_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('created_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('approved_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('order_number', 100);
            $table->date('order_date')->index();
            $table->date('expected_delivery_date')->nullable()->index();

            $table->string('currency', 3)->default('BIF');

            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('discount_total', 15, 2)->default(0);
            $table->decimal('tax_total', 15, 2)->default(0);
            $table->decimal('shipping_total', 15, 2)->default(0);
            $table->decimal('grand_total', 15, 2)->default(0);

            $table->string('status', 30)
                ->default('draft')
                ->index();

            $table->text('notes')->nullable();

            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique([
                'pharmacy_id',
                'order_number',
            ]);

            $table->index([
                'pharmacy_id',
                'status',
                'order_date',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};