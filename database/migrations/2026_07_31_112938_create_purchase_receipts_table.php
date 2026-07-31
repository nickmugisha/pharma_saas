<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_receipts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('pharmacy_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('pharmacy_branch_id')
                ->constrained('pharmacy_branches')
                ->restrictOnDelete();

            $table->foreignId('purchase_order_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('supplier_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('received_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('receipt_number', 100);
            $table->timestamp('received_at')->nullable();

            $table->string('status', 30)
                ->default('draft')
                ->index();

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(
                ['pharmacy_id', 'receipt_number'],
                'pr_pharm_no_unique',
            );

            $table->index(
                ['pharmacy_id', 'status', 'received_at'],
                'pr_pharm_status_date_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_receipts');
    }
};