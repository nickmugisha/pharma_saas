<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_payments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('pharmacy_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('sale_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('received_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('voided_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('payment_number', 100);
            $table->timestamp('paid_at')->index();

            $table->decimal('amount', 15, 2);

            $table->string('payment_method', 40)->index();
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
                'sale_pay_pharm_number_unique',
            );

            $table->index(
                ['pharmacy_id', 'sale_id', 'status'],
                'sale_pay_pharm_sale_status_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_payments');
    }
};