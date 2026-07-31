<?php

namespace App\Filament\Pharmacy\Resources\PurchaseReceipts\Pages;

use App\Filament\Pharmacy\Resources\PurchaseReceipts\PurchaseReceiptResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePurchaseReceipt extends CreateRecord
{
    protected static string $resource = PurchaseReceiptResource::class;
}
