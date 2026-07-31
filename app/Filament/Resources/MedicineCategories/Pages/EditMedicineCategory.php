<?php

namespace App\Filament\Resources\MedicineCategories\Pages;

use App\Filament\Resources\MedicineCategories\MedicineCategoryResource;
use Filament\Resources\Pages\EditRecord;

class EditMedicineCategory extends EditRecord
{
    protected static string $resource = MedicineCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}