<?php

namespace App\Filament\Pharmacy\Resources\BranchMedicineSettings\Pages;

use App\Actions\Stock\SyncInventoryAlerts;
use App\Filament\Pharmacy\Resources\BranchMedicineSettings\BranchMedicineSettingResource;
use Filament\Resources\Pages\EditRecord;

class EditBranchMedicineSetting extends EditRecord
{
    protected static string $resource =
        BranchMedicineSettingResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        abort_unless(
            (int) $this->record->pharmacy_id
                === (int) auth()->user()?->pharmacy_id,
            403,
        );

        $data['pharmacy_id'] = $this->record->pharmacy_id;
        $data['pharmacy_branch_id'] =
            $this->record->pharmacy_branch_id;
        $data['pharmacy_medicine_id'] =
            $this->record->pharmacy_medicine_id;

        return $data;
    }

    protected function afterSave(): void
    {
        app(SyncInventoryAlerts::class)->handle(
            pharmacyId: $this->record->pharmacy_id,
            branchId: $this->record->pharmacy_branch_id,
            pharmacyMedicineId:
                $this->record->pharmacy_medicine_id,
        );
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}