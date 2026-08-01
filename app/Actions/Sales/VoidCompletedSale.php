<?php

namespace App\Actions\Sales;

use App\Actions\Stock\SyncInventoryAlerts;
use App\Models\MedicineBatch;
use App\Models\Sale;
use App\Models\SaleItemBatch;
use App\Models\SalePayment;
use App\Models\SaleVoid;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Actions\Prescriptions\RecordPrescriptionActivity;
use App\Models\Prescription;
use App\Models\PrescriptionDispensing;
use App\Models\PrescriptionDispensingItem;
use App\Models\PrescriptionItem;

class VoidCompletedSale
{
    public function handle(
        User $user,
        Sale|int $sale,
        string $reason,
    ): Sale {
        abort_unless(
            $user->can('sales.void'),
            403,
        );

        abort_unless(
            filled($user->pharmacy_id),
            403,
        );

        $reason = trim($reason);

        if (mb_strlen($reason) < 10) {
            throw ValidationException::withMessages([
                'reason' =>
                    'The void reason must contain at least 10 characters.',
            ]);
        }

        $saleId = $sale instanceof Sale
            ? $sale->getKey()
            : $sale;

        return DB::transaction(function () use (
            $user,
            $saleId,
            $reason,
        ): Sale {
            $lockedSale = Sale::query()
                ->whereKey($saleId)
                ->where(
                    'pharmacy_id',
                    $user->pharmacy_id,
                )
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedSale->status !== 'completed') {
                throw ValidationException::withMessages([
                    'sale' =>
                        'Only a completed sale can be voided.',
                ]);
            }

            if (
                SaleVoid::query()
                    ->where('sale_id', $lockedSale->id)
                    ->exists()
            ) {
                throw ValidationException::withMessages([
                    'sale' =>
                        'This sale has already been voided.',
                ]);
            }

            $prescriptionReversal =
    $this->lockPrescriptionReversal(
        $lockedSale,
    );

            $allocations = SaleItemBatch::query()
                ->whereHas(
                    'saleItem',
                    fn (Builder $query): Builder =>
                        $query->where(
                            'sale_id',
                            $lockedSale->id,
                        ),
                )
                ->with('saleItem')
                ->orderBy('id')
                ->get();

            $batchIds = $allocations
                ->pluck('medicine_batch_id')
                ->unique()
                ->values();

            $batches = MedicineBatch::query()
                ->whereIn('id', $batchIds)
                ->where(
                    'pharmacy_id',
                    $lockedSale->pharmacy_id,
                )
                ->where(
                    'pharmacy_branch_id',
                    $lockedSale->pharmacy_branch_id,
                )
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if (
                $allocations->isNotEmpty()
                && $batches->count() !== $batchIds->count()
            ) {
                throw ValidationException::withMessages([
                    'stock' =>
                        'One or more sale batches could not be restored.',
                ]);
            }

            $payments = SalePayment::query()
                ->where(
                    'pharmacy_id',
                    $lockedSale->pharmacy_id,
                )
                ->where('sale_id', $lockedSale->id)
                ->where('status', 'completed')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $void = SaleVoid::create([
                'pharmacy_id' =>
                    $lockedSale->pharmacy_id,

                'sale_id' =>
                    $lockedSale->id,

                'voided_by_user_id' =>
                    $user->id,

                'reason' =>
                    $reason,

                'voided_at' =>
                    now(),
            ]);

            $restoredQuantity = 0.0;
            $affectedMedicineIds = [];

            foreach ($allocations as $allocation) {
                $batch = $batches->get(
                    $allocation->medicine_batch_id,
                );

                if (! $batch) {
                    throw ValidationException::withMessages([
                        'stock' =>
                            'A sale batch could not be restored.',
                    ]);
                }

                $quantity = round(
                    (float) $allocation->quantity,
                    3,
                );

                $newBalance = round(
                    (float) $batch->quantity_available
                    + $quantity,
                    3,
                );

                $isExpired = Carbon::parse(
                    $batch->expiry_date,
                )
                    ->startOfDay()
                    ->lte(today());

                $batch->forceFill([
                    'quantity_available' => $newBalance,
                    'status' => $isExpired
                        ? 'expired'
                        : 'active',
                ])->save();

                StockMovement::create([
                    'pharmacy_id' =>
                        $lockedSale->pharmacy_id,

                    'pharmacy_branch_id' =>
                        $lockedSale->pharmacy_branch_id,

                    'pharmacy_medicine_id' =>
                        $allocation->saleItem
                            ->pharmacy_medicine_id,

                    'medicine_batch_id' =>
                        $batch->id,

                    'created_by_user_id' =>
                        $user->id,

                    'movement_type' =>
                        'sale_void',

                    'direction' =>
                        'in',

                    'quantity' =>
                        $quantity,

                    'unit_cost' =>
                        $allocation->unit_cost,

                    'balance_after' =>
                        $newBalance,

                    'reference_type' =>
                        SaleVoid::class,

                    'reference_id' =>
                        $void->id,

                    'occurred_at' =>
                        now(),

                    'notes' =>
                        "Void {$void->void_number} for sale {$lockedSale->sale_number}",
                ]);

                $restoredQuantity = round(
                    $restoredQuantity + $quantity,
                    3,
                );

                $affectedMedicineIds[] =
                    $allocation->saleItem
                        ->pharmacy_medicine_id;
            }

            $reversedPaymentAmount = 0.0;

            foreach ($payments as $payment) {
                $reversedPaymentAmount = round(
                    $reversedPaymentAmount
                    + (float) $payment->amount,
                    2,
                );

                $payment->forceFill([
                    'status' => 'voided',
                    'voided_by_user_id' => $user->id,
                    'voided_at' => now(),
                    'void_reason' => $reason,
                ])->save();
            }

            $void->forceFill([
                'restored_quantity' =>
                    $restoredQuantity,

                'reversed_payment_amount' =>
                    $reversedPaymentAmount,
            ])->save();

            $lockedSale->forceFill([
                'status' => 'voided',
                'payment_status' => 'refunded',
                'voided_by_user_id' => $user->id,
                'voided_at' => now(),
                'void_reason' => $reason,
            ])->save();

            if ($prescriptionReversal !== null) {
    $this->reversePrescriptionDispensing(
        user: $user,
        sale: $lockedSale,
        reason: $reason,
        context: $prescriptionReversal,
    );
}

            foreach (
                array_unique($affectedMedicineIds)
                as $pharmacyMedicineId
            ) {
                app(SyncInventoryAlerts::class)->handle(
                    pharmacyId:
                        $lockedSale->pharmacy_id,

                    branchId:
                        $lockedSale->pharmacy_branch_id,

                    pharmacyMedicineId:
                        (int) $pharmacyMedicineId,
                );
            }

            return $lockedSale->fresh([
                'items.batchAllocations.medicineBatch',
                'payments',
                'voidRecord.voidedByUser',
                'voidedByUser',
            ]);
        }, attempts: 5);
    }

    private function lockPrescriptionReversal(
    Sale $sale,
): ?array {
    $dispensing = PrescriptionDispensing::query()
        ->where('sale_id', $sale->id)
        ->where(
            'pharmacy_id',
            $sale->pharmacy_id,
        )
        ->lockForUpdate()
        ->first();

    if ($dispensing === null) {
        return null;
    }

    if (
        (int) $dispensing->pharmacy_branch_id
        !== (int) $sale->pharmacy_branch_id
    ) {
        throw ValidationException::withMessages([
            'prescription' =>
                'The prescription dispensing branch does not match the sale branch.',
        ]);
    }

    if (
        $dispensing->status
        !== PrescriptionDispensing::STATUS_COMPLETED
    ) {
        throw ValidationException::withMessages([
            'prescription' =>
                'This prescription dispensing has already been reversed.',
        ]);
    }

    $dispensingItems =
        PrescriptionDispensingItem::query()
            ->where(
                'prescription_dispensing_id',
                $dispensing->id,
            )
            ->orderBy('id')
            ->get();

    if ($dispensingItems->isEmpty()) {
        throw ValidationException::withMessages([
            'prescription' =>
                'The linked dispensing contains no medicine records.',
        ]);
    }

    $prescription = Prescription::query()
        ->whereKey($dispensing->prescription_id)
        ->where(
            'pharmacy_id',
            $sale->pharmacy_id,
        )
        ->where(
            'pharmacy_branch_id',
            $sale->pharmacy_branch_id,
        )
        ->lockForUpdate()
        ->first();

    if ($prescription === null) {
        throw ValidationException::withMessages([
            'prescription' =>
                'The linked prescription could not be restored.',
        ]);
    }

    $prescriptionItems = PrescriptionItem::query()
        ->where(
            'prescription_id',
            $prescription->id,
        )
        ->whereIn(
            'id',
            $dispensingItems->pluck(
                'prescription_item_id',
            ),
        )
        ->orderBy('id')
        ->lockForUpdate()
        ->get()
        ->keyBy('id');

    if (
        $prescriptionItems->count()
        !== $dispensingItems
            ->pluck('prescription_item_id')
            ->unique()
            ->count()
    ) {
        throw ValidationException::withMessages([
            'prescription' =>
                'One or more prescription items could not be restored.',
        ]);
    }

    return [
        'dispensing' => $dispensing,
        'dispensing_items' => $dispensingItems,
        'prescription' => $prescription,
        'prescription_items' =>
            $prescriptionItems,
    ];
}

