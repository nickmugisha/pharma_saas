<?php

namespace App\Actions\Marketplace;

use App\Actions\Wallet\PostWalletTransaction;
use App\Models\MarketplaceOrder;
use App\Models\MarketplaceStockReservation;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PayMarketplaceOrder
{
    public function handle(
        User $client,
        MarketplaceOrder $order,
    ): MarketplaceOrder {
        abort_unless(
            $client->is_active
            && $client->hasRole('client')
            && (int) $order->user_id === (int) $client->id,
            403,
        );

        return DB::transaction(function () use (
            $client,
            $order,
        ): MarketplaceOrder {
            $lockedOrder = MarketplaceOrder::query()
                ->with(['wallet', 'items'])
                ->whereKey($order->id)
                ->where('user_id', $client->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (
                $lockedOrder->status !== MarketplaceOrder::STATUS_AWAITING_PAYMENT
                || $lockedOrder->payment_status !== MarketplaceOrder::PAYMENT_UNPAID
            ) {
                throw ValidationException::withMessages([
                    'order' => 'This order is not waiting for wallet payment.',
                ]);
            }

            if (
                $lockedOrder->prescription_status
                !== 'not_required'
                && $lockedOrder->prescription_status !== 'approved'
            ) {
                throw ValidationException::withMessages([
                    'order' => 'Prescription review must be completed before payment.',
                ]);
            }

            if (
                $lockedOrder->reservation_expires_at === null
                || $lockedOrder->reservation_expires_at->isPast()
            ) {
                throw ValidationException::withMessages([
                    'order' => 'The stock reservation has expired. Create a new order.',
                ]);
            }

            $reservations = MarketplaceStockReservation::query()
                ->where('marketplace_order_id', $lockedOrder->id)
                ->where('status', MarketplaceStockReservation::STATUS_HELD)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($reservations->isEmpty()) {
                throw ValidationException::withMessages([
                    'order' => 'No active stock reservation exists for this order.',
                ]);
            }

            if (! $lockedOrder->wallet || $lockedOrder->wallet->status !== 'active') {
                throw ValidationException::withMessages([
                    'wallet' => 'An active wallet is required to pay this order.',
                ]);
            }

            $transaction = app(PostWalletTransaction::class)->handle(
                wallet: $lockedOrder->wallet,
                direction: WalletTransaction::DIRECTION_DEBIT,
                amount: (float) $lockedOrder->grand_total,
                type: WalletTransaction::TYPE_MARKETPLACE_PAYMENT,
                description: sprintf(
                    'Payment for marketplace order %s.',
                    $lockedOrder->order_number,
                ),
                actor: $client,
                source: $lockedOrder,
                metadata: [
                    'order_number' => $lockedOrder->order_number,
                    'pharmacy_id' => $lockedOrder->pharmacy_id,
                    'pharmacy_branch_id' => $lockedOrder->pharmacy_branch_id,
                ],
                idempotencyKey: "marketplace_order_payment:{$lockedOrder->id}",
            );

            foreach ($reservations as $reservation) {
                $reservation->forceFill([
                    'status' => MarketplaceStockReservation::STATUS_CONVERTED,
                ])->save();
            }

            $lockedOrder->forceFill([
                'status' => MarketplaceOrder::STATUS_CONFIRMED,
                'payment_status' => MarketplaceOrder::PAYMENT_PAID,
                'wallet_payment_transaction_id' => $transaction->id,
                'paid_at' => now(),
                'reservation_expires_at' => null,
            ])->save();

            app(RecordMarketplaceOrderEvent::class)->handle(
                order: $lockedOrder,
                eventType: 'wallet_payment_completed',
                title: 'Wallet payment completed',
                description: sprintf(
                    '%s BIF was debited from wallet %s.',
                    number_format((float) $lockedOrder->grand_total, 2),
                    $lockedOrder->wallet->wallet_number,
                ),
                metadata: [
                    'wallet_transaction_id' => $transaction->id,
                    'transaction_number' => $transaction->transaction_number,
                ],
                actor: $client,
            );

            return $lockedOrder->fresh([
                'wallet',
                'walletPaymentTransaction',
                'items',
                'stockReservations.medicineBatch',
                'events.actorUser',
            ]);
        }, attempts: 5);
    }
}
