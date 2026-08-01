<?php

namespace App\Actions\Sales;

use App\Actions\Stock\SyncInventoryAlerts;
use App\Models\MedicineBatch;
use App\Models\PharmacyBranch;
use App\Models\PharmacyMedicine;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleItemBatch;
use App\Models\SalePayment;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CompletePosSale
{
    private const PAYMENT_METHODS = [
        'cash',
        'mobile_money',
        'bank_transfer',
        'card',
        'other',
    ];

    public function handle(
        User $user,
        array $lines,
        array $payments,
        array $saleData = [],
    ): Sale {
        abort_unless(
            $user->can('sales.manage'),
            403,
        );

        $pharmacyId = $user->pharmacy_id;

        abort_unless($pharmacyId, 403);

        $branchId = (int) (
            $saleData['pharmacy_branch_id']
            ?? $user->pharmacy_branch_id
        );

        $sale = DB::transaction(function () use (
            $user,
            $pharmacyId,
            $branchId,
            $lines,
            $payments,
            $saleData,
        ): Sale {
            PharmacyBranch::query()
                ->whereKey($branchId)
                ->where('pharmacy_id', $pharmacyId)
                ->where('status', 'active')
                ->firstOrFail();

            if ($lines === []) {
                throw ValidationException::withMessages([
                    'items' => 'Add at least one medicine.',
                ]);
            }

            if ($payments === []) {
                throw ValidationException::withMessages([
                    'payments' => 'Add at least one payment.',
                ]);
            }

            $sale = Sale::create([
                'pharmacy_id' => $pharmacyId,
                'pharmacy_branch_id' => $branchId,
                'cashier_user_id' => $user->id,
                'channel' => 'pos',
                'sold_at' => now(),
                'status' => 'draft',
                'payment_status' => 'unpaid',
                'currency' => 'BIF',
                'customer_name' =>
                    $saleData['customer_name'] ?? null,
                'customer_phone' =>
                    $saleData['customer_phone'] ?? null,
                'source_type' =>
                    $saleData['source_type'] ?? null,
                'source_id' =>
                    $saleData['source_id'] ?? null,
                'notes' => $saleData['notes'] ?? null,
            ]);

            $subtotal = 0.0;
            $discountTotal = 0.0;
            $taxTotal = 0.0;
            $seenMedicines = [];
            $affectedMedicineIds = [];

            foreach ($lines as $index => $line) {
                $pharmacyMedicineId = (int) (
                    $line['pharmacy_medicine_id'] ?? 0
                );

                if (isset($seenMedicines[$pharmacyMedicineId])) {
                    throw ValidationException::withMessages([
                        "items.{$index}.pharmacy_medicine_id" =>
                            'The same medicine cannot appear twice.',
                    ]);
                }

                $seenMedicines[$pharmacyMedicineId] = true;

                $listing = PharmacyMedicine::query()
                    ->with('medicine')
                    ->whereKey($pharmacyMedicineId)
                    ->where('pharmacy_id', $pharmacyId)
                    ->where('status', 'active')
                    ->firstOrFail();

                $quantity = round(
                    (float) ($line['quantity'] ?? 0),
                    3,
                );

                if ($quantity <= 0) {
                    throw ValidationException::withMessages([
                        "items.{$index}.quantity" =>
                            'Quantity must be greater than zero.',
                    ]);
                }

                $unitPrice = round(
                    (float) $listing->selling_price,
                    2,
                );

                if ($unitPrice <= 0) {
                    throw ValidationException::withMessages([
                        "items.{$index}.pharmacy_medicine_id" =>
                            'This medicine does not have a valid selling price.',
                    ]);
                }

                $grossAmount = round(
                    $quantity * $unitPrice,
                    2,
                );

                $discountAmount = round(
                    (float) ($line['discount_amount'] ?? 0),
                    2,
                );

                if (
                    $discountAmount < 0
                    || $discountAmount > $grossAmount
                ) {
                    throw ValidationException::withMessages([
                        "items.{$index}.discount_amount" =>
                            'The discount is invalid.',
                    ]);
                }

                $taxRate = round(
                    (float) ($line['tax_rate'] ?? 0),
                    3,
                );

                if ($taxRate < 0 || $taxRate > 100) {
                    throw ValidationException::withMessages([
                        "items.{$index}.tax_rate" =>
                            'The tax rate must be between 0 and 100.',
                    ]);
                }

                $taxableAmount = round(
                    $grossAmount - $discountAmount,
                    2,
                );

                $taxAmount = round(
                    $taxableAmount * ($taxRate / 100),
                    2,
                );

                $lineTotal = round(
                    $taxableAmount + $taxAmount,
                    2,
                );

                $medicineName =
                    $listing->medicine?->brand_name
                    ?? $listing->medicine?->generic_name
                    ?? "Medicine #{$listing->id}";

                $batches = MedicineBatch::query()
                    ->where('pharmacy_id', $pharmacyId)
                    ->where('pharmacy_branch_id', $branchId)
                    ->where(
                        'pharmacy_medicine_id',
                        $listing->id,
                    )
                    ->where('status', 'active')
                    ->where('quantity_available', '>', 0)
                    ->whereDate('expiry_date', '>', today())
                    ->orderBy('expiry_date')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                $availableQuantity = round(
                    (float) $batches->sum('quantity_available'),
                    3,
                );

                if ($availableQuantity < $quantity) {
                    throw ValidationException::withMessages([
                        "items.{$index}.quantity" => sprintf(
                            'Insufficient stock. Only %s unit(s) are available.',
                            number_format($availableQuantity, 3),
                        ),
                    ]);
                }

                $saleItem = SaleItem::create([
                    'sale_id' => $sale->id,
                    'pharmacy_medicine_id' => $listing->id,
                    'medicine_name' => $medicineName,
                    'sku' => $listing->sku,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'discount_amount' => $discountAmount,
                    'tax_rate' => $taxRate,
                    'tax_amount' => $taxAmount,
                    'line_total' => $lineTotal,
                    'cost_total' => 0,
                    'notes' => $line['notes'] ?? null,
                ]);

                $remaining = $quantity;
                $costTotal = 0.0;

                foreach ($batches as $batch) {
                    if ($remaining <= 0) {
                        break;
                    }

                    $batchAvailable = round(
                        (float) $batch->quantity_available,
                        3,
                    );

                    $allocatedQuantity = round(
                        min($remaining, $batchAvailable),
                        3,
                    );

                    if ($allocatedQuantity <= 0) {
                        continue;
                    }

                    $newBalance = round(
                        $batchAvailable - $allocatedQuantity,
                        3,
                    );

                    $allocation = SaleItemBatch::create([
                        'sale_item_id' => $saleItem->id,
                        'medicine_batch_id' => $batch->id,
                        'quantity' => $allocatedQuantity,
                        'unit_cost' => $batch->unit_cost,
                    ]);

                    $costTotal = round(
                        $costTotal
                        + (float) $allocation->line_cost,
                        2,
                    );

                    $batch->forceFill([
                        'quantity_available' => $newBalance,
                        'status' => $newBalance <= 0
                            ? 'depleted'
                            : 'active',
                    ])->save();

                    StockMovement::create([
                        'pharmacy_id' => $pharmacyId,
                        'pharmacy_branch_id' => $branchId,
                        'pharmacy_medicine_id' => $listing->id,
                        'medicine_batch_id' => $batch->id,
                        'created_by_user_id' => $user->id,
                        'movement_type' => 'sale',
                        'direction' => 'out',
                        'quantity' => $allocatedQuantity,
                        'unit_cost' => $batch->unit_cost,
                        'balance_after' => $newBalance,
                        'reference_type' => SaleItemBatch::class,
                        'reference_id' => $allocation->id,
                        'occurred_at' => now(),
                        'notes' => "Sale {$sale->sale_number}",
                    ]);

                    $remaining = round(
                        $remaining - $allocatedQuantity,
                        3,
                    );
                }

                $saleItem->forceFill([
                    'cost_total' => $costTotal,
                ])->saveQuietly();

                $subtotal = round(
                    $subtotal + $grossAmount,
                    2,
                );

                $discountTotal = round(
                    $discountTotal + $discountAmount,
                    2,
                );

                $taxTotal = round(
                    $taxTotal + $taxAmount,
                    2,
                );

                $affectedMedicineIds[] = $listing->id;
            }

            $grandTotal = round(
                $subtotal - $discountTotal + $taxTotal,
                2,
            );

            $paymentTotal = 0.0;
            $hasCashPayment = false;

            foreach ($payments as $index => $payment) {
                $method = (string) (
                    $payment['payment_method'] ?? ''
                );

                if (! in_array(
                    $method,
                    self::PAYMENT_METHODS,
                    true,
                )) {
                    throw ValidationException::withMessages([
                        "payments.{$index}.payment_method" =>
                            'The selected payment method is invalid.',
                    ]);
                }

                $amount = round(
                    (float) ($payment['amount'] ?? 0),
                    2,
                );

                if ($amount <= 0) {
                    throw ValidationException::withMessages([
                        "payments.{$index}.amount" =>
                            'Payment amount must be greater than zero.',
                    ]);
                }

                $paymentTotal = round(
                    $paymentTotal + $amount,
                    2,
                );

                if ($method === 'cash') {
                    $hasCashPayment = true;
                }
            }

            if ($paymentTotal < $grandTotal) {
                throw ValidationException::withMessages([
                    'payments' => sprintf(
                        'The payment is insufficient. %s BIF remains.',
                        number_format(
                            $grandTotal - $paymentTotal,
                            0,
                        ),
                    ),
                ]);
            }

            if (
                $paymentTotal > $grandTotal
                && ! $hasCashPayment
            ) {
                throw ValidationException::withMessages([
                    'payments' =>
                        'Overpayment is only allowed when cash is included.',
                ]);
            }

            foreach ($payments as $payment) {
                SalePayment::create([
                    'pharmacy_id' => $pharmacyId,
                    'sale_id' => $sale->id,
                    'received_by_user_id' => $user->id,
                    'paid_at' => now(),
                    'amount' => round(
                        (float) $payment['amount'],
                        2,
                    ),
                    'payment_method' =>
                        $payment['payment_method'],
                    'reference' =>
                        $payment['reference'] ?? null,
                    'status' => 'completed',
                    'notes' => $payment['notes'] ?? null,
                ]);
            }

            $sale->forceFill([
                'receipt_number' => sprintf(
                    'RCT-%s-%s',
                    now()->format('Ymd'),
                    Str::upper(Str::random(6)),
                ),
                'subtotal' => $subtotal,
                'discount_total' => $discountTotal,
                'tax_total' => $taxTotal,
                'grand_total' => $grandTotal,
                'paid_amount' => $grandTotal,
                'change_amount' => round(
                    max($paymentTotal - $grandTotal, 0),
                    2,
                ),
                'status' => 'completed',
                'payment_status' => 'paid',
                'completed_at' => now(),
            ])->save();

            foreach (
                array_unique($affectedMedicineIds)
                as $pharmacyMedicineId
            ) {
                app(SyncInventoryAlerts::class)->handle(
                    pharmacyId: $pharmacyId,
                    branchId: $branchId,
                    pharmacyMedicineId:
                        (int) $pharmacyMedicineId,
                );
            }

            return $sale->fresh([
                'items.batchAllocations.medicineBatch',
                'payments',
                'branch',
                'cashier',
            ]);
        }, attempts: 5);

        return $sale;
    }
}