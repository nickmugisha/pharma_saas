<?php

namespace App\Filament\Pharmacy\Resources\PurchaseReceipts\Pages;

use App\Filament\Pharmacy\Resources\PurchaseReceipts\PurchaseReceiptResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPurchaseReceipt extends EditRecord
{
    protected static string $resource = PurchaseReceiptResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
