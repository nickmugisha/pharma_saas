<?php

namespace App\Filament\Pharmacy\Resources\PurchaseReceipts\Pages;

use App\Filament\Pharmacy\Resources\PurchaseReceipts\PurchaseReceiptResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPurchaseReceipts extends ListRecords
{
    protected static string $resource = PurchaseReceiptResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
