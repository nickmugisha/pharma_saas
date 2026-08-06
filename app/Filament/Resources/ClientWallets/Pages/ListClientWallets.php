<?php

namespace App\Filament\Resources\ClientWallets\Pages;

use App\Filament\Resources\ClientWallets\ClientWalletResource;
use Filament\Resources\Pages\ListRecords;

class ListClientWallets extends ListRecords
{
    protected static string $resource = ClientWalletResource::class;
}
