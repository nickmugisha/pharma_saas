<?php

namespace App\Filament\Pharmacy\Resources\PharmacyBranches\Pages;

use App\Filament\Pharmacy\Resources\PharmacyBranches\PharmacyBranchResource;
use App\Models\PharmacyBranch;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;

class CreatePharmacyBranch extends CreateRecord
{
    protected static string $resource = PharmacyBranchResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $pharmacyId = auth()->user()?->pharmacy_id;

        abort_unless($pharmacyId, 403);

        $data['pharmacy_id'] = $pharmacyId;

        $hasExistingBranch = PharmacyBranch::query()
            ->where('pharmacy_id', $pharmacyId)
            ->exists();

        if (! $hasExistingBranch) {
            $data['is_main'] = true;
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return;
        }

        if (
            blank($user->pharmacy_branch_id)
            && (int) $user->pharmacy_id
                === (int) $this->record->pharmacy_id
        ) {
            $user->forceFill([
                'pharmacy_branch_id' => $this->record->id,
            ])->save();
        }
    }
}
