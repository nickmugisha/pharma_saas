<?php

namespace App\Filament\Pharmacy\Resources\PurchaseOrders\Pages;

use App\Filament\Pharmacy\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Models\PharmacyBranch;
use App\Models\Supplier;
use Filament\Resources\Pages\CreateRecord;

class CreatePurchaseOrder extends CreateRecord
{
    protected static string $resource = PurchaseOrderResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = auth()->user();
        $pharmacyId = $user?->pharmacy_id;

        abort_unless($pharmacyId, 403);

        Supplier::query()
            ->whereKey($data['supplier_id'])
            ->where('pharmacy_id', $pharmacyId)
            ->where('status', 'active')
            ->firstOrFail();

        PharmacyBranch::query()
            ->whereKey($data['pharmacy_branch_id'])
            ->where('pharmacy_id', $pharmacyId)
            ->where('status', 'active')
            ->firstOrFail();

        $data['pharmacy_id'] = $pharmacyId;
        $data['created_by_user_id'] = $user->id;
        $data['currency'] = 'BIF';
        $data['status'] = 'draft';

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->record->recalculateTotals();
    }
}