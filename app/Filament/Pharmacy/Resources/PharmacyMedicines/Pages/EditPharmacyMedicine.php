<?php

namespace App\Filament\Pharmacy\Resources\PharmacyMedicines\Pages;

use App\Filament\Pharmacy\Resources\PharmacyMedicines\PharmacyMedicineResource;
use App\Models\PharmacyMedicine;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditPharmacyMedicine extends EditRecord
{
    protected static string $resource = PharmacyMedicineResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $user = auth()->user();

        abort_unless(
            $user?->pharmacy_id
            && (int) $this->record->pharmacy_id
                === (int) $user->pharmacy_id,
            403,
        );

        if (
            filled($data['internal_sku'] ?? null)
            && PharmacyMedicine::query()
                ->where('pharmacy_id', $user->pharmacy_id)
                ->where('internal_sku', $data['internal_sku'])
                ->whereKeyNot($this->record->getKey())
                ->exists()
        ) {
            throw ValidationException::withMessages([
                'data.internal_sku' =>
                    'This internal SKU is already used by your pharmacy.',
            ]);
        }

        $data['medicine_id'] = $this->record->medicine_id;
        $data['currency'] = 'BIF';

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}