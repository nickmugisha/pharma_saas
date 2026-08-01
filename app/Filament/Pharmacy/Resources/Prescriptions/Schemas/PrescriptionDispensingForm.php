<?php

namespace App\Filament\Pharmacy\Resources\Prescriptions\Schemas;

use App\Filament\Pharmacy\Resources\Sales\Schemas\SaleForm;
use App\Models\MedicineBatch;
use App\Models\PharmacyMedicine;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;

class PrescriptionDispensingForm
{
    public static function components(
        Prescription $prescription,
    ): array {
        $remainingItemCount = $prescription
            ->items()
            ->whereColumn(
                'quantity_dispensed',
                '<',
                'quantity_prescribed',
            )
            ->count();

        return [
            Section::make('Medicines to dispense')
                ->description(
                    'Select prescribed items and enter the quantity being supplied during this dispensing event.'
                )
                ->columns([
                    'default' => 1,
                ])
                ->schema([
                    Repeater::make('lines')
                        ->label('Dispensing items')
                        ->schema([
                            Select::make(
                                'prescription_item_id'
                            )
                                ->label(
                                    'Prescription item'
                                )
                                ->options(
                                    fn (): array =>
                                        self::prescriptionItemOptions(
                                            $prescription,
                                        ),
                                )
                                ->required()
                                ->searchable()
                                ->preload()
                                ->live()
                                ->disableOptionsWhenSelectedInSiblingRepeaterItems(),

                            Placeholder::make(
                                'prescribed_details'
                            )
                                ->label(
                                    'Prescription instructions'
                                )
                                ->content(
                                    fn (Get $get): string =>
                                        self::prescribedDetails(
                                            $prescription,
                                            $get(
                                                'prescription_item_id'
                                            ),
                                        ),
                                ),

                            Placeholder::make(
                                'remaining_quantity'
                            )
                                ->label(
                                    'Remaining quantity'
                                )
                                ->content(
                                    fn (Get $get): string =>
                                        number_format(
                                            self::remainingQuantity(
                                                $prescription,
                                                $get(
                                                    'prescription_item_id'
                                                ),
                                            ),
                                            3,
                                        ).' unit(s)',
                                ),

                            Placeholder::make(
                                'substitution_rule'
                            )
                                ->label('Substitution')
                                ->content(
                                    fn (Get $get): string =>
                                        self::substitutionText(
                                            $prescription,
                                            $get(
                                                'prescription_item_id'
                                            ),
                                        ),
                                ),

                            Select::make(
                                'pharmacy_medicine_id'
                            )
                                ->label(
                                    'Medicine to supply'
                                )
                                ->options(
                                    fn (Get $get): array =>
                                        self::medicineOptions(
                                            prescription:
                                                $prescription,

                                            prescriptionItemId:
                                                $get(
                                                    'prescription_item_id'
                                                ),
                                        ),
                                )
                                ->required()
                                ->searchable()
                                ->preload()
                                ->live(),

                            Placeholder::make(
                                'selling_price'
                            )
                                ->label('Selling price')
                                ->content(
                                    fn (Get $get): string =>
                                        self::sellingPriceText(
                                            $get(
                                                'pharmacy_medicine_id'
                                            ),
                                        ),
                                ),

                            Placeholder::make(
                                'available_stock'
                            )
                                ->label(
                                    'Available non-expired stock'
                                )
                                ->content(
                                    fn (Get $get): string =>
                                        self::availableStockText(
                                            prescription:
                                                $prescription,

                                            listingId:
                                                $get(
                                                    'pharmacy_medicine_id'
                                                ),
                                        ),
                                ),

                            TextInput::make('quantity')
                                ->label(
                                    'Quantity to dispense'
                                )
                                ->numeric()
                                ->required()
                                ->minValue(0.001)
                                ->maxValue(
                                    fn (Get $get): float =>
                                        self::remainingQuantity(
                                            $prescription,
                                            $get(
                                                'prescription_item_id'
                                            ),
                                        ),
                                )
                                ->step(0.001)
                                ->default(1)
                                ->live(),

                            TextInput::make(
                                'discount_amount'
                            )
                                ->label('Discount amount')
                                ->numeric()
                                ->minValue(0)
                                ->step(0.01)
                                ->default(0)
                                ->suffix('BIF')
                                ->live(),

                            TextInput::make('tax_rate')
                                ->label('Tax rate')
                                ->numeric()
                                ->minValue(0)
                                ->maxValue(100)
                                ->step(0.001)
                                ->default(0)
                                ->suffix('%')
                                ->live(),

                            Placeholder::make(
                                'line_total'
                            )
                                ->label(
                                    'Estimated line total'
                                )
                                ->content(
                                    fn (Get $get): string =>
                                        number_format(
                                            SaleForm::calculateSaleTotal([
                                                [
                                                    'pharmacy_medicine_id' =>
                                                        $get(
                                                            'pharmacy_medicine_id'
                                                        ),

                                                    'quantity' =>
                                                        $get(
                                                            'quantity'
                                                        ),

                                                    'discount_amount' =>
                                                        $get(
                                                            'discount_amount'
                                                        ),

                                                    'tax_rate' =>
                                                        $get(
                                                            'tax_rate'
                                                        ),
                                                ],
                                            ]),
                                            2,
                                        ).' BIF',
                                ),
                        ])
                        ->columns([
                            'default' => 1,
                            'md' => 2,
                            'xl' => 3,
                        ])
                        ->defaultItems(1)
                        ->minItems(1)
                        ->maxItems(
                            max($remainingItemCount, 1),
                        )
                        ->itemNumbers()
                        ->reorderable(false)
                        ->addActionLabel(
                            'Add another prescribed item'
                        )
                        ->itemLabel(
                            fn (array $state): string =>
                                self::prescriptionItemLabel(
                                    $prescription,
                                    $state[
                                        'prescription_item_id'
                                    ] ?? null,
                                ),
                        ),
                ]),

            Section::make('Payments')
                ->description(
                    'Payment must cover the generated sale total. Change is allowed only when cash is included.'
                )
                ->schema([
                    Repeater::make('payments')
                        ->label('Payments received')
                        ->schema([
                            Select::make(
                                'payment_method'
                            )
                                ->label(
                                    'Payment method'
                                )
                                ->options([
                                    'cash' => 'Cash',
                                    'mobile_money' =>
                                        'Mobile money',
                                    'bank_transfer' =>
                                        'Bank transfer',
                                    'card' => 'Card',
                                    'other' => 'Other',
                                ])
                                ->default('cash')
                                ->required()
                                ->live(),

                            TextInput::make('amount')
                                ->label('Amount received')
                                ->numeric()
                                ->required()
                                ->minValue(0.01)
                                ->step(0.01)
                                ->suffix('BIF')
                                ->live(),

                            TextInput::make('reference')
                                ->label(
                                    'Payment reference'
                                )
                                ->maxLength(150)
                                ->placeholder(
                                    'Transaction or transfer reference'
                                )
                                ->visible(
                                    fn (Get $get): bool =>
                                        $get(
                                            'payment_method'
                                        ) !== 'cash',
                                ),

                            Textarea::make(
                                'notes'
                            )
                                ->label('Payment note')
                                ->rows(2)
                                ->maxLength(1000)
                                ->columnSpanFull(),
                        ])
                        ->columns([
                            'default' => 1,
                            'md' => 2,
                        ])
                        ->defaultItems(1)
                        ->minItems(1)
                        ->maxItems(5)
                        ->itemNumbers()
                        ->reorderable(false)
                        ->addActionLabel(
                            'Add another payment'
                        ),

                    Placeholder::make(
                        'estimated_sale_total'
                    )
                        ->label(
                            'Estimated sale total'
                        )
                        ->content(
                            fn (Get $get): string =>
                                number_format(
                                    SaleForm::calculateSaleTotal(
                                        $get('lines') ?? [],
                                    ),
                                    2,
                                ).' BIF',
                        ),

                    Placeholder::make(
                        'payment_total'
                    )
                        ->label('Payment total')
                        ->content(
                            fn (Get $get): string =>
                                number_format(
                                    SaleForm::calculatePaymentTotal(
                                        $get(
                                            'payments'
                                        ) ?? [],
                                    ),
                                    2,
                                ).' BIF',
                        ),

                    Placeholder::make(
                        'payment_balance'
                    )
                        ->label('Payment balance')
                        ->content(
                            fn (Get $get): string =>
                                SaleForm::paymentBalanceText(
                                    items:
                                        $get('lines') ?? [],

                                    payments:
                                        $get(
                                            'payments'
                                        ) ?? [],
                                ),
                        ),
                ]),

            Section::make('Dispensing note')
                ->schema([
                    Textarea::make('notes')
                        ->label('Internal note')
                        ->rows(3)
                        ->maxLength(2000)
                        ->placeholder(
                            'Optional information about this dispensing event'
                        ),
                ]),
        ];
    }

    private static function prescriptionItemOptions(
        Prescription $prescription,
    ): array {
        return $prescription
            ->items()
            ->whereColumn(
                'quantity_dispensed',
                '<',
                'quantity_prescribed',
            )
            ->orderBy('id')
            ->get()
            ->mapWithKeys(
                function (
                    PrescriptionItem $item,
                ): array {
                    $remaining = max(
                        (float) $item
                            ->quantity_prescribed
                        - (float) $item
                            ->quantity_dispensed,
                        0,
                    );

                    return [
                        $item->id => sprintf(
                            '%s — %s remaining',
                            $item->prescribed_name,
                            number_format(
                                $remaining,
                                3,
                            ),
                        ),
                    ];
                },
            )
            ->all();
    }

    private static function prescriptionItem(
        Prescription $prescription,
        mixed $itemId,
    ): ?PrescriptionItem {
        if (blank($itemId)) {
            return null;
        }

        return $prescription
            ->items()
            ->whereKey((int) $itemId)
            ->first();
    }

    private static function prescriptionItemLabel(
        Prescription $prescription,
        mixed $itemId,
    ): string {
        $item = self::prescriptionItem(
            $prescription,
            $itemId,
        );

        return $item?->prescribed_name
            ?? 'New dispensing item';
    }

    private static function prescribedDetails(
        Prescription $prescription,
        mixed $itemId,
    ): string {
        $item = self::prescriptionItem(
            $prescription,
            $itemId,
        );

        if ($item === null) {
            return 'Select a prescription item';
        }

        return collect([
            $item->strength,
            $item->dosage_form,
            $item->dosage,
            $item->frequency,
            $item->duration,
        ])
            ->filter()
            ->implode(' • ')
            ?: 'No additional instructions';
    }

    private static function remainingQuantity(
        Prescription $prescription,
        mixed $itemId,
    ): float {
        $item = self::prescriptionItem(
            $prescription,
            $itemId,
        );

        if ($item === null) {
            return 0;
        }

        return round(
            max(
                (float) $item->quantity_prescribed
                - (float) $item->quantity_dispensed,
                0,
            ),
            3,
        );
    }

    private static function substitutionText(
        Prescription $prescription,
        mixed $itemId,
    ): string {
        $item = self::prescriptionItem(
            $prescription,
            $itemId,
        );

        if ($item === null) {
            return 'Select a prescription item';
        }

        return $item->substitution_allowed
            ? 'Alternative medicines are allowed'
            : 'Only the approved medicine may be supplied';
    }

    private static function medicineOptions(
        Prescription $prescription,
        mixed $prescriptionItemId,
    ): array {
        $item = self::prescriptionItem(
            $prescription,
            $prescriptionItemId,
        );

        if ($item === null) {
            return [];
        }

        $availableListingIds = MedicineBatch::query()
            ->where(
                'pharmacy_id',
                $prescription->pharmacy_id,
            )
            ->where(
                'pharmacy_branch_id',
                $prescription->pharmacy_branch_id,
            )
            ->where('status', 'active')
            ->where('quantity_available', '>', 0)
            ->whereDate('expiry_date', '>', today())
            ->distinct()
            ->pluck('pharmacy_medicine_id');

        $query = PharmacyMedicine::query()
            ->with('medicine')
            ->where(
                'pharmacy_id',
                $prescription->pharmacy_id,
            )
            ->where('status', 'active')
            ->whereIn('id', $availableListingIds);

        if (! $item->substitution_allowed) {
            if (
                $item->pharmacy_medicine_id !== null
            ) {
                $query->whereKey(
                    $item->pharmacy_medicine_id,
                );
            } elseif ($item->medicine_id !== null) {
                $query->where(
                    'medicine_id',
                    $item->medicine_id,
                );
            } else {
                return [];
            }
        }

        return $query
            ->orderBy('id')
            ->get()
            ->mapWithKeys(
                fn (
                    PharmacyMedicine $listing,
                ): array => [
                    $listing->id => sprintf(
                        '%s — %s BIF',
                        self::medicineName($listing),
                        number_format(
                            (float) $listing
                                ->selling_price,
                            2,
                        ),
                    ),
                ],
            )
            ->all();
    }

    private static function sellingPriceText(
        mixed $listingId,
    ): string {
        if (blank($listingId)) {
            return 'Select a medicine';
        }

        $price = PharmacyMedicine::query()
            ->whereKey((int) $listingId)
            ->where(
                'pharmacy_id',
                auth()->user()?->pharmacy_id ?? 0,
            )
            ->value('selling_price');

        return $price === null
            ? 'Unavailable'
            : number_format(
                (float) $price,
                2,
            ).' BIF';
    }

    private static function availableStockText(
        Prescription $prescription,
        mixed $listingId,
    ): string {
        if (blank($listingId)) {
            return 'Select a medicine';
        }

        $quantity = MedicineBatch::query()
            ->where(
                'pharmacy_id',
                $prescription->pharmacy_id,
            )
            ->where(
                'pharmacy_branch_id',
                $prescription->pharmacy_branch_id,
            )
            ->where(
                'pharmacy_medicine_id',
                (int) $listingId,
            )
            ->where('status', 'active')
            ->where('quantity_available', '>', 0)
            ->whereDate('expiry_date', '>', today())
            ->sum('quantity_available');

        return number_format(
            (float) $quantity,
            3,
        ).' unit(s)';
    }

    private static function medicineName(
        PharmacyMedicine $listing,
    ): string {
        return $listing->medicine?->brand_name
            ?? $listing->medicine?->generic_name
            ?? "Medicine #{$listing->id}";
    }
}