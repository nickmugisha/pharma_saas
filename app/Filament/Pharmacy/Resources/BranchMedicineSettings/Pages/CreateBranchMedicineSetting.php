<?php

namespace App\Filament\Pharmacy\Resources\BranchMedicineSettings\Pages;

use App\Actions\Stock\SyncInventoryAlerts;
use App\Filament\Pharmacy\Resources\BranchMedicineSettings\BranchMedicineSettingResource;
use App\Models\BranchMedicineSetting;
use App\Models\PharmacyBranch;
use App\Models\PharmacyMedicine;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateBranchMedicineSetting extends CreateRecord
{
    protected static string $resource =
        BranchMedicineSettingResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $pharmacyId = auth()->user()?->pharmacy_id;

        abort_unless($pharmacyId, 403);

        PharmacyBranch::query()
            ->whereKey($data['pharmacy_branch_id'])
            ->where('pharmacy_id', $pharmacyId)
            ->where('status', 'active')
            ->firstOrFail();

        PharmacyMedicine::query()
            ->whereKey($data['pharmacy_medicine_id'])
            ->where('pharmacy_id', $pharmacyId)
            ->where('status', 'active')
            ->firstOrFail();

        $alreadyExists = BranchMedicineSetting::query()
            ->where(
                'pharmacy_branch_id',
                $data['pharmacy_branch_id'],
            )
            ->where(
                'pharmacy_medicine_id',
                $data['pharmacy_medicine_id'],
            )
            ->exists();

        if ($alreadyExists) {
            throw ValidationException::withMessages([
                'data.pharmacy_medicine_id' =>
                    'This medicine already has settings for the selected branch.',
            ]);
        }

        $data['pharmacy_id'] = $pharmacyId;

        return $data;
    }

    protected function afterCreate(): void
    {
        app(SyncInventoryAlerts::class)->handle(
            pharmacyId: $this->record->pharmacy_id,
            branchId: $this->record->pharmacy_branch_id,
            pharmacyMedicineId:
                $this->record->pharmacy_medicine_id,
        );
    }
}