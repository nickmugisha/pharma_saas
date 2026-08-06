<?php

namespace App\Actions\Marketplace;

use App\Actions\Stock\SyncInventoryAlerts;
use App\Actions\Wallet\PostWalletTransaction;
use App\Models\MarketplaceOrder;
use App\Models\MarketplaceStockReservation;
use App\Models\MedicineBatch;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RefundMarketplaceOrder
{
    public function handle(
        User $actor,
        MarketplaceOrder $order,
        string $reason,
    ): MarketplaceOrder {
        $this->authorize($actor, $order);
        $reason = trim($reason);

        if (mb_strlen($reason) < 10) {
            throw ValidationException::withMessages([
                'reason' => 'The refund reason must contain at least ten characters.',
            ]);
        }

        return DB::transaction(function () use (
            $actor,
            $order,
            $reason,
        ): MarketplaceOrder {
            $lockedOrder = MarketplaceOrder::query()
                ->with(['wallet', 'walletPaymentTransaction'])
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (
                $lockedOrder->status !== MarketplaceOrder::STATUS_CONFIRMED
                || $lockedOrder->payment_status !== MarketplaceOrder::PAYMENT_PAID
                || ! $lockedOrder->walletPaymentTransaction
            ) {
                throw ValidationException::withMessages([
                    'order' => 'Only a confirmed paid order can be refunded.',
                ]);
            }

            $reservations = MarketplaceStockReservation::query()
                ->where('marketplace_order_id', $lockedOrder->id)
                ->where('status', MarketplaceStockReservation::STATUS_CONVERTED)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $batchIds = $reservations->pluck('medicine_batch_id')->unique();
            $batches = MedicineBatch::query()
                ->whereIn('id', $batchIds)
                ->where('pharmacy_id', $lockedOrder->pharmacy_id)
                ->where('pharmacy_branch_id', $lockedOrder->pharmacy_branch_id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($reservations->isNotEmpty() && $batches->count() !== $batchIds->count()) {
                throw ValidationException::withMessages([
                    'stock' => 'One or more reserved batches could not be restored.',
                ]);
            }

            $affectedListingIds = [];

            foreach ($reservations as $reservation) {
                $batch = $batches->get($reservation->medicine_batch_id);

                if (! $batch) {
                    throw ValidationException::withMessages([
                        'stock' => 'A reserved medicine batch could not be restored.',
                    ]);
                }

                $quantity = round((float) $reservation->quantity, 3);
                $newBalance = round((float) $batch->quantity_available + $quantity, 3);
                $expired = Carbon::parse($batch->expiry_date)
                    ->startOfDay()
                    ->lte(today());

                $batch->forceFill([
                    'quantity_available' => $newBalance,
                    'status' => $expired ? 'expired' : 'active',
                ])->save();

                $reservation->forceFill([
                    'status' => MarketplaceStockReservation::STATUS_RELEASED,
                    'released_at' => now(),
                    'release_reason' => $reason,
                ])->save();

                StockMovement::create([
                    'pharmacy_id' => $lockedOrder->pharmacy_id,
                    'pharmacy_branch_id' => $lockedOrder->pharmacy_branch_id,
                    'pharmacy_medicine_id' => $reservation->pharmacy_medicine_id,
                    'medicine_batch_id' => $batch->id,
                    'created_by_user_id' => $actor->id,
                    'movement_type' => 'marketplace_refund',
                    'direction' => 'in',
                    'quantity' => $quantity,
                    'unit_cost' => $batch->unit_cost,
                    'balance_after' => $newBalance,
                    'reference_type' => MarketplaceStockReservation::class,
                    'reference_id' => $reservation->id,
                    'occurred_at' => now(),
                    'notes' => "Refunded marketplace order {$lockedOrder->order_number}",
                ]);

                $affectedListingIds[] = $reservation->pharmacy_medicine_id;
            }

            $refundTransaction = app(PostWalletTransaction::class)->handle(
                wallet: $lockedOrder->wallet,
                direction: WalletTransaction::DIRECTION_CREDIT,
                amount: (float) $lockedOrder->grand_total,
                type: WalletTransaction::TYPE_MARKETPLACE_REFUND,
                description: sprintf(
                    'Refund for marketplace order %s. %s',
                    $lockedOrder->order_number,
                    $reason,
                ),
                actor: $actor,
                source: $lockedOrder,
                relatedTransaction: $lockedOrder->walletPaymentTransaction,
                metadata: [
                    'order_number' => $lockedOrder->order_number,
                    'reason' => $reason,
                ],
                idempotencyKey: "marketplace_order_refund:{$lockedOrder->id}",
            );

            $lockedOrder->forceFill([
                'status' => MarketplaceOrder::STATUS_CANCELLED,
                'payment_status' => MarketplaceOrder::PAYMENT_REFUNDED,
                'wallet_refund_transaction_id' => $refundTransaction->id,
                'refunded_at' => now(),
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
            ])->save();

            app(RecordMarketplaceOrderEvent::class)->handle(
                order: $lockedOrder,
                eventType: 'wallet_payment_refunded',
                title: 'Wallet payment refunded',
                description: $reason,
                metadata: [
                    'wallet_transaction_id' => $refundTransaction->id,
                    'transaction_number' => $refundTransaction->transaction_number,
                ],
                actor: $actor,
            );

            foreach (array_unique($affectedListingIds) as $listingId) {
                app(SyncInventoryAlerts::class)->handle(
                    pharmacyId: $lockedOrder->pharmacy_id,
                    branchId: $lockedOrder->pharmacy_branch_id,
                    pharmacyMedicineId: (int) $listingId,
                );
            }

            return $lockedOrder->fresh([
                'wallet',
                'walletPaymentTransaction',
                'walletRefundTransaction',
                'items',
                'stockReservations.medicineBatch',
                'events.actorUser',
            ]);
        }, attempts: 5);
    }

    private function authorize(
        User $actor,
        MarketplaceOrder $order,
    ): void {
        $platformAllowed = $actor->can('wallets.manage');

        $pharmacyAllowed = $actor->can('marketplace.orders.manage')
            && filled($actor->pharmacy_id)
            && (int) $actor->pharmacy_id === (int) $order->pharmacy_id
            && (
                blank($actor->pharmacy_branch_id)
                || (int) $actor->pharmacy_branch_id
                    === (int) $order->pharmacy_branch_id
            );

        abort_unless($platformAllowed || $pharmacyAllowed, 403);
    }
}
