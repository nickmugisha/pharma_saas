<?php

namespace App\Filament\Pharmacy\Resources\BranchMedicineSettings\Pages;

use App\Filament\Pharmacy\Resources\BranchMedicineSettings\BranchMedicineSettingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBranchMedicineSettings extends ListRecords
{
    protected static string $resource = BranchMedicineSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
