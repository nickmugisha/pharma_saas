<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_invoices', function (Blueprint $table): void {
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

            $table->foreignId('purchase_order_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('created_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('invoice_number', 100);
            $table->date('invoice_date')->index();
            $table->date('due_date')->nullable()->index();

            $table->string('currency', 3)->default('BIF');

            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('discount_total', 15, 2)->default(0);
            $table->decimal('tax_total', 15, 2)->default(0);
            $table->decimal('shipping_total', 15, 2)->default(0);
            $table->decimal('grand_total', 15, 2);
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->decimal('balance_due', 15, 2)->default(0);

            $table->string('status', 30)
                ->default('unpaid')
                ->index();

            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(
                ['pharmacy_id', 'supplier_id', 'invoice_number'],
                'sup_inv_pharm_supplier_no_unique',
            );

            $table->index(
                ['pharmacy_id', 'status', 'due_date'],
                'sup_inv_pharm_status_due_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_invoices');
    }
};