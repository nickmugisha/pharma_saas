<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_transactions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('client_wallet_id')
                ->constrained('client_wallets')
                ->restrictOnDelete();
            $table->foreignId('created_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('related_transaction_id')
                ->nullable()
                ->constrained('wallet_transactions')
                ->restrictOnDelete();
            $table->string('transaction_number', 100)->unique();
            $table->string('idempotency_key', 191)->nullable()->unique();
            $table->string('type', 50)->index();
            $table->string('direction', 10)->index();
            $table->decimal('amount', 15, 2);
            $table->decimal('balance_after', 15, 2);
            $table->string('currency', 3)->default('BIF');
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->text('description');
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();

            $table->index(
                ['client_wallet_id', 'occurred_at'],
                'wallet_transactions_wallet_date_idx',
            );
            $table->index(
                ['source_type', 'source_id'],
                'wallet_transactions_source_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};
