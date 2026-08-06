<?php

namespace App\Actions\Wallet;

use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReverseWalletTransaction
{
    public function handle(
        User $actor,
        WalletTransaction $transaction,
        string $reason,
    ): WalletTransaction {
        abort_unless($actor->can('wallets.manage'), 403);

        $reason = trim($reason);

        if (mb_strlen($reason) < 10) {
            throw ValidationException::withMessages([
                'reason' => 'The reversal reason must contain at least ten characters.',
            ]);
        }

        return DB::transaction(function () use (
            $actor,
            $transaction,
            $reason,
        ): WalletTransaction {
            $lockedTransaction = WalletTransaction::query()
                ->with('wallet')
                ->whereKey($transaction->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (in_array($lockedTransaction->type, [
                WalletTransaction::TYPE_MARKETPLACE_PAYMENT,
                WalletTransaction::TYPE_MARKETPLACE_REFUND,
                WalletTransaction::TYPE_REVERSAL,
            ], true)) {
                throw ValidationException::withMessages([
                    'transaction' => 'Marketplace payments and refunds must be reversed through the order workflow.',
                ]);
            }

            if (
                WalletTransaction::query()
                    ->where('related_transaction_id', $lockedTransaction->id)
                    ->where('type', WalletTransaction::TYPE_REVERSAL)
                    ->exists()
            ) {
                throw ValidationException::withMessages([
                    'transaction' => 'This wallet transaction has already been reversed.',
                ]);
            }

            $direction = $lockedTransaction->direction
                === WalletTransaction::DIRECTION_CREDIT
                    ? WalletTransaction::DIRECTION_DEBIT
                    : WalletTransaction::DIRECTION_CREDIT;

            return app(PostWalletTransaction::class)->handle(
                wallet: $lockedTransaction->wallet,
                direction: $direction,
                amount: (float) $lockedTransaction->amount,
                type: WalletTransaction::TYPE_REVERSAL,
                description: sprintf(
                    'Reversal of %s. %s',
                    $lockedTransaction->transaction_number,
                    $reason,
                ),
                actor: $actor,
                source: $lockedTransaction->source,
                relatedTransaction: $lockedTransaction,
                metadata: ['reason' => $reason],
                idempotencyKey: "wallet_transaction_reversal:{$lockedTransaction->id}",
            );
        }, attempts: 5);
    }
}
