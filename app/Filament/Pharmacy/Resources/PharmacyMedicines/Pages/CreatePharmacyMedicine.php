<?php

namespace App\Filament\Pharmacy\Resources\PharmacyMedicines\Pages;

use App\Filament\Pharmacy\Resources\PharmacyMedicines\PharmacyMedicineResource;
use App\Models\Medicine;
use App\Models\PharmacyMedicine;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreatePharmacyMedicine extends CreateRecord
{
    protected static string $resource = PharmacyMedicineResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = auth()->user();

        abort_unless($user?->pharmacy_id, 403);

        Medicine::query()
            ->whereKey($data['medicine_id'])
            ->where('approval_status', 'approved')
            ->where('is_active', true)
            ->firstOrFail();

        $alreadyExists = PharmacyMedicine::query()
            ->where('pharmacy_id', $user->pharmacy_id)
            ->where('medicine_id', $data['medicine_id'])
            ->exists();

        if ($alreadyExists) {
            throw ValidationException::withMessages([
                'data.medicine_id' =>
                    'This medicine is already registered in your pharmacy.',
            ]);
        }

        if (
            filled($data['internal_sku'] ?? null)
            && PharmacyMedicine::query()
                ->where('pharmacy_id', $user->pharmacy_id)
                ->where('internal_sku', $data['internal_sku'])
                ->exists()
        ) {
            throw ValidationException::withMessages([
                'data.internal_sku' =>
                    'This internal SKU is already used by your pharmacy.',
            ]);
        }

        $data['pharmacy_id'] = $user->pharmacy_id;
        $data['created_by_user_id'] = $user->id;
        $data['currency'] = 'BIF';

        return $data;
    }
}