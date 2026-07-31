<?php

namespace App\Filament\Pharmacy\Resources\SupplierPayments\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SupplierPaymentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('payment_date', 'desc')
            ->columns([
                TextColumn::make('payment_number')
                    ->label('Payment number')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('invoice.invoice_number')
                    ->label('Invoice')
                    ->searchable(),

                TextColumn::make('supplier.name')
                    ->label('Supplier')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('payment_date')
                    ->label('Payment date')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('amount')
                    ->numeric(decimalPlaces: 0)
                    ->suffix(' BIF')
                    ->sortable(),

                TextColumn::make('payment_method')
                    ->label('Method')
                    ->formatStateUsing(
                        fn (string $state): string =>
                            str($state)->replace('_', ' ')->title(),
                    )
                    ->badge(),

                TextColumn::make('reference')
                    ->placeholder('—')
                    ->searchable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'voided' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'completed' => 'Completed',
                        'voided' => 'Voided',
                    ]),

                SelectFilter::make('payment_method')
                    ->label('Payment method')
                    ->options([
                        'cash' => 'Cash',
                        'bank_transfer' => 'Bank transfer',
                        'mobile_money' => 'Mobile money',
                        'cheque' => 'Cheque',
                        'card' => 'Card',
                        'other' => 'Other',
                    ]),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('Open'),
            ]);
    }
}