<?php

namespace App\Actions\Prescriptions;

use App\Actions\Sales\CompletePosSale;
use App\Models\PharmacyMedicine;
use App\Models\Prescription;
use App\Models\PrescriptionDispensing;
use App\Models\PrescriptionDispensingItem;
use App\Models\PrescriptionItem;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

class DispensePrescription
{
    public function handle(
        User $actor,
        Prescription $prescription,
        array $lines,
        array $payments,
        ?string $notes = null,
    ): PrescriptionDispensing {
        abort_unless(
            $actor->can('prescriptions.dispense'),
            403,
        );

        abort_unless(
            $actor->can('sales.create'),
            403,
        );

        if ($lines === []) {
            throw ValidationException::withMessages([
                'lines' =>
                    'Add at least one prescription item to dispense.',
            ]);
        }

        return DB::transaction(
            function () use (
                $actor,
                $prescription,
                $lines,
                $payments,
                $notes,
            ): PrescriptionDispensing {
                $lockedPrescription = Prescription::query()
                    ->with([
                        'customer',
                        'branch',
                    ])
                    ->lockForUpdate()
                    ->findOrFail($prescription->id);

                $this->authorizeTenant(
                    $actor,
                    $lockedPrescription,
                );

                $this->validatePrescriptionStatus(
                    $lockedPrescription,
                );

                $this->ensureNotExpired(
                    $lockedPrescription,
                );

                $items = PrescriptionItem::query()
                    ->where(
                        'prescription_id',
                        $lockedPrescription->id,
                    )
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                if ($items->isEmpty()) {
                    throw ValidationException::withMessages([
                        'lines' =>
                            'The prescription contains no medicine items.',
                    ]);
                }

                $preparedLines = $this->prepareLines(
                    actor: $actor,
                    prescriptionItems: $items,
                    lines: $lines,
                );

                $saleLines = array_map(
                    fn (array $line): array => [
                        'pharmacy_medicine_id' =>
                            $line['pharmacy_medicine_id'],

                        'quantity' =>
                            $line['quantity'],

                        'discount_amount' =>
                            $line['discount_amount'],

                        'tax_rate' =>
                            $line['tax_rate'],
                    ],
                    $preparedLines,
                );

                $customer = $lockedPrescription->customer;

                $sale = app(CompletePosSale::class)
                    ->handle(
                        $actor,
                        $saleLines,
                        $payments,
                        [
                            'pharmacy_branch_id' =>
                                $lockedPrescription
                                    ->pharmacy_branch_id,

                            'customer_id' =>
                                $customer->id,

                            'customer_name' =>
                                $customer->name,

                            'customer_phone' =>
                                $customer->phone,

                            'source_type' =>
                                $lockedPrescription
                                    ->getMorphClass(),

                            'source_id' =>
                                $lockedPrescription->id,

                            'notes' =>
                                filled($notes)
                                    ? trim($notes)
                                    : sprintf(
                                        'Dispensing for prescription %s.',
                                        $lockedPrescription
                                            ->prescription_number,
                                    ),
                        ],
                    );

                $this->linkSaleToPrescription(
                    $sale,
                    $lockedPrescription,
                );

                $saleItems = $sale->items()
                    ->orderBy('id')
                    ->get();

                if (
                    $saleItems->count()
                    !== count($preparedLines)
                ) {
                    throw new LogicException(
                        'The POS sale items do not match the prescription dispensing lines.',
                    );
                }

                $dispensing = PrescriptionDispensing::create([
                    'pharmacy_id' =>
                        $lockedPrescription->pharmacy_id,

                    'pharmacy_branch_id' =>
                        $lockedPrescription
                            ->pharmacy_branch_id,

                    'prescription_id' =>
                        $lockedPrescription->id,

                    'sale_id' => $sale->id,

                    'dispensed_by_user_id' =>
                        $actor->id,

                    'status' =>
                        PrescriptionDispensing
                            ::STATUS_COMPLETED,

                    'dispensed_at' => now(),

                    'notes' =>
                        filled($notes)
                            ? trim($notes)
                            : null,
                ]);

                foreach (
                    $preparedLines
                    as $index => $preparedLine
                ) {
                    $saleItem = $saleItems->get($index);

                    $this->verifySaleItem(
                        $saleItem,
                        $preparedLine,
                    );

                    /** @var PrescriptionItem $prescriptionItem */
                    $prescriptionItem =
                        $preparedLine[
                            'prescription_item'
                        ];

                    PrescriptionDispensingItem::create([
                        'prescription_dispensing_id' =>
                            $dispensing->id,

                        'prescription_item_id' =>
                            $prescriptionItem->id,

                        'sale_item_id' =>
                            $saleItem->id,

                        'quantity_dispensed' =>
                            $preparedLine['quantity'],
                    ]);

                    $newQuantityDispensed = round(
                        (float) $prescriptionItem
                            ->quantity_dispensed
                        + $preparedLine['quantity'],
                        3,
                    );

                    $fullyDispensed =
                        $newQuantityDispensed
                        + 0.0005
                        >= (float) $prescriptionItem
                            ->quantity_prescribed;

                    $prescriptionItem->forceFill([
                        'quantity_dispensed' =>
                            $newQuantityDispensed,

                        'status' =>
                            $fullyDispensed
                                ? 'dispensed'
                                : 'partially_dispensed',
                    ])->save();
                }

                $allItemsDispensed = $items->every(
                    fn (
                        PrescriptionItem $item,
                    ): bool =>
                        (float) $item
                            ->quantity_dispensed
                        + 0.0005
                        >= (float) $item
                            ->quantity_prescribed,
                );

                $lockedPrescription->forceFill([
                    'status' =>
                        $allItemsDispensed
                            ? Prescription
                                ::STATUS_DISPENSED
                            : Prescription
                                ::STATUS_PARTIALLY_DISPENSED,

                    'dispensed_at' =>
                        $allItemsDispensed
                            ? now()
                            : null,
                ])->save();

                app(RecordPrescriptionActivity::class)
                    ->handle(
                        actor: $actor,
                        prescription:
                            $lockedPrescription,
                        activityType:
                            $allItemsDispensed
                                ? 'dispensed'
                                : 'partially_dispensed',
                        title:
                            $allItemsDispensed
                                ? 'Prescription fully dispensed'
                                : 'Prescription partially dispensed',
                        description: sprintf(
                            'Medicines were dispensed through sale %s.',
                            $sale->sale_number,
                        ),
                        metadata: [
                            'dispensing_number' =>
                                $dispensing
                                    ->dispensing_number,

                            'sale_id' => $sale->id,

                            'sale_number' =>
                                $sale->sale_number,

                            'receipt_number' =>
                                $sale->receipt_number,

                            'sale_total' =>
                                (float) $sale
                                    ->grand_total,

                            'items' => array_map(
                                fn (
                                    array $line,
                                ): array => [
                                    'prescription_item_id' =>
                                        $line[
                                            'prescription_item'
                                        ]->id,

                                    'pharmacy_medicine_id' =>
                                        $line[
                                            'pharmacy_medicine_id'
                                        ],

                                    'quantity' =>
                                        $line['quantity'],
                                ],
                                $preparedLines,
                            ),
                        ],
                    );

                return $dispensing->fresh([
                    'prescription.customer',
                    'prescription.items',
                    'sale.items',
                    'sale.payments',
                    'items.prescriptionItem',
                    'items.saleItem',
                    'dispensedByUser',
                    'branch',
                ]);
            },
            5,
        );
    }

    private function authorizeTenant(
        User $actor,
        Prescription $prescription,
    ): void {
        abort_unless(
            (int) $actor->pharmacy_id
                === (int) $prescription->pharmacy_id,
            403,
        );

        abort_unless(
            (int) $actor->pharmacy_branch_id
                === (int) $prescription
                    ->pharmacy_branch_id,
            403,
        );

        abort_unless(
            (int) $prescription
                ->customer
                ->pharmacy_id
                === (int) $prescription->pharmacy_id,
            422,
        );
    }

    private function validatePrescriptionStatus(
        Prescription $prescription,
    ): void {
        if (
            ! in_array(
                $prescription->status,
                [
                    Prescription::STATUS_APPROVED,
                    Prescription
                        ::STATUS_PARTIALLY_DISPENSED,
                ],
                true,
            )
        ) {
            throw ValidationException::withMessages([
                'status' =>
                    'Only approved or partially dispensed prescriptions can be dispensed.',
            ]);
        }
    }

    private function ensureNotExpired(
        Prescription $prescription,
    ): void {
        if (
            $prescription->valid_until !== null
            && $prescription
                ->valid_until
                ->isBefore(today())
        ) {
            throw ValidationException::withMessages([
                'valid_until' =>
                    'The prescription has expired.',
            ]);
        }
    }

    private function prepareLines(
        User $actor,
        Collection $prescriptionItems,
        array $lines,
    ): array {
        $prepared = [];
        $usedPrescriptionItems = [];

        foreach ($lines as $index => $line) {
            if (! is_array($line)) {
                throw ValidationException::withMessages([
                    "lines.{$index}" =>
                        'The dispensing line is invalid.',
                ]);
            }

            $prescriptionItemId =
                $line['prescription_item_id']
                ?? null;

            if (blank($prescriptionItemId)) {
                throw ValidationException::withMessages([
                    "lines.{$index}.prescription_item_id" =>
                        'Select a prescription item.',
                ]);
            }

            if (
                in_array(
                    (int) $prescriptionItemId,
                    $usedPrescriptionItems,
                    true,
                )
            ) {
                throw ValidationException::withMessages([
                    "lines.{$index}.prescription_item_id" =>
                        'The same prescription item cannot be added twice.',
                ]);
            }

            /** @var PrescriptionItem|null $prescriptionItem */
            $prescriptionItem =
                $prescriptionItems->firstWhere(
                    'id',
                    (int) $prescriptionItemId,
                );

            if ($prescriptionItem === null) {
                throw ValidationException::withMessages([
                    "lines.{$index}.prescription_item_id" =>
                        'The selected prescription item is invalid.',
                ]);
            }

            $quantity = round(
                (float) (
                    $line['quantity'] ?? 0
                ),
                3,
            );

            if ($quantity <= 0) {
                throw ValidationException::withMessages([
                    "lines.{$index}.quantity" =>
                        'The dispensing quantity must be greater than zero.',
                ]);
            }

            $remainingQuantity = round(
                (float) $prescriptionItem
                    ->quantity_prescribed
                - (float) $prescriptionItem
                    ->quantity_dispensed,
                3,
            );

            if (
                $remainingQuantity <= 0
                || $quantity
                    > $remainingQuantity + 0.0005
            ) {
                throw ValidationException::withMessages([
                    "lines.{$index}.quantity" =>
                        sprintf(
                            'Only %s remains available for this prescription item.',
                            number_format(
                                max(
                                    $remainingQuantity,
                                    0,
                                ),
                                3,
                                '.',
                                '',
                            ),
                        ),
                ]);
            }

            $listingId =
                $line['pharmacy_medicine_id']
                ?? null;

            if (blank($listingId)) {
                throw ValidationException::withMessages([
                    "lines.{$index}.pharmacy_medicine_id" =>
                        'Select the pharmacy medicine to dispense.',
                ]);
            }

            $listing = PharmacyMedicine::query()
                ->with('medicine')
                ->whereKey($listingId)
                ->where(
                    'pharmacy_id',
                    $actor->pharmacy_id,
                )
                ->where('status', 'active')
                ->first();

            if ($listing === null) {
                throw ValidationException::withMessages([
                    "lines.{$index}.pharmacy_medicine_id" =>
                        'The selected pharmacy medicine is unavailable.',
                ]);
            }

            $this->validateMedicineMatch(
                $prescriptionItem,
                $listing,
                $index,
            );

            $usedPrescriptionItems[] =
                (int) $prescriptionItemId;

            $prepared[] = [
                'prescription_item' =>
                    $prescriptionItem,

                'pharmacy_medicine_id' =>
                    $listing->id,

                'quantity' => $quantity,

                'discount_amount' =>
                    round(
                        max(
                            (float) (
                                $line[
                                    'discount_amount'
                                ] ?? 0
                            ),
                            0,
                        ),
                        2,
                    ),

                'tax_rate' =>
                    round(
                        max(
                            (float) (
                                $line['tax_rate']
                                ?? 0
                            ),
                            0,
                        ),
                        2,
                    ),
            ];
        }

        return $prepared;
    }

    private function validateMedicineMatch(
        PrescriptionItem $prescriptionItem,
        PharmacyMedicine $listing,
        int $index,
    ): void {
        if ($prescriptionItem->substitution_allowed) {
            return;
        }

        if (
            $prescriptionItem
                ->pharmacy_medicine_id !== null
            && (int) $prescriptionItem
                ->pharmacy_medicine_id
                !== (int) $listing->id
        ) {
            throw ValidationException::withMessages([
                "lines.{$index}.pharmacy_medicine_id" =>
                    'This prescription item does not allow medicine substitution.',
            ]);
        }

        if (
            $prescriptionItem->medicine_id !== null
            && (int) $prescriptionItem
                ->medicine_id
                !== (int) $listing->medicine_id
        ) {
            throw ValidationException::withMessages([
                "lines.{$index}.pharmacy_medicine_id" =>
                    'The selected medicine does not match the approved prescription item.',
            ]);
        }
    }

    private function linkSaleToPrescription(
        Sale $sale,
        Prescription $prescription,
    ): void {
        $sale->forceFill([
            'customer_id' =>
                $prescription->customer_id,

            'source_type' =>
                $prescription->getMorphClass(),

            'source_id' =>
                $prescription->id,
        ])->save();
    }

    private function verifySaleItem(
        mixed $saleItem,
        array $preparedLine,
    ): void {
        if ($saleItem === null) {
            throw new LogicException(
                'A sale item is missing from the completed POS sale.',
            );
        }

        if (
            (int) $saleItem
                ->pharmacy_medicine_id
            !== (int) $preparedLine[
                'pharmacy_medicine_id'
            ]
        ) {
            throw new LogicException(
                'The generated sale item medicine does not match the dispensing line.',
            );
        }

        if (
            abs(
                (float) $saleItem->quantity
                - (float) $preparedLine['quantity'],
            ) > 0.0005
        ) {
            throw new LogicException(
                'The generated sale item quantity does not match the dispensing line.',
            );
        }
    }
}