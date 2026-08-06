<?php

namespace App\Actions\Marketplace;

use App\Models\MarketplaceCart;
use App\Models\MarketplaceCartItem;
use App\Models\User;
use App\Services\MarketplaceCatalogue;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManageMarketplaceCart
{
    public function __construct(
        private readonly MarketplaceCatalogue $catalogue,
    ) {
    }

    public function current(User $user): MarketplaceCart
    {
        $this->authorizeClient($user);

        return MarketplaceCart::query()->firstOrCreate(
            ['user_id' => $user->id, 'status' => 'active'],
        );
    }

    public function add(
        User $user,
        int $pharmacyMedicineId,
        int $branchId,
        float $quantity,
        string $fulfillmentMethod,
    ): MarketplaceCartItem {
        $this->authorizeClient($user);
        $quantity = round($quantity, 3);

        if ($quantity <= 0) {
            throw ValidationException::withMessages([
                'quantity' => 'Quantity must be greater than zero.',
            ]);
        }

        $offer = $this->catalogue->findOffer($pharmacyMedicineId, $branchId);

        if (! $offer) {
            throw ValidationException::withMessages([
                'offer' => 'This pharmacy offer is no longer available.',
            ]);
        }

        if ($offer['online_sale_mode'] === 'in_store_only') {
            throw ValidationException::withMessages([
                'offer' => 'This medicine can only be obtained in person at the pharmacy.',
            ]);
        }

        $this->validateFulfillment($offer, $fulfillmentMethod);

        return DB::transaction(function () use (
            $user,
            $offer,
            $quantity,
            $fulfillmentMethod,
        ): MarketplaceCartItem {
            $cart = MarketplaceCart::query()
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->lockForUpdate()
                ->first();

            if (! $cart) {
                $cart = MarketplaceCart::create([
                    'user_id' => $user->id,
                    'status' => 'active',
                ]);
            }

            $item = MarketplaceCartItem::query()
                ->where('marketplace_cart_id', $cart->id)
                ->where('pharmacy_branch_id', $offer['branch_id'])
                ->where('pharmacy_medicine_id', $offer['pharmacy_medicine_id'])
                ->where('fulfillment_method', $fulfillmentMethod)
                ->lockForUpdate()
                ->first();

            $newQuantity = round(
                (float) ($item?->quantity ?? 0) + $quantity,
                3,
            );

            if ($newQuantity > (float) $offer['max_order_quantity'] + 0.0005) {
                throw ValidationException::withMessages([
                    'quantity' => sprintf(
                        'Only %s unit(s) can currently be reserved from this offer.',
                        number_format((float) $offer['max_order_quantity'], 3),
                    ),
                ]);
            }

            $payload = [
                'marketplace_cart_id' => $cart->id,
                'pharmacy_id' => $offer['pharmacy_id'],
                'pharmacy_branch_id' => $offer['branch_id'],
                'pharmacy_medicine_id' => $offer['pharmacy_medicine_id'],
                'marketplace_offer_id' => $offer['offer_id'],
                'quantity' => $newQuantity,
                'unit_price_snapshot' => $offer['price'],
                'currency' => $offer['currency'],
                'fulfillment_method' => $fulfillmentMethod,
                'online_sale_mode' => $offer['online_sale_mode'],
            ];

            if ($item) {
                $item->update($payload);
                return $item->fresh();
            }

            return MarketplaceCartItem::create($payload);
        });
    }

    public function update(
        User $user,
        MarketplaceCartItem $item,
        float $quantity,
        string $fulfillmentMethod,
    ): MarketplaceCartItem {
        $this->authorizeItem($user, $item);
        $quantity = round($quantity, 3);

        if ($quantity <= 0) {
            throw ValidationException::withMessages([
                'quantity' => 'Quantity must be greater than zero.',
            ]);
        }

        $offer = $this->catalogue->findOffer(
            $item->pharmacy_medicine_id,
            $item->pharmacy_branch_id,
        );

        if (! $offer) {
            throw ValidationException::withMessages([
                'offer' => 'This pharmacy offer is no longer available.',
            ]);
        }

        $this->validateFulfillment($offer, $fulfillmentMethod);

        if ($quantity > (float) $offer['max_order_quantity'] + 0.0005) {
            throw ValidationException::withMessages([
                'quantity' => sprintf(
                    'Only %s unit(s) are currently available.',
                    number_format((float) $offer['max_order_quantity'], 3),
                ),
            ]);
        }

        $item->update([
            'quantity' => $quantity,
            'unit_price_snapshot' => $offer['price'],
            'marketplace_offer_id' => $offer['offer_id'],
            'currency' => $offer['currency'],
            'fulfillment_method' => $fulfillmentMethod,
            'online_sale_mode' => $offer['online_sale_mode'],
        ]);

        return $item->fresh();
    }

    public function remove(User $user, MarketplaceCartItem $item): void
    {
        $this->authorizeItem($user, $item);
        $item->delete();
    }

    private function validateFulfillment(array $offer, string $method): void
    {
        if (! in_array($method, ['pickup', 'delivery'], true)) {
            throw ValidationException::withMessages([
                'fulfillment_method' => 'Select pickup or delivery.',
            ]);
        }

        if ($method === 'pickup' && ! $offer['pickup_enabled']) {
            throw ValidationException::withMessages([
                'fulfillment_method' => 'Pickup is not available for this offer.',
            ]);
        }

        if ($method === 'delivery' && ! $offer['delivery_enabled']) {
            throw ValidationException::withMessages([
                'fulfillment_method' => 'Delivery is not available for this offer.',
            ]);
        }
    }

    private function authorizeClient(User $user): void
    {
        abort_unless(
            $user->is_active
            && $user->hasRole('client')
            && $user->clientProfile?->status === 'active',
            403,
        );
    }

    private function authorizeItem(User $user, MarketplaceCartItem $item): void
    {
        $this->authorizeClient($user);
        abort_unless($item->cart?->user_id === $user->id, 404);
    }
}
