<?php

namespace App\Filament\Pharmacy\Resources\PharmacyBranches\Pages;

use App\Filament\Pharmacy\Resources\PharmacyBranches\PharmacyBranchResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPharmacyBranches extends ListRecords
{
    protected static string $resource = PharmacyBranchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
