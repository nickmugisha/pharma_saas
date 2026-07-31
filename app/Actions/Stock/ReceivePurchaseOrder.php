<?php

namespace App\Actions\Stock;

use App\Models\MedicineBatch;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\BranchMedicineSetting;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptItem;
use App\Models\StockMovement;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Actions\Stock\SyncInventoryAlerts;

class ReceivePurchaseOrder
{
    public function handle(
        User $user,
        int $purchaseOrderId,
        array $lines,
        ?string $notes = null,
    ): PurchaseReceipt {
        return DB::transaction(function () use (
            $user,
            $purchaseOrderId,
            $lines,
            $notes,
        ): PurchaseReceipt {
            $pharmacyId = $user->pharmacy_id;

            abort_unless($pharmacyId, 403);

            $order = PurchaseOrder::query()
                ->whereKey($purchaseOrderId)
                ->where('pharmacy_id', $pharmacyId)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array(
                $order->status,
                ['approved', 'partially_received'],
                true,
            )) {
                throw ValidationException::withMessages([
                    'purchase_order_id' =>
                        'Only approved purchase orders can be received.',
                ]);
            }

            if ($lines === []) {
                throw ValidationException::withMessages([
                    'items' => 'Add at least one received item.',
                ]);
            }

            $receipt = PurchaseReceipt::create([
                'pharmacy_id' => $order->pharmacy_id,
                'pharmacy_branch_id' => $order->pharmacy_branch_id,
                'purchase_order_id' => $order->id,
                'supplier_id' => $order->supplier_id,
                'received_by_user_id' => $user->id,
                'received_at' => now(),
                'status' => 'draft',
                'notes' => $notes,
            ]);

            $seenLines = [];

            foreach ($lines as $index => $line) {
                $orderItemId = (int) ($line['purchase_order_item_id'] ?? 0);
                $batchNumber = trim((string) ($line['batch_number'] ?? ''));

                $duplicateKey = $orderItemId.'|'.mb_strtolower($batchNumber);

                if (isset($seenLines[$duplicateKey])) {
                    throw ValidationException::withMessages([
                        "items.{$index}.batch_number" =>
                            'The same order item and batch appears twice.',
                    ]);
                }

                $seenLines[$duplicateKey] = true;

                $orderItem = PurchaseOrderItem::query()
                    ->whereKey($orderItemId)
                    ->where('purchase_order_id', $order->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                
                $pharmacyMedicine = $orderItem->pharmacyMedicine;

abort_unless(
    $pharmacyMedicine
    && (int) $pharmacyMedicine->pharmacy_id
        === (int) $order->pharmacy_id,
    422,
);

BranchMedicineSetting::firstOrCreate(
    [
        'pharmacy_branch_id' =>
            $order->pharmacy_branch_id,
        'pharmacy_medicine_id' =>
            $orderItem->pharmacy_medicine_id,
    ],
    [
        'pharmacy_id' => $order->pharmacy_id,
        'minimum_stock_level' =>
            $pharmacyMedicine->minimum_stock_level ?? 0,
        'reorder_quantity' =>
            $pharmacyMedicine->reorder_quantity ?? 0,
        'expiry_warning_days' =>
            $pharmacyMedicine->expiry_warning_days ?? 90,
        'alerts_enabled' =>
            $pharmacyMedicine->alerts_enabled ?? true,
    ],
);

                $quantity = round(
                    (float) ($line['quantity_received'] ?? 0),
                    3,
                );

                $remaining = round(
                    (float) $orderItem->quantity_ordered
                    - (float) $orderItem->quantity_received,
                    3,
                );

                if ($quantity <= 0) {
                    throw ValidationException::withMessages([
                        "items.{$index}.quantity_received" =>
                            'Received quantity must be greater than zero.',
                    ]);
                }

                if ($quantity > $remaining) {
                    throw ValidationException::withMessages([
                        "items.{$index}.quantity_received" =>
                            "Only {$remaining} units remain to be received.",
                    ]);
                }

                if ($batchNumber === '') {
                    throw ValidationException::withMessages([
                        "items.{$index}.batch_number" =>
                            'Batch number is required.',
                    ]);
                }

                $expiryDate = Carbon::parse(
                    $line['expiry_date'] ?? null,
                )->startOfDay();

                if ($expiryDate->lte(today())) {
                    throw ValidationException::withMessages([
                        "items.{$index}.expiry_date" =>
                            'Expiry date must be after today.',
                    ]);
                }

                $manufacturingDate = filled(
                    $line['manufacturing_date'] ?? null,
                )
                    ? Carbon::parse(
                        $line['manufacturing_date'],
                    )->startOfDay()
                    : null;

                if (
                    $manufacturingDate
                    && $manufacturingDate->gte($expiryDate)
                ) {
                    throw ValidationException::withMessages([
                        "items.{$index}.manufacturing_date" =>
                            'Manufacturing date must precede expiry date.',
                    ]);
                }

                $batch = MedicineBatch::query()
                    ->where(
                        'pharmacy_branch_id',
                        $order->pharmacy_branch_id,
                    )
                    ->where(
                        'pharmacy_medicine_id',
                        $orderItem->pharmacy_medicine_id,
                    )
                    ->where('batch_number', $batchNumber)
                    ->lockForUpdate()
                    ->first();

                if ($batch) {
                    if (
                        ! $batch->expiry_date->isSameDay($expiryDate)
                    ) {
                        throw ValidationException::withMessages([
                            "items.{$index}.expiry_date" =>
                                'This batch number already has another expiry date.',
                        ]);
                    }

                    $newReceived = round(
                        (float) $batch->quantity_received + $quantity,
                        3,
                    );

                    $newAvailable = round(
                        (float) $batch->quantity_available + $quantity,
                        3,
                    );

                    $batch->forceFill([
                        'quantity_received' => $newReceived,
                        'quantity_available' => $newAvailable,
                        'status' => 'active',
                    ])->save();
                } else {
                    $batch = MedicineBatch::create([
                        'pharmacy_id' => $order->pharmacy_id,
                        'pharmacy_branch_id' =>
                            $order->pharmacy_branch_id,
                        'pharmacy_medicine_id' =>
                            $orderItem->pharmacy_medicine_id,
                        'supplier_id' => $order->supplier_id,
                        'purchase_order_item_id' => $orderItem->id,
                        'batch_number' => $batchNumber,
                        'manufacturing_date' => $manufacturingDate,
                        'expiry_date' => $expiryDate,
                        'unit_cost' => $orderItem->unit_cost,
                        'quantity_received' => $quantity,
                        'quantity_available' => $quantity,
                        'status' => 'active',
                        'received_at' => now(),
                    ]);

                    $newAvailable = $quantity;
                }

                $receiptItem = PurchaseReceiptItem::create([
                    'purchase_receipt_id' => $receipt->id,
                    'purchase_order_item_id' => $orderItem->id,
                    'pharmacy_medicine_id' =>
                        $orderItem->pharmacy_medicine_id,
                    'medicine_batch_id' => $batch->id,
                    'batch_number' => $batchNumber,
                    'manufacturing_date' => $manufacturingDate,
                    'expiry_date' => $expiryDate,
                    'quantity_received' => $quantity,
                    'unit_cost' => $orderItem->unit_cost,
                    'notes' => $line['notes'] ?? null,
                ]);

                $orderItem->forceFill([
                    'quantity_received' => round(
                        (float) $orderItem->quantity_received
                        + $quantity,
                        3,
                    ),
                ])->save();

                StockMovement::create([
                    'pharmacy_id' => $order->pharmacy_id,
                    'pharmacy_branch_id' =>
                        $order->pharmacy_branch_id,
                    'pharmacy_medicine_id' =>
                        $orderItem->pharmacy_medicine_id,
                    'medicine_batch_id' => $batch->id,
                    'created_by_user_id' => $user->id,
                    'movement_type' => 'purchase_receipt',
                    'direction' => 'in',
                    'quantity' => $quantity,
                    'unit_cost' => $orderItem->unit_cost,
                    'balance_after' => $newAvailable,
                    'reference_type' => PurchaseReceiptItem::class,
                    'reference_id' => $receiptItem->id,
                    'occurred_at' => now(),
                    'notes' => "Receipt {$receipt->receipt_number}",
                ]);
            }

            $hasRemainingItems = $order->items()
                ->whereRaw('quantity_received < quantity_ordered')
                ->exists();

            $order->forceFill([
                'status' => $hasRemainingItems
                    ? 'partially_received'
                    : 'received',
            ])->save();

            $receipt->forceFill([
                'status' => 'completed',
                'received_at' => now(),
            ])->save();

            foreach (
    $receipt->items
        ->pluck('pharmacy_medicine_id')
        ->unique()
    as $pharmacyMedicineId
) {
    app(SyncInventoryAlerts::class)->handle(
        pharmacyId: $order->pharmacy_id,
        branchId: $order->pharmacy_branch_id,
        pharmacyMedicineId: (int) $pharmacyMedicineId,
    );
}

            return $receipt->fresh([
                'items.medicineBatch',
            ]);
        });
    }
}