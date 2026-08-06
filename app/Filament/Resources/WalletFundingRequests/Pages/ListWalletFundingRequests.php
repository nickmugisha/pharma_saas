<?php

namespace App\Filament\Resources\WalletFundingRequests\Pages;

use App\Filament\Resources\WalletFundingRequests\WalletFundingRequestResource;
use Filament\Resources\Pages\ListRecords;

class ListWalletFundingRequests extends ListRecords
{
    protected static string $resource = WalletFundingRequestResource::class;
}
