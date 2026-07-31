<?php

namespace App\Filament\Pharmacy\Resources\PurchaseOrders\Schemas;

use App\Models\PharmacyMedicine;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class PurchaseOrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Purchase information')
    ->columnSpanFull()
    ->columns(2)
                    ->schema([
                        Select::make('supplier_id')
                            ->label('Supplier')
                            ->relationship(
                                name: 'supplier',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn (Builder $query): Builder =>
                                    $query
                                        ->where(
                                            'pharmacy_id',
                                            auth()->user()?->pharmacy_id ?? 0,
                                        )
                                        ->where('status', 'active'),
                            )
                            ->searchable()
                            ->preload()
                            ->required(),

                        Select::make('pharmacy_branch_id')
                            ->label('Receiving branch')
                            ->relationship(
                                name: 'branch',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn (Builder $query): Builder =>
                                    $query
                                        ->where(
                                            'pharmacy_id',
                                            auth()->user()?->pharmacy_id ?? 0,
                                        )
                                        ->where('status', 'active'),
                            )
                            ->default(
                                fn (): ?int =>
                                    auth()->user()?->pharmacy_branch_id,
                            )
                            ->searchable()
                            ->preload()
                            ->required(),

                        DatePicker::make('order_date')
                            ->label('Order date')
                            ->default(today())
                            ->required(),

                        DatePicker::make('expected_delivery_date')
                            ->label('Expected delivery')
                            ->minDate(
                                fn ($get) =>
                                    $get('order_date') ?: today(),
                            ),

                        TextInput::make('shipping_total')
                            ->label('Shipping cost')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->suffix('BIF')
                            ->required(),

                        Select::make('status')
                            ->options([
                                'draft' => 'Draft',
                                'submitted' => 'Submitted',
                                'approved' => 'Approved',
                                'partially_received' => 'Partially received',
                                'received' => 'Received',
                                'cancelled' => 'Cancelled',
                            ])
                            ->default('draft')
                            ->disabled()
                            ->dehydrated(false),

                        Textarea::make('notes')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),

              Section::make('Ordered medicines')
    ->description(
        'Add the medicines and their supplier purchase costs.'
    )
    ->columnSpanFull()
    ->schema([
        Repeater::make('items')
            ->relationship('items')
            ->label('')
            ->schema([
                Select::make('pharmacy_medicine_id')
                    ->label('Medicine')
                    ->relationship(
                        name: 'pharmacyMedicine',
                        titleAttribute: 'internal_sku',
                        modifyQueryUsing:
                            fn (Builder $query): Builder =>
                                $query
                                    ->with('medicine')
                                    ->where(
                                        'pharmacy_id',
                                        auth()->user()?->pharmacy_id ?? 0,
                                    )
                                    ->where('status', 'active')
                                    ->whereHas(
                                        'medicine',
                                        fn (Builder $medicineQuery): Builder =>
                                            $medicineQuery
                                                ->where(
                                                    'approval_status',
                                                    'approved',
                                                )
                                                ->where(
                                                    'is_active',
                                                    true,
                                                ),
                                    ),
                    )
                    ->getOptionLabelFromRecordUsing(
                        fn (PharmacyMedicine $record): string =>
                            collect([
                                $record->medicine?->brand_name,
                                $record->medicine?->strength,
                                $record->internal_sku,
                            ])
                                ->filter()
                                ->implode(' — '),
                    )
                    ->searchable()
                    ->preload()
                    ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                    ->required()
                    ->columnSpanFull(),

                TextInput::make('quantity_ordered')
                    ->label('Quantity')
                    ->numeric()
                    ->step(0.001)
                    ->minValue(0.001)
                    ->required(),

                TextInput::make('unit_cost')
                    ->label('Unit cost')
                    ->numeric()
                    ->minValue(0)
                    ->suffix('BIF')
                    ->required(),

                TextInput::make('discount_amount')
                    ->label('Discount')
                    ->numeric()
                    ->minValue(0)
                    ->default(0)
                    ->suffix('BIF')
                    ->required(),

                TextInput::make('tax_rate')
                    ->label('Tax rate')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100)
                    ->default(0)
                    ->suffix('%')
                    ->required(),

                Textarea::make('notes')
                    ->rows(2)
                    ->columnSpanFull(),
            ])
            ->columns([
                'default' => 1,
                'md' => 2,
            ])
            ->defaultItems(1)
            ->minItems(1)
            ->addActionLabel('Add medicine')
            ->reorderable()
            ->columnSpanFull(),
    ])
                    ->description(
                        'Add the medicines and their supplier purchase costs.'
                    )
                    ->schema([
                        Repeater::make('items')
                            ->relationship('items')
                            ->label('')
                            ->schema([
                                Select::make('pharmacy_medicine_id')
                                    ->label('Medicine')
                                    ->relationship(
                                        name: 'pharmacyMedicine',
                                        titleAttribute: 'internal_sku',
                                        modifyQueryUsing:
                                            fn (Builder $query): Builder =>
                                                $query
                                                    ->with('medicine')
                                                    ->where(
                                                        'pharmacy_id',
                                                        auth()->user()?->pharmacy_id ?? 0,
                                                    )
                                                    ->where('status', 'active')
                                                    ->whereHas(
                                                        'medicine',
                                                        fn (Builder $medicineQuery): Builder =>
                                                            $medicineQuery
                                                                ->where(
                                                                    'approval_status',
                                                                    'approved',
                                                                )
                                                                ->where(
                                                                    'is_active',
                                                                    true,
                                                                ),
                                                    ),
                                    )
                                    ->getOptionLabelFromRecordUsing(
                                        fn (PharmacyMedicine $record): string =>
                                            collect([
                                                $record->medicine?->brand_name,
                                                $record->medicine?->strength,
                                                $record->internal_sku,
                                            ])
                                                ->filter()
                                                ->implode(' — '),
                                    )
                                    ->searchable()
                                    ->preload()
                                    ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                    ->required(),

                                TextInput::make('quantity_ordered')
                                    ->label('Quantity')
                                    ->numeric()
                                    ->step(0.001)
                                    ->minValue(0.001)
                                    ->required(),

                                TextInput::make('unit_cost')
                                    ->label('Unit cost')
                                    ->numeric()
                                    ->minValue(0)
                                    ->suffix('BIF')
                                    ->required(),

                                TextInput::make('discount_amount')
                                    ->label('Discount')
                                    ->numeric()
                                    ->minValue(0)
                                    ->default(0)
                                    ->suffix('BIF')
                                    ->required(),

                                TextInput::make('tax_rate')
                                    ->label('Tax rate')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->default(0)
                                    ->suffix('%')
                                    ->required(),

                                Textarea::make('notes')
                                    ->rows(2)
                                    ->columnSpanFull(),
                            ])
                            ->columns(5)
                            ->defaultItems(1)
                            ->minItems(1)
                            ->addActionLabel('Add medicine')
                            ->reorderable()
                            ->columnSpanFull(),
                    ]),

Section::make('Calculated totals')
    ->description(
        'Totals are recalculated automatically after saving.'
    )
    ->columnSpanFull()
    ->columns([
        'default' => 1,
        'md' => 2,
        'xl' => 4,
    ])                    ->description(
                        'Totals are recalculated automatically after saving.'
                    )
                    ->columns(4)
                    ->schema([
                        TextInput::make('subtotal')
                            ->disabled()
                            ->dehydrated(false)
                            ->suffix('BIF'),

                        TextInput::make('discount_total')
                            ->label('Discount')
                            ->disabled()
                            ->dehydrated(false)
                            ->suffix('BIF'),

                        TextInput::make('tax_total')
                            ->label('Tax')
                            ->disabled()
                            ->dehydrated(false)
                            ->suffix('BIF'),

                        TextInput::make('grand_total')
                            ->label('Grand total')
                            ->disabled()
                            ->dehydrated(false)
                            ->suffix('BIF'),
                    ]),
            ]);
    }
}