<?php

namespace App\Filament\Pharmacy\Resources\SupplierInvoices\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class SupplierInvoiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Invoice information')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        Select::make('purchase_order_id')
                            ->label('Approved purchase order')
                            ->relationship(
                                name: 'purchaseOrder',
                                titleAttribute: 'order_number',
                                modifyQueryUsing: fn (Builder $query): Builder =>
                                    $query
                                        ->where(
                                            'pharmacy_id',
                                            auth()->user()?->pharmacy_id ?? 0,
                                        )
                                        ->where('status', 'approved'),
                            )
                            ->searchable()
                            ->preload()
                            ->required()
                            ->disabledOn('edit'),

                        TextInput::make('invoice_number')
                            ->label('Supplier invoice number')
                            ->placeholder('Example: INV-BMS-2026-001')
                            ->required()
                            ->maxLength(100)
                            ->disabledOn('edit'),

                        DatePicker::make('invoice_date')
                            ->label('Invoice date')
                            ->default(today())
                            ->required(),

                        DatePicker::make('due_date')
                            ->label('Payment due date')
                            ->minDate(
                                fn ($get) =>
                                    $get('invoice_date') ?: today(),
                            ),

                        Textarea::make('notes')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),

                Section::make('Financial summary')
                    ->description(
                        'Amounts are copied automatically from the approved purchase order.'
                    )
                    ->columnSpanFull()
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                        'xl' => 4,
                    ])
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

                        TextInput::make('shipping_total')
                            ->label('Shipping')
                            ->disabled()
                            ->dehydrated(false)
                            ->suffix('BIF'),

                        TextInput::make('grand_total')
                            ->label('Grand total')
                            ->disabled()
                            ->dehydrated(false)
                            ->suffix('BIF'),

                        TextInput::make('paid_amount')
                            ->label('Paid')
                            ->disabled()
                            ->dehydrated(false)
                            ->suffix('BIF'),

                        TextInput::make('balance_due')
                            ->label('Balance due')
                            ->disabled()
                            ->dehydrated(false)
                            ->suffix('BIF'),

                        TextInput::make('status')
                            ->disabled()
                            ->dehydrated(false),
                    ]),
            ]);
    }
}