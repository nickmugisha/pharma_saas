<?php

namespace App\Filament\Pharmacy\Resources\MarketplaceOffers\Pages;

use App\Filament\Pharmacy\Resources\MarketplaceOffers\MarketplaceOfferResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMarketplaceOffers extends ListRecords
{
    protected static string $resource = MarketplaceOfferResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