private function reversePrescriptionDispensing(
    User $user,
    Sale $sale,
    string $reason,
    array $context,
): void {
    /** @var PrescriptionDispensing $dispensing */
    $dispensing = $context['dispensing'];

    /** @var Prescription $prescription */
    $prescription = $context['prescription'];

    $dispensingItems =
        $context['dispensing_items'];

    $prescriptionItems =
        $context['prescription_items'];

    $reversedItems = [];

    foreach ($dispensingItems as $dispensingItem) {
        /** @var PrescriptionItem|null $prescriptionItem */
        $prescriptionItem =
            $prescriptionItems->get(
                $dispensingItem
                    ->prescription_item_id,
            );

        if ($prescriptionItem === null) {
            throw ValidationException::withMessages([
                'prescription' =>
                    'A prescription item could not be restored.',
            ]);
        }

        $reversedQuantity = round(
            (float) $dispensingItem
                ->quantity_dispensed,
            3,
        );

        $newQuantityDispensed = round(
            max(
                (float) $prescriptionItem
                    ->quantity_dispensed
                - $reversedQuantity,
                0,
            ),
            3,
        );

        $quantityPrescribed = round(
            (float) $prescriptionItem
                ->quantity_prescribed,
            3,
        );

        $itemStatus = match (true) {
            $newQuantityDispensed <= 0.0005 =>
                'pending',

            $newQuantityDispensed + 0.0005
                >= $quantityPrescribed =>
                'dispensed',

            default =>
                'partially_dispensed',
        };

        $prescriptionItem->forceFill([
            'quantity_dispensed' =>
                $newQuantityDispensed,

            'status' => $itemStatus,
        ])->save();

        $reversedItems[] = [
            'prescription_item_id' =>
                $prescriptionItem->id,

            'prescribed_name' =>
                $prescriptionItem
                    ->prescribed_name,

            'quantity_reversed' =>
                $reversedQuantity,

            'quantity_remaining_dispensed' =>
                $newQuantityDispensed,
        ];
    }

    $allPrescriptionItems =
        PrescriptionItem::query()
            ->where(
                'prescription_id',
                $prescription->id,
            )
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

    $allFullyDispensed =
        $allPrescriptionItems->isNotEmpty()
        && $allPrescriptionItems->every(
            fn (
                PrescriptionItem $item,
            ): bool =>
                (float) $item
                    ->quantity_dispensed
                + 0.0005
                >= (float) $item
                    ->quantity_prescribed,
        );

    $hasDispensedQuantity =
        $allPrescriptionItems->contains(
            fn (
                PrescriptionItem $item,
            ): bool =>
                (float) $item
                    ->quantity_dispensed
                > 0.0005,
        );

    $prescriptionStatus = match (true) {
        $allFullyDispensed =>
            Prescription::STATUS_DISPENSED,

        $hasDispensedQuantity =>
            Prescription
                ::STATUS_PARTIALLY_DISPENSED,

        default =>
            Prescription::STATUS_APPROVED,
    };

    $prescription->forceFill([
        'status' => $prescriptionStatus,

        'dispensed_at' =>
            $allFullyDispensed
                ? (
                    $prescription->dispensed_at
                    ?? now()
                )
                : null,
    ])->save();

    $dispensing->forceFill([
        'status' =>
            PrescriptionDispensing::STATUS_VOIDED,

        'voided_by_user_id' => $user->id,
        'voided_at' => now(),
        'void_reason' => $reason,
    ])->save();

    app(RecordPrescriptionActivity::class)
        ->handle(
            actor: $user,
            prescription: $prescription,
            activityType:
                'dispensing_voided',
            title:
                'Prescription dispensing reversed',
            description: sprintf(
                'Dispensing %s was reversed because sale %s was voided.',
                $dispensing->dispensing_number,
                $sale->sale_number,
            ),
            metadata: [
                'dispensing_id' =>
                    $dispensing->id,

                'dispensing_number' =>
                    $dispensing
                        ->dispensing_number,

                'sale_id' => $sale->id,

                'sale_number' =>
                    $sale->sale_number,

                'reason' => $reason,

                'items' => $reversedItems,
            ],
        );
}
}