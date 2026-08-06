<?php

namespace App\Actions\Marketplace;

use App\Actions\Stock\SyncInventoryAlerts;
use App\Models\MarketplaceOrder;
use App\Models\MarketplaceStockReservation;
use App\Models\MedicineBatch;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReleaseMarketplaceOrderStock
{
    public function handle(
        MarketplaceOrder $order,
        string $reason,
        string $finalStatus = MarketplaceOrder::STATUS_CANCELLED,
        ?User $actor = null,
    ): MarketplaceOrder {
        return DB::transaction(function () use (
            $order,
            $reason,
            $finalStatus,
            $actor,
        ): MarketplaceOrder {
            $lockedOrder = MarketplaceOrder::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            $reservations = MarketplaceStockReservation::query()
                ->where('marketplace_order_id', $lockedOrder->id)
                ->where('status', MarketplaceStockReservation::STATUS_HELD)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $batchIds = $reservations->pluck('medicine_batch_id')->unique();
            $batches = MedicineBatch::query()
                ->whereIn('id', $batchIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $affectedListingIds = [];

            foreach ($reservations as $reservation) {
                $batch = $batches->get($reservation->medicine_batch_id);

                if (! $batch) {
                    throw ValidationException::withMessages([
                        'stock' =>
                            'A reserved medicine batch could not be restored.',
                    ]);
                }

                $quantity = round((float) $reservation->quantity, 3);
                $newBalance = round((float) $batch->quantity_available + $quantity, 3);
                $expired = Carbon::parse($batch->expiry_date)->startOfDay()->lte(today());

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
                    'created_by_user_id' => $actor?->id,
                    'movement_type' => 'marketplace_release',
                    'direction' => 'in',
                    'quantity' => $quantity,
                    'unit_cost' => $batch->unit_cost,
                    'balance_after' => $newBalance,
                    'reference_type' => MarketplaceStockReservation::class,
                    'reference_id' => $reservation->id,
                    'occurred_at' => now(),
                    'notes' => "Released from marketplace order {$lockedOrder->order_number}",
                ]);

                $affectedListingIds[] = $reservation->pharmacy_medicine_id;
            }

            $lockedOrder->forceFill([
                'status' => $finalStatus,
                'reservation_expires_at' => null,
                'cancelled_at' => in_array($finalStatus, [
                    MarketplaceOrder::STATUS_CANCELLED,
                    MarketplaceOrder::STATUS_EXPIRED,
                ], true) ? now() : $lockedOrder->cancelled_at,
                'cancellation_reason' => $reason,
            ])->save();

            app(RecordMarketplaceOrderEvent::class)->handle(
                order: $lockedOrder,
                eventType: $finalStatus === MarketplaceOrder::STATUS_EXPIRED
                    ? 'reservation_expired'
                    : 'order_cancelled',
                title: $finalStatus === MarketplaceOrder::STATUS_EXPIRED
                    ? 'Reservation expired'
                    : 'Order cancelled',
                description: $reason,
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
                'items',
                'stockReservations.medicineBatch',
                'events.actorUser',
            ]);
        }, attempts: 5);
    }
}
