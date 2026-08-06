<?php

namespace App\Filament\Pharmacy\Resources\MarketplaceOffers\Pages;

use App\Filament\Pharmacy\Resources\MarketplaceOffers\MarketplaceOfferResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditMarketplaceOffer extends EditRecord
{
    protected static string $resource = MarketplaceOfferResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (
            ! (bool) ($data['pickup_enabled'] ?? false)
            && ! (bool) ($data['delivery_enabled'] ?? false)
        ) {
            throw ValidationException::withMessages([
                'data.pickup_enabled' =>
                    'Enable pickup, delivery, or both for an active marketplace offer.',
            ]);
        }

        abort_unless(
            (int) $this->record->pharmacy_id === (int) auth()->user()?->pharmacy_id,
            403,
        );

        $data['pharmacy_id'] = $this->record->pharmacy_id;
        $data['pharmacy_branch_id'] = $this->record->pharmacy_branch_id;
        $data['pharmacy_medicine_id'] = $this->record->pharmacy_medicine_id;
        $data['currency'] = 'BIF';

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
