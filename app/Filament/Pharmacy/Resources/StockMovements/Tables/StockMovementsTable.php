<?php

namespace App\Filament\Pharmacy\Resources\StockMovements\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class StockMovementsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('occurred_at', 'desc')
            ->columns([
                TextColumn::make('occurred_at')
                    ->label('Date')
                    ->dateTime('d M Y H:i')
                    ->sortable(),

                TextColumn::make(
                    'pharmacyMedicine.medicine.brand_name'
                )
                    ->label('Medicine')
                    ->searchable()
                    ->weight('bold'),

                TextColumn::make('medicineBatch.batch_number')
                    ->label('Batch')
                    ->searchable(),

                TextColumn::make('branch.name')
                    ->label('Branch')
                    ->searchable(),

                TextColumn::make('movement_type')
                    ->label('Movement')
                    ->formatStateUsing(
                        fn (string $state): string =>
                            str($state)->replace('_', ' ')->title(),
                    )
                    ->badge(),

                TextColumn::make('direction')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'in' => 'success',
                        'out' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('quantity')
                    ->numeric(decimalPlaces: 3)
                    ->sortable(),

                TextColumn::make('balance_after')
                    ->label('Balance after')
                    ->numeric(decimalPlaces: 3)
                    ->sortable(),

                TextColumn::make('createdByUser.name')
                    ->label('Recorded by')
                    ->placeholder('System'),
            ])
            ->filters([
                SelectFilter::make('direction')
                    ->options([
                        'in' => 'Stock in',
                        'out' => 'Stock out',
                    ]),

                SelectFilter::make('movement_type')
                    ->label('Movement type')
                    ->options([
                        'purchase_receipt' => 'Purchase receipt',
                        'sale' => 'Sale',
                        'customer_return' => 'Customer return',
                        'supplier_return' => 'Supplier return',
                        'adjustment_in' => 'Adjustment in',
                        'adjustment_out' => 'Adjustment out',
                        'transfer_in' => 'Transfer in',
                        'transfer_out' => 'Transfer out',
                        'expired' => 'Expired',
                        'damaged' => 'Damaged',
                        'reversal' => 'Reversal',
                    ]),
            ])
            ->recordActions([]);
    }
}