<?php

namespace App\Actions\Marketplace;

use App\Models\MarketplaceOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReviewMarketplaceOrder
{
    public function approve(User $actor, MarketplaceOrder $order): MarketplaceOrder
    {
        $this->authorize($actor, $order);

        return DB::transaction(function () use ($actor, $order): MarketplaceOrder {
            $lockedOrder = MarketplaceOrder::query()
                ->with(['items.clientPrescription'])
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedOrder->status !== MarketplaceOrder::STATUS_AWAITING_REVIEW) {
                throw ValidationException::withMessages([
                    'order' => 'This order is not waiting for prescription review.',
                ]);
            }

            foreach ($lockedOrder->items as $item) {
                if ($item->online_sale_mode === 'prescription_required') {
                    $prescription = $item->clientPrescription;

                    if (! $prescription) {
                        throw ValidationException::withMessages([
                            'prescription' => "{$item->medicine_name} requires a prescription document.",
                        ]);
                    }

                    if (
                        $prescription->valid_until
                        && $prescription->valid_until->isBefore(today())
                    ) {
                        throw ValidationException::withMessages([
                            'prescription' => "The prescription for {$item->medicine_name} has expired.",
                        ]);
                    }
                }

                if ($item->prescription_review_status === 'pending') {
                    $item->forceFill([
                        'prescription_review_status' => 'approved',
                        'reviewed_by_user_id' => $actor->id,
                        'reviewed_at' => now(),
                        'rejection_reason' => null,
                    ])->save();
                }
            }

            $lockedOrder->forceFill([
                'prescription_status' => 'approved',
            ])->save();

            app(RecordMarketplaceOrderEvent::class)->handle(
                order: $lockedOrder,
                eventType: 'prescription_approved',
                title: 'Prescription review approved',
                description: 'The pharmacy approved the order for stock reservation and wallet payment.',
                actor: $actor,
            );

            return app(ReserveMarketplaceOrderStock::class)->handle(
                order: $lockedOrder,
                actor: $actor,
            );
        }, attempts: 5);
    }

    public function reject(
        User $actor,
        MarketplaceOrder $order,
        string $reason,
    ): MarketplaceOrder {
        $this->authorize($actor, $order);
        $reason = trim($reason);

        if (mb_strlen($reason) < 5) {
            throw ValidationException::withMessages([
                'reason' => 'Provide a clear rejection reason.',
            ]);
        }

        return DB::transaction(function () use ($actor, $order, $reason): MarketplaceOrder {
            $lockedOrder = MarketplaceOrder::query()
                ->with('items')
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedOrder->status !== MarketplaceOrder::STATUS_AWAITING_REVIEW) {
                throw ValidationException::withMessages([
                    'order' => 'This order is not waiting for prescription review.',
                ]);
            }

            foreach ($lockedOrder->items as $item) {
                if ($item->prescription_review_status === 'pending') {
                    $item->forceFill([
                        'prescription_review_status' => 'rejected',
                        'reviewed_by_user_id' => $actor->id,
                        'reviewed_at' => now(),
                        'rejection_reason' => $reason,
                    ])->save();
                }
            }

            $lockedOrder->forceFill([
                'status' => MarketplaceOrder::STATUS_CANCELLED,
                'prescription_status' => 'rejected',
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
            ])->save();

            app(RecordMarketplaceOrderEvent::class)->handle(
                order: $lockedOrder,
                eventType: 'prescription_rejected',
                title: 'Prescription review rejected',
                description: $reason,
                actor: $actor,
            );

            return $lockedOrder->fresh(['items', 'events.actorUser']);
        }, attempts: 5);
    }

    private function authorize(User $actor, MarketplaceOrder $order): void
    {
        abort_unless(
            $actor->is_active
            && $actor->can('marketplace.prescriptions.review')
            && (int) $actor->pharmacy_id === (int) $order->pharmacy_id
            && (
                $actor->hasRole('pharmacy_owner')
                || (int) $actor->pharmacy_branch_id === (int) $order->pharmacy_branch_id
            ),
            403,
        );
    }
}
