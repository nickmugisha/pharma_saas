<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_payments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('pharmacy_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('supplier_invoice_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('supplier_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('created_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('voided_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('payment_number', 100);
            $table->date('payment_date')->index();
            $table->decimal('amount', 15, 2);

            $table->string('payment_method', 30)->index();
            $table->string('reference', 150)->nullable();

            $table->string('status', 30)
                ->default('completed')
                ->index();

            $table->timestamp('voided_at')->nullable();
            $table->text('void_reason')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(
                ['pharmacy_id', 'payment_number'],
                'sup_pay_pharm_no_unique',
            );

            $table->index(
                ['pharmacy_id', 'status', 'payment_date'],
                'sup_pay_pharm_status_date_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_payments');
    }
};