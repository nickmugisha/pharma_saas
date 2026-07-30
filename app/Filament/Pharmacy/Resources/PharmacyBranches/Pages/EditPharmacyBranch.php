<?php

namespace App\Filament\Pharmacy\Resources\PharmacyBranches\Pages;

use App\Filament\Pharmacy\Resources\PharmacyBranches\PharmacyBranchResource;
use Filament\Resources\Pages\EditRecord;

class EditPharmacyBranch extends EditRecord
{
    protected static string $resource = PharmacyBranchResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}