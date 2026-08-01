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
}