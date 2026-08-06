<?php

namespace App\Actions\Wallet;

use App\Models\ClientWallet;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PostWalletTransaction
{
    public function handle(
        ClientWallet $wallet,
        string $direction,
        float $amount,
        string $type,
        string $description,
        ?User $actor = null,
        ?Model $source = null,
        ?WalletTransaction $relatedTransaction = null,
        ?array $metadata = null,
        ?string $idempotencyKey = null,
    ): WalletTransaction {
        if (! in_array($direction, [
            WalletTransaction::DIRECTION_CREDIT,
            WalletTransaction::DIRECTION_DEBIT,
        ], true)) {
            throw ValidationException::withMessages([
                'direction' => 'The wallet transaction direction is invalid.',
            ]);
        }

        $amount = round($amount, 2);

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'The wallet transaction amount must be greater than zero.',
            ]);
        }

        return DB::transaction(function () use (
            $wallet,
            $direction,
            $amount,
            $type,
            $description,
            $actor,
            $source,
            $relatedTransaction,
            $metadata,
            $idempotencyKey,
        ): WalletTransaction {
            if (filled($idempotencyKey)) {
                $existing = WalletTransaction::query()
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();

                if ($existing) {
                    return $existing;
                }
            }

            $lockedWallet = ClientWallet::query()
                ->whereKey($wallet->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedWallet->status !== 'active') {
                throw ValidationException::withMessages([
                    'wallet' => 'This wallet is not active.',
                ]);
            }

            $credits = (float) WalletTransaction::query()
                ->where('client_wallet_id', $lockedWallet->id)
                ->where('direction', WalletTransaction::DIRECTION_CREDIT)
                ->sum('amount');

            $debits = (float) WalletTransaction::query()
                ->where('client_wallet_id', $lockedWallet->id)
                ->where('direction', WalletTransaction::DIRECTION_DEBIT)
                ->sum('amount');

            $currentBalance = round($credits - $debits, 2);

            if (
                $direction === WalletTransaction::DIRECTION_DEBIT
                && $amount > $currentBalance + 0.005
            ) {
                throw ValidationException::withMessages([
                    'wallet' => sprintf(
                        'Insufficient wallet balance. %s BIF is available.',
                        number_format(max($currentBalance, 0), 2),
                    ),
                ]);
            }

            $balanceAfter = $direction === WalletTransaction::DIRECTION_CREDIT
                ? round($currentBalance + $amount, 2)
                : round($currentBalance - $amount, 2);

            return WalletTransaction::create([
                'client_wallet_id' => $lockedWallet->id,
                'created_by_user_id' => $actor?->id,
                'related_transaction_id' => $relatedTransaction?->id,
                'idempotency_key' => filled($idempotencyKey)
                    ? $idempotencyKey
                    : null,
                'type' => $type,
                'direction' => $direction,
                'amount' => $amount,
                'balance_after' => $balanceAfter,
                'currency' => $lockedWallet->currency,
                'source_type' => $source?->getMorphClass(),
                'source_id' => $source?->getKey(),
                'description' => trim($description),
                'metadata' => $metadata,
                'occurred_at' => now(),
            ]);
        }, attempts: 5);
    }
}
