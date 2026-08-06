<?php

namespace App\Filament\Pharmacy\Resources\MarketplaceOrders\Pages;

use App\Filament\Pharmacy\Resources\MarketplaceOrders\MarketplaceOrderResource;
use Filament\Resources\Pages\ListRecords;

class ListMarketplaceOrders extends ListRecords
{
    protected static string $resource = MarketplaceOrderResource::class;
}
