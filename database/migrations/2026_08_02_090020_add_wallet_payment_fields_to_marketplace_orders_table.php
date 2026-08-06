<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_orders', function (Blueprint $table): void {
            $table->string('payment_status', 30)
                ->default('unpaid')
                ->after('status')
                ->index();
            $table->foreignId('wallet_payment_transaction_id')
                ->nullable()
                ->unique()
                ->after('client_wallet_id')
                ->constrained('wallet_transactions')
                ->restrictOnDelete();
            $table->foreignId('wallet_refund_transaction_id')
                ->nullable()
                ->unique()
                ->after('wallet_payment_transaction_id')
                ->constrained('wallet_transactions')
                ->restrictOnDelete();
            $table->timestamp('paid_at')
                ->nullable()
                ->after('placed_at');
            $table->timestamp('refunded_at')
                ->nullable()
                ->after('paid_at');
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_orders', function (Blueprint $table): void {
            $table->dropForeign(['wallet_payment_transaction_id']);
            $table->dropForeign(['wallet_refund_transaction_id']);
            $table->dropColumn([
                'payment_status',
                'wallet_payment_transaction_id',
                'wallet_refund_transaction_id',
                'paid_at',
                'refunded_at',
            ]);
        });
    }
};
