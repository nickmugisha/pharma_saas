<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_funding_requests', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('request_number', 100)->unique();
            $table->foreignId('client_wallet_id')
                ->constrained('client_wallets')
                ->restrictOnDelete();
            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->foreignId('reviewed_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('wallet_transaction_id')
                ->nullable()
                ->unique()
                ->constrained('wallet_transactions')
                ->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3)->default('BIF');
            $table->string('funding_method', 40);
            $table->string('external_reference', 150)->nullable();
            $table->string('status', 30)->default('pending')->index();
            $table->timestamp('requested_at')->index();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(
                ['client_wallet_id', 'status', 'requested_at'],
                'wallet_funding_wallet_status_date_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_funding_requests');
    }
};
