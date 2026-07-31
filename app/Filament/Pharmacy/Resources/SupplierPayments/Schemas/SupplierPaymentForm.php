<?php

namespace App\Filament\Pharmacy\Resources\SupplierPayments\Schemas;

use App\Models\SupplierInvoice;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class SupplierPaymentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Payment information')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        Select::make('supplier_invoice_id')
                            ->label('Supplier invoice')
                            ->relationship(
                                name: 'invoice',
                                titleAttribute: 'invoice_number',
                                modifyQueryUsing: fn (Builder $query): Builder =>
                                    $query
                                        ->with('supplier')
                                        ->where(
                                            'pharmacy_id',
                                            auth()->user()?->pharmacy_id ?? 0,
                                        )
                                        ->whereIn('status', [
                                            'unpaid',
                                            'partially_paid',
                                            'overdue',
                                        ])
                                        ->where('balance_due', '>', 0),
                            )
                            ->getOptionLabelFromRecordUsing(
                                fn (SupplierInvoice $record): string =>
                                    sprintf(
                                        '%s — %s — Balance: %s BIF',
                                        $record->invoice_number,
                                        $record->supplier?->name ?? 'Supplier',
                                        number_format(
                                            (float) $record->balance_due,
                                            0,
                                        ),
                                    ),
                            )
                            ->searchable()
                            ->preload()
                            ->required()
                            ->disabledOn('edit'),

                        DatePicker::make('payment_date')
                            ->label('Payment date')
                            ->default(today())
                            ->required()
                            ->disabledOn('edit'),

                        TextInput::make('amount')
                            ->numeric()
                            ->minValue(1)
                            ->suffix('BIF')
                            ->required()
                            ->disabledOn('edit'),

                        Select::make('payment_method')
                            ->label('Payment method')
                            ->options([
                                'cash' => 'Cash',
                                'bank_transfer' => 'Bank transfer',
                                'mobile_money' => 'Mobile money',
                                'cheque' => 'Cheque',
                                'card' => 'Card',
                                'other' => 'Other',
                            ])
                            ->required()
                            ->disabledOn('edit'),

                        TextInput::make('reference')
                            ->label('Transaction reference')
                            ->maxLength(150)
                            ->disabledOn('edit'),

                        TextInput::make('status')
                            ->disabled()
                            ->dehydrated(false),

                        Textarea::make('notes')
                            ->rows(3)
                            ->columnSpanFull()
                            ->disabledOn('edit'),

                        Textarea::make('void_reason')
                            ->label('Void reason')
                            ->rows(3)
                            ->columnSpanFull()
                            ->disabled()
                            ->dehydrated(false)
                            ->visible(
                                fn (?object $record): bool =>
                                    $record?->status === 'voided',
                            ),
                    ]),
            ]);
    }
}