<?php

namespace App\Filament\Pharmacy\Resources\MarketplaceOffers\Pages;

use App\Filament\Pharmacy\Resources\MarketplaceOffers\MarketplaceOfferResource;
use App\Models\MarketplaceOffer;
use App\Models\PharmacyBranch;
use App\Models\PharmacyMedicine;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateMarketplaceOffer extends CreateRecord
{
    protected static string $resource = MarketplaceOfferResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = auth()->user();
        abort_unless($user?->pharmacy_id, 403);

        if (
            ! (bool) ($data['pickup_enabled'] ?? false)
            && ! (bool) ($data['delivery_enabled'] ?? false)
        ) {
            throw ValidationException::withMessages([
                'data.pickup_enabled' =>
                    'Enable pickup, delivery, or both for an active marketplace offer.',
            ]);
        }


        PharmacyBranch::query()
            ->whereKey($data['pharmacy_branch_id'])
            ->where('pharmacy_id', $user->pharmacy_id)
            ->when(
                ! $user->hasRole('pharmacy_owner'),
                fn ($query) => $query->whereKey(
                    $user->pharmacy_branch_id ?? 0,
                ),
            )
            ->where('status', 'active')
            ->firstOrFail();

        PharmacyMedicine::query()
            ->whereKey($data['pharmacy_medicine_id'])
            ->where('pharmacy_id', $user->pharmacy_id)
            ->where('status', 'active')
            ->where('is_visible_online', true)
            ->firstOrFail();

        if (MarketplaceOffer::query()
            ->where('pharmacy_branch_id', $data['pharmacy_branch_id'])
            ->where('pharmacy_medicine_id', $data['pharmacy_medicine_id'])
            ->exists()) {
            throw ValidationException::withMessages([
                'data.pharmacy_medicine_id' =>
                    'This branch already has a marketplace offer for the selected medicine.',
            ]);
        }

        $data['pharmacy_id'] = $user->pharmacy_id;
        $data['currency'] = 'BIF';

        return $data;
    }
}
