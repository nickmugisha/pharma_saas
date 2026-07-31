<?php

namespace App\Filament\Pharmacy\Resources\Suppliers\Pages;

use App\Filament\Pharmacy\Resources\Suppliers\SupplierResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSupplier extends CreateRecord
{
    protected static string $resource = SupplierResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = auth()->user();

        abort_unless($user?->pharmacy_id, 403);

        $data['pharmacy_id'] = $user->pharmacy_id;
        $data['created_by_user_id'] = $user->id;
        $data['currency'] = 'BIF';

        return $data;
    }
}