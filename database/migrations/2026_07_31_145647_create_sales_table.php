<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('pharmacy_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('pharmacy_branch_id')
                ->constrained('pharmacy_branches')
                ->restrictOnDelete();

            $table->foreignId('cashier_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('voided_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('sale_number', 100);
            $table->string('receipt_number', 100)->nullable();

            $table->string('channel', 30)
                ->default('pos')
                ->index();

            $table->timestamp('sold_at')->nullable()->index();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('voided_at')->nullable();

            $table->string('status', 30)
                ->default('draft')
                ->index();

            $table->string('payment_status', 30)
                ->default('unpaid')
                ->index();

            $table->string('currency', 3)->default('BIF');

            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('discount_total', 15, 2)->default(0);
            $table->decimal('tax_total', 15, 2)->default(0);
            $table->decimal('grand_total', 15, 2)->default(0);
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->decimal('change_amount', 15, 2)->default(0);

            // Temporary customer snapshot until SLOT 12 creates accounts.
            $table->string('customer_name', 150)->nullable();
            $table->string('customer_phone', 50)->nullable()->index();

            // Allows future orders/reservations to create a POS sale.
            $table->string('source_type', 100)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();

            $table->text('notes')->nullable();
            $table->text('void_reason')->nullable();

            $table->timestamps();

            $table->unique(
                ['pharmacy_id', 'sale_number'],
                'sales_pharm_number_unique',
            );

            $table->unique(
                ['pharmacy_id', 'receipt_number'],
                'sales_pharm_receipt_unique',
            );

            $table->index(
                ['source_type', 'source_id'],
                'sales_source_idx',
            );

            $table->index(
                ['pharmacy_id', 'pharmacy_branch_id', 'status', 'sold_at'],
                'sales_pharm_branch_status_date_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};