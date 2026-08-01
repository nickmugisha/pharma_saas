<?php

namespace App\Filament\Pharmacy\Resources\Sales\Schemas;

use App\Models\MedicineBatch;
use App\Models\PharmacyBranch;
use App\Models\PharmacyMedicine;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class SaleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Sale information')
                    ->description(
                        'Select the selling branch and optionally enter customer information.'
                    )
                    ->columnSpanFull()
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                    ])
                    ->schema([
                        Select::make('pharmacy_branch_id')
                            ->label('Selling branch')
                            ->options(
                                fn (): array => PharmacyBranch::query()
                                    ->where(
                                        'pharmacy_id',
                                        auth()->user()?->pharmacy_id ?? 0,
                                    )
                                    ->where('status', 'active')
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                                    ->all(),
                            )
                            ->default(
                                fn (): ?int =>
                                    auth()->user()?->pharmacy_branch_id,
                            )
                            ->required()
                            ->searchable()
                            ->preload()
                            ->live(),

                        TextInput::make('customer_name')
                            ->label('Customer name')
                            ->maxLength(150)
                            ->placeholder('Walk-in customer'),

                        TextInput::make('customer_phone')
                            ->label('Customer phone')
                            ->tel()
                            ->maxLength(50)
                            ->placeholder('+257 ...'),

                        Textarea::make('notes')
                            ->label('Sale notes')
                            ->rows(3)
                            ->maxLength(2000)
                            ->columnSpanFull(),
                    ]),

                Section::make('Medicines')
                    ->description(
                        'The system validates stock and deducts batches using FEFO when the sale is completed.'
                    )
                    ->columnSpanFull()
                    ->schema([
                        Repeater::make('items')
                            ->label('Sale items')
                            ->schema([
                                Select::make('pharmacy_medicine_id')
                                    ->label('Medicine')
                                    ->options(
                                        fn (): array =>
                                            static::medicineOptions(),
                                    )
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->live()
                                    ->disableOptionsWhenSelectedInSiblingRepeaterItems(),

                                TextInput::make('quantity')
                                    ->numeric()
                                    ->required()
                                    ->minValue(0.001)
                                    ->step(0.001)
                                    ->default(1)
                                    ->live(),

                                TextInput::make('discount_amount')
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

                                Placeholder::make('unit_price_display')
                                    ->label('Selling price')
                                    ->content(
                                        fn (Get $get): string =>
                                            static::sellingPriceText(
                                                $get(
                                                    'pharmacy_medicine_id'
                                                ),
                                            ),
                                    ),

                                Placeholder::make(
                                    'available_stock_display'
                                )
                                    ->label('Available stock')
                                    ->content(
                                        fn (Get $get): string =>
                                            static::availableStockText(
                                                listingId: $get(
                                                    'pharmacy_medicine_id'
                                                ),
                                                branchId: $get(
                                                    '../../pharmacy_branch_id'
                                                ),
                                            ),
                                    ),

                                Textarea::make('notes')
                                    ->label('Item note')
                                    ->rows(2)
                                    ->maxLength(1000)
                                    ->columnSpanFull(),
                            ])
                            ->columns([
                                'default' => 1,
                                'md' => 2,
                                'xl' => 3,
                            ])
                            ->defaultItems(1)
                            ->minItems(1)
                            ->maxItems(50)
                            ->itemNumbers()
                            ->reorderable(false)
                            ->addActionLabel('Add another medicine')
                            ->itemLabel(
                                fn (array $state): string =>
                                    static::selectedMedicineLabel(
                                        $state[
                                            'pharmacy_medicine_id'
                                        ] ?? null,
                                    ),
                            ),
                    ]),

                Section::make('Payments')
                    ->description(
                        'The total payment must cover the sale total. Only cash payments may generate change.'
                    )
                    ->columnSpanFull()
                    ->schema([
                        Repeater::make('payments')
                            ->label('Payments received')
                            ->schema([
                                Select::make('payment_method')
                                    ->label('Payment method')
                                    ->options([
                                        'cash' => 'Cash',
                                        'mobile_money' => 'Mobile money',
                                        'bank_transfer' => 'Bank transfer',
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
                                    ->label('Payment reference')
                                    ->maxLength(150)
                                    ->placeholder(
                                        'Transaction or transfer reference'
                                    )
                                    ->visible(
                                        fn (Get $get): bool =>
                                            $get('payment_method')
                                                !== 'cash',
                                    ),

                                Textarea::make('notes')
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
                            ->addActionLabel('Add another payment'),

                        Placeholder::make('estimated_sale_total')
                            ->label('Estimated sale total')
                            ->content(
                                fn (Get $get): string =>
                                    number_format(
                                        static::calculateSaleTotal(
                                            $get('items') ?? [],
                                        ),
                                        2,
                                    ).' BIF',
                            ),

                        Placeholder::make('payment_total')
                            ->label('Payment total')
                            ->content(
                                fn (Get $get): string =>
                                    number_format(
                                        static::calculatePaymentTotal(
                                            $get('payments') ?? [],
                                        ),
                                        2,
                                    ).' BIF',
                            ),

                        Placeholder::make('payment_balance')
                            ->label('Payment balance')
                            ->content(
                                fn (Get $get): string =>
                                    static::paymentBalanceText(
                                        items:
                                            $get('items') ?? [],
                                        payments:
                                            $get('payments') ?? [],
                                    ),
                            ),
                    ]),
            ]);
    }

    private static function medicineOptions(): array
    {
        return PharmacyMedicine::query()
            ->with('medicine')
            ->where(
                'pharmacy_id',
                auth()->user()?->pharmacy_id ?? 0,
            )
            ->where('status', 'active')
            ->orderBy('id')
            ->get()
            ->mapWithKeys(
                fn (PharmacyMedicine $listing): array => [
                    $listing->id => sprintf(
                        '%s — %s BIF',
                        static::medicineName($listing),
                        number_format(
                            (float) $listing->selling_price,
                            2,
                        ),
                    ),
                ],
            )
            ->all();
    }

    private static function selectedMedicineLabel(
        mixed $listingId,
    ): string {
        if (blank($listingId)) {
            return 'New medicine';
        }

        $listing = PharmacyMedicine::query()
            ->with('medicine')
            ->whereKey((int) $listingId)
            ->where(
                'pharmacy_id',
                auth()->user()?->pharmacy_id ?? 0,
            )
            ->first();

        return $listing
            ? static::medicineName($listing)
            : 'Selected medicine';
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

        if ($price === null) {
            return 'Unavailable';
        }

        return number_format((float) $price, 2).' BIF';
    }

    private static function availableStockText(
        mixed $listingId,
        mixed $branchId,
    ): string {
        if (blank($listingId) || blank($branchId)) {
            return 'Select a branch and medicine';
        }

        $listingExists = PharmacyMedicine::query()
            ->whereKey((int) $listingId)
            ->where(
                'pharmacy_id',
                auth()->user()?->pharmacy_id ?? 0,
            )
            ->where('status', 'active')
            ->exists();

        if (! $listingExists) {
            return 'Unavailable';
        }

        $quantity = MedicineBatch::query()
            ->where(
                'pharmacy_id',
                auth()->user()?->pharmacy_id ?? 0,
            )
            ->where(
                'pharmacy_branch_id',
                (int) $branchId,
            )
            ->where(
                'pharmacy_medicine_id',
                (int) $listingId,
            )
            ->where('status', 'active')
            ->where('quantity_available', '>', 0)
            ->whereDate('expiry_date', '>', today())
            ->sum('quantity_available');

        return number_format((float) $quantity, 3).' unit(s)';
    }

    public static function calculateSaleTotal(
        array $items,
    ): float {
        $total = 0.0;

        foreach ($items as $item) {
            $listingId = (int) (
                $item['pharmacy_medicine_id'] ?? 0
            );

            if ($listingId <= 0) {
                continue;
            }

            $price = PharmacyMedicine::query()
                ->whereKey($listingId)
                ->where(
                    'pharmacy_id',
                    auth()->user()?->pharmacy_id ?? 0,
                )
                ->value('selling_price');

            if ($price === null) {
                continue;
            }

            $quantity = max(
                (float) ($item['quantity'] ?? 0),
                0,
            );

            $gross = round(
                (float) $price * $quantity,
                2,
            );

            $discount = min(
                max(
                    (float) (
                        $item['discount_amount'] ?? 0
                    ),
                    0,
                ),
                $gross,
            );

            $taxRate = min(
                max(
                    (float) ($item['tax_rate'] ?? 0),
                    0,
                ),
                100,
            );

            $taxable = round(
                $gross - $discount,
                2,
            );

            $tax = round(
                $taxable * ($taxRate / 100),
                2,
            );

            $total = round(
                $total + $taxable + $tax,
                2,
            );
        }

        return $total;
    }

    public static function calculatePaymentTotal(
        array $payments,
    ): float {
        return round(
            collect($payments)->sum(
                fn (array $payment): float =>
                    max(
                        (float) ($payment['amount'] ?? 0),
                        0,
                    ),
            ),
            2,
        );
    }

    public static function paymentBalanceText(
        array $items,
        array $payments,
    ): string {
        $saleTotal = static::calculateSaleTotal($items);

        $paymentTotal =
            static::calculatePaymentTotal($payments);

        $difference = round(
            $paymentTotal - $saleTotal,
            2,
        );

        if ($saleTotal <= 0) {
            return 'Add sale items';
        }

        if ($difference < 0) {
            return number_format(
                abs($difference),
                2,
            ).' BIF remaining';
        }

        if ($difference > 0) {
            return number_format(
                $difference,
                2,
            ).' BIF potential change';
        }

        return 'Fully covered';
    }

    private static function medicineName(
        PharmacyMedicine $listing,
    ): string {
        return $listing->medicine?->brand_name
            ?? $listing->medicine?->generic_name
            ?? "Medicine #{$listing->id}";
    }
}