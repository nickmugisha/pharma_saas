<?php

namespace App\Filament\Pharmacy\Resources\PharmacyMedicines\Pages;

use App\Filament\Pharmacy\Resources\PharmacyMedicines\PharmacyMedicineResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPharmacyMedicines extends ListRecords
{
    protected static string $resource = PharmacyMedicineResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
