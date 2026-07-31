<?php

namespace App\Filament\Pharmacy\Resources\SupplierInvoices\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SupplierInvoicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('invoice_date', 'desc')
            ->columns([
                TextColumn::make('invoice_number')
                    ->label('Invoice number')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('purchaseOrder.order_number')
                    ->label('Purchase order')
                    ->searchable(),

                TextColumn::make('supplier.name')
                    ->label('Supplier')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('invoice_date')
                    ->label('Invoice date')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('due_date')
                    ->label('Due date')
                    ->date('d M Y')
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('grand_total')
                    ->label('Total')
                    ->numeric(decimalPlaces: 0)
                    ->suffix(' BIF')
                    ->sortable(),

                TextColumn::make('paid_amount')
                    ->label('Paid')
                    ->numeric(decimalPlaces: 0)
                    ->suffix(' BIF')
                    ->sortable(),

                TextColumn::make('balance_due')
                    ->label('Balance')
                    ->numeric(decimalPlaces: 0)
                    ->suffix(' BIF')
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(
                        fn (string $state): string =>
                            str($state)->replace('_', ' ')->title(),
                    )
                    ->color(fn (string $state): string => match ($state) {
                        'unpaid' => 'warning',
                        'partially_paid' => 'info',
                        'paid' => 'success',
                        'overdue', 'cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'unpaid' => 'Unpaid',
                        'partially_paid' => 'Partially paid',
                        'paid' => 'Paid',
                        'overdue' => 'Overdue',
                        'cancelled' => 'Cancelled',
                    ]),

                SelectFilter::make('supplier_id')
                    ->label('Supplier')
                    ->relationship('supplier', 'name'),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}