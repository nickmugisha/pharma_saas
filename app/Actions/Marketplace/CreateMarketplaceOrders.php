<?php

namespace App\Actions\Marketplace;

use App\Models\ClientAddress;
use App\Models\ClientPrescription;
use App\Models\MarketplaceCart;
use App\Models\MarketplaceOrder;
use App\Models\MarketplaceOrderItem;
use App\Models\User;
use App\Services\MarketplaceCatalogue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateMarketplaceOrders
{
    public function __construct(
        private readonly MarketplaceCatalogue $catalogue,
    ) {
    }

    public function handle(
        User $client,
        array $prescriptionSelections = [],
        ?int $addressId = null,
        ?string $notes = null,
    ): Collection {
        $this->authorizeClient($client);

        $wallet = $client->wallet;

        if (! $wallet || $wallet->status !== 'active') {
            throw ValidationException::withMessages([
                'wallet' => 'An active client wallet is required before checkout.',
            ]);
        }

        $address = $addressId
            ? ClientAddress::query()
                ->whereKey($addressId)
                ->where('user_id', $client->id)
                ->firstOrFail()
            : null;

        return DB::transaction(function () use (
            $client,
            $wallet,
            $prescriptionSelections,
            $address,
            $notes,
        ): Collection {
            $cart = MarketplaceCart::query()
                ->with(['items.pharmacyMedicine.medicine.dosageForm'])
                ->where('user_id', $client->id)
                ->where('status', 'active')
                ->lockForUpdate()
                ->first();

            if (! $cart || $cart->items->isEmpty()) {
                throw ValidationException::withMessages([
                    'cart' => 'Your cart is empty.',
                ]);
            }

            $prepared = $cart->items->map(function ($item) use (
                $client,
                $prescriptionSelections,
            ): array {
                $offer = $this->catalogue->findOffer(
                    $item->pharmacy_medicine_id,
                    $item->pharmacy_branch_id,
                );

                if (! $offer) {
                    throw ValidationException::withMessages([
                        'cart' => 'One of the selected pharmacy offers is no longer available.',
                    ]);
                }

                if ($offer['online_sale_mode'] === 'in_store_only') {
                    throw ValidationException::withMessages([
                        'cart' => sprintf(
                            '%s can only be obtained in person.',
                            $item->pharmacyMedicine?->medicine?->brand_name ?? 'A selected medicine',
                        ),
                    ]);
                }

                $quantity = round((float) $item->quantity, 3);

                if ($quantity > (float) $offer['max_order_quantity'] + 0.0005) {
                    throw ValidationException::withMessages([
                        'cart' => sprintf(
                            'The available quantity for %s changed. Please review your cart.',
                            $item->pharmacyMedicine?->medicine?->brand_name ?? 'a medicine',
                        ),
                    ]);
                }

                $prescription = null;
                $selection = $prescriptionSelections[$item->id] ?? null;

                if (filled($selection)) {
                    $prescription = ClientPrescription::query()
                        ->whereKey((int) $selection)
                        ->where('user_id', $client->id)
                        ->firstOrFail();

                    if (
                        $prescription->valid_until
                        && $prescription->valid_until->isBefore(today())
                    ) {
                        throw ValidationException::withMessages([
                            "prescriptions.{$item->id}" => 'The selected prescription has expired.',
                        ]);
                    }
                }

                if (
                    $offer['online_sale_mode'] === 'prescription_required'
                    && ! $prescription
                ) {
                    throw ValidationException::withMessages([
                        "prescriptions.{$item->id}" => 'Select or upload a valid prescription for this medicine.',
                    ]);
                }

                return [
                    'cart_item' => $item,
                    'offer' => $offer,
                    'prescription' => $prescription,
                ];
            });

            $groups = $prepared->groupBy(fn (array $row): string => implode(':', [
                $row['offer']['pharmacy_id'],
                $row['offer']['branch_id'],
                $row['cart_item']->fulfillment_method,
            ]));

            $requiredWalletBalance = round((float) $groups->sum(function ($group): float {
                $subtotal = (float) $group->sum(function (array $row): float {
                    return round(
                        (float) $row['cart_item']->quantity
                        * (float) $row['offer']['price'],
                        2,
                    );
                });

                $first = $group->first();
                $deliveryFee = $first['cart_item']->fulfillment_method === 'delivery'
                    ? (float) $group->max(fn (array $row): float =>
                        (float) $row['offer']['delivery_fee'])
                    : 0.0;

                return round($subtotal + $deliveryFee, 2);
            }), 2);

            if ((float) $wallet->available_balance + 0.005 < $requiredWalletBalance) {
                throw ValidationException::withMessages([
                    'wallet' => sprintf(
                        'Your wallet needs %s BIF for this checkout, but only %s BIF is available.',
                        number_format($requiredWalletBalance, 2),
                        number_format((float) $wallet->available_balance, 2),
                    ),
                ]);
            }

            $orders = collect();

            foreach ($groups as $group) {
                $first = $group->first();
                $fulfillment = $first['cart_item']->fulfillment_method;

                if ($fulfillment === 'delivery' && ! $address) {
                    throw ValidationException::withMessages([
                        'client_address_id' => 'Select a delivery address.',
                    ]);
                }

                $subtotal = round((float) $group->sum(function (array $row): float {
                    return round(
                        (float) $row['cart_item']->quantity
                        * (float) $row['offer']['price'],
                        2,
                    );
                }), 2);

                $deliveryFee = $fulfillment === 'delivery'
                    ? round((float) $group->max(fn (array $row): float =>
                        (float) $row['offer']['delivery_fee']), 2)
                    : 0.0;

                $needsReview = $group->contains(fn (array $row): bool =>
                    in_array($row['offer']['online_sale_mode'], [
                        'prescription_required',
                        'pharmacist_review',
                    ], true));

                $order = MarketplaceOrder::create([
                    'user_id' => $client->id,
                    'client_wallet_id' => $wallet->id,
                    'pharmacy_id' => $first['offer']['pharmacy_id'],
                    'pharmacy_branch_id' => $first['offer']['branch_id'],
                    'status' => MarketplaceOrder::STATUS_DRAFT,
                    'prescription_status' => $needsReview ? 'pending_review' : 'not_required',
                    'fulfillment_method' => $fulfillment,
                    'client_name' => $client->name,
                    'client_email' => $client->email,
                    'client_phone' => $client->clientProfile?->phone,
                    'address_label' => $address?->label,
                    'address_line_1' => $address?->address_line_1,
                    'address_line_2' => $address?->address_line_2,
                    'city' => $address?->city,
                    'province' => $address?->province,
                    'country' => $address?->country,
                    'delivery_instructions' => $address?->delivery_instructions,
                    'subtotal' => $subtotal,
                    'delivery_fee' => $deliveryFee,
                    'grand_total' => round($subtotal + $deliveryFee, 2),
                    'currency' => $first['offer']['currency'],
                    'notes' => filled($notes) ? trim($notes) : null,
                    'placed_at' => now(),
                ]);

                foreach ($group as $row) {
                    $medicine = $row['cart_item']->pharmacyMedicine->medicine;
                    $quantity = round((float) $row['cart_item']->quantity, 3);
                    $unitPrice = round((float) $row['offer']['price'], 2);
                    $requiresReview = in_array($row['offer']['online_sale_mode'], [
                        'prescription_required',
                        'pharmacist_review',
                    ], true);

                    MarketplaceOrderItem::create([
                        'marketplace_order_id' => $order->id,
                        'medicine_id' => $medicine->id,
                        'pharmacy_medicine_id' => $row['offer']['pharmacy_medicine_id'],
                        'marketplace_offer_id' => $row['offer']['offer_id'],
                        'client_prescription_id' => $row['prescription']?->id,
                        'medicine_name' => $medicine->brand_name
                            ?? $medicine->generic_name
                            ?? 'Medicine',
                        'strength' => $medicine->strength,
                        'dosage_form' => $medicine->dosageForm?->name,
                        'sku' => $row['cart_item']->pharmacyMedicine->internal_sku,
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                        'line_total' => round($quantity * $unitPrice, 2),
                        'online_sale_mode' => $row['offer']['online_sale_mode'],
                        'prescription_review_status' => $requiresReview
                            ? 'pending'
                            : 'not_required',
                    ]);
                }

                app(RecordMarketplaceOrderEvent::class)->handle(
                    order: $order,
                    eventType: 'order_created',
                    title: 'Marketplace order created',
                    description: $needsReview
                        ? 'The order is waiting for pharmacy prescription review.'
                        : 'The order is ready for temporary stock reservation.',
                    actor: $client,
                );

                if ($needsReview) {
                    $order->forceFill([
                        'status' => MarketplaceOrder::STATUS_AWAITING_REVIEW,
                    ])->save();
                } else {
                    $order = app(ReserveMarketplaceOrderStock::class)->handle(
                        order: $order,
                        actor: $client,
                    );
                }

                $orders->push($order->fresh([
                    'pharmacy',
                    'branch',
                    'items.clientPrescription',
                    'stockReservations',
                    'events',
                ]));
            }

            $cart->items()->delete();
            $cart->forceFill([
                'status' => 'converted',
                'converted_at' => now(),
            ])->save();

            return $orders;
        }, attempts: 5);
    }

    private function authorizeClient(User $client): void
    {
        abort_unless(
            $client->is_active
            && $client->hasRole('client')
            && $client->clientProfile?->status === 'active',
            403,
        );
    }
}
