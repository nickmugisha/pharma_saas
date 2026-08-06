<?php

namespace App\Actions\Marketplace;

use App\Actions\Stock\SyncInventoryAlerts;
use App\Models\MarketplaceOrder;
use App\Models\MarketplaceStockReservation;
use App\Models\MedicineBatch;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReserveMarketplaceOrderStock
{
    public function handle(
        MarketplaceOrder $order,
        ?User $actor = null,
        int $holdMinutes = 30,
    ): MarketplaceOrder {
        return DB::transaction(function () use ($order, $actor, $holdMinutes): MarketplaceOrder {
            $lockedOrder = MarketplaceOrder::query()
                ->with('items')
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (
                ! in_array($lockedOrder->status, [
                    MarketplaceOrder::STATUS_DRAFT,
                    MarketplaceOrder::STATUS_AWAITING_REVIEW,
                ], true)
            ) {
                throw ValidationException::withMessages([
                    'order' => 'This order cannot reserve stock in its current status.',
                ]);
            }

            if ($lockedOrder->stockReservations()->where('status', 'held')->exists()) {
                throw ValidationException::withMessages([
                    'order' => 'Stock has already been reserved for this order.',
                ]);
            }

            $expiresAt = now()->addMinutes(max($holdMinutes, 5));
            $affectedListingIds = [];

            foreach ($lockedOrder->items as $item) {
                $batches = MedicineBatch::query()
                    ->where('pharmacy_id', $lockedOrder->pharmacy_id)
                    ->where('pharmacy_branch_id', $lockedOrder->pharmacy_branch_id)
                    ->where('pharmacy_medicine_id', $item->pharmacy_medicine_id)
                    ->where('status', 'active')
                    ->where('quantity_available', '>', 0)
                    ->whereDate('expiry_date', '>', today())
                    ->orderBy('expiry_date')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                $available = round((float) $batches->sum('quantity_available'), 3);
                $needed = round((float) $item->quantity, 3);

                if ($available + 0.0005 < $needed) {
                    throw ValidationException::withMessages([
                        'stock' => sprintf(
                            '%s no longer has enough stock. Only %s unit(s) remain.',
                            $item->medicine_name,
                            number_format($available, 3),
                        ),
                    ]);
                }

                $remaining = $needed;

                foreach ($batches as $batch) {
                    if ($remaining <= 0.0005) {
                        break;
                    }

                    $batchAvailable = round((float) $batch->quantity_available, 3);
                    $reserved = round(min($remaining, $batchAvailable), 3);

                    if ($reserved <= 0) {
                        continue;
                    }

                    $newBalance = round($batchAvailable - $reserved, 3);

                    $reservation = MarketplaceStockReservation::create([
                        'marketplace_order_id' => $lockedOrder->id,
                        'marketplace_order_item_id' => $item->id,
                        'pharmacy_id' => $lockedOrder->pharmacy_id,
                        'pharmacy_branch_id' => $lockedOrder->pharmacy_branch_id,
                        'pharmacy_medicine_id' => $item->pharmacy_medicine_id,
                        'medicine_batch_id' => $batch->id,
                        'quantity' => $reserved,
                        'status' => MarketplaceStockReservation::STATUS_HELD,
                        'expires_at' => $expiresAt,
                    ]);

                    $batch->forceFill([
                        'quantity_available' => $newBalance,
                        'status' => $newBalance <= 0 ? 'depleted' : 'active',
                    ])->save();

                    StockMovement::create([
                        'pharmacy_id' => $lockedOrder->pharmacy_id,
                        'pharmacy_branch_id' => $lockedOrder->pharmacy_branch_id,
                        'pharmacy_medicine_id' => $item->pharmacy_medicine_id,
                        'medicine_batch_id' => $batch->id,
                        'created_by_user_id' => $actor?->id,
                        'movement_type' => 'marketplace_reservation',
                        'direction' => 'out',
                        'quantity' => $reserved,
                        'unit_cost' => $batch->unit_cost,
                        'balance_after' => $newBalance,
                        'reference_type' => MarketplaceStockReservation::class,
                        'reference_id' => $reservation->id,
                        'occurred_at' => now(),
                        'notes' => "Reserved for marketplace order {$lockedOrder->order_number}",
                    ]);

                    $remaining = round($remaining - $reserved, 3);
                }

                $affectedListingIds[] = $item->pharmacy_medicine_id;
            }

            $lockedOrder->forceFill([
                'status' => MarketplaceOrder::STATUS_AWAITING_PAYMENT,
                'reservation_expires_at' => $expiresAt,
            ])->save();

            app(RecordMarketplaceOrderEvent::class)->handle(
                order: $lockedOrder,
                eventType: 'stock_reserved',
                title: 'Stock reserved',
                description: 'FEFO stock was reserved while the client completes wallet payment.',
                metadata: ['expires_at' => $expiresAt->toIso8601String()],
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
