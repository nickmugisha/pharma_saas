<?php

namespace App\Filament\Pharmacy\Resources\SupplierPayments\Pages;

use App\Filament\Pharmacy\Resources\SupplierPayments\SupplierPaymentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSupplierPayments extends ListRecords
{
    protected static string $resource = SupplierPaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
