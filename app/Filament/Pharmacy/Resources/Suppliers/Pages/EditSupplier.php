<?php

namespace App\Filament\Pharmacy\Resources\Suppliers\Pages;

use App\Filament\Pharmacy\Resources\Suppliers\SupplierResource;
use Filament\Resources\Pages\EditRecord;

class EditSupplier extends EditRecord
{
    protected static string $resource = SupplierResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        abort_unless(
            (int) $this->record->pharmacy_id
                === (int) auth()->user()?->pharmacy_id,
            403,
        );

        $data['currency'] = 'BIF';

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}