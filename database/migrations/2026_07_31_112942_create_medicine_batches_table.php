<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medicine_batches', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('pharmacy_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('pharmacy_branch_id')
                ->constrained('pharmacy_branches')
                ->restrictOnDelete();

            $table->foreignId('pharmacy_medicine_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('supplier_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->foreignId('purchase_order_item_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->string('batch_number', 100);

            $table->date('manufacturing_date')->nullable();
            $table->date('expiry_date')->index();

            $table->decimal('unit_cost', 15, 2);
            $table->decimal('quantity_received', 15, 3)->default(0);
            $table->decimal('quantity_available', 15, 3)->default(0);

            $table->string('status', 30)
                ->default('active')
                ->index();

            $table->timestamp('received_at')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(
                [
                    'pharmacy_branch_id',
                    'pharmacy_medicine_id',
                    'batch_number',
                ],
                'mb_branch_med_batch_unique',
            );

            $table->index(
                ['pharmacy_id', 'expiry_date', 'status'],
                'mb_pharm_expiry_status_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medicine_batches');
    }
};