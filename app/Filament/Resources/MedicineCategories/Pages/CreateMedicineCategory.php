<?php

namespace App\Filament\Resources\MedicineCategories\Pages;

use App\Filament\Resources\MedicineCategories\MedicineCategoryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMedicineCategory extends CreateRecord
{
    protected static string $resource = MedicineCategoryResource::class;
}
