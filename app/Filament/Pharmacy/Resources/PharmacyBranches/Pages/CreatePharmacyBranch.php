<?php

namespace App\Filament\Pharmacy\Resources\PharmacyBranches\Pages;

use App\Filament\Pharmacy\Resources\PharmacyBranches\PharmacyBranchResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePharmacyBranch extends CreateRecord
{
    protected static string $resource = PharmacyBranchResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $pharmacyId = auth()->user()?->pharmacy_id;

        abort_unless($pharmacyId, 403);

        $data['pharmacy_id'] = $pharmacyId;

        return $data;
    }
}