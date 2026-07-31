<?php

namespace App\Filament\Pharmacy\Resources\SupplierInvoices\Pages;

use App\Filament\Pharmacy\Resources\SupplierInvoices\SupplierInvoiceResource;
use Filament\Resources\Pages\EditRecord;

class EditSupplierInvoice extends EditRecord
{
    protected static string $resource = SupplierInvoiceResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        abort_unless(
            (int) $this->record->pharmacy_id
                === (int) auth()->user()?->pharmacy_id,
            403,
        );

        $data['purchase_order_id'] =
            $this->record->purchase_order_id;

        $data['invoice_number'] =
            $this->record->invoice_number;

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}