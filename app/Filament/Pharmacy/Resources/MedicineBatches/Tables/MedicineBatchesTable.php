<?php

namespace App\Filament\Pharmacy\Resources\MedicineBatches\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class MedicineBatchesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('expiry_date')
            ->columns([
                TextColumn::make(
                    'pharmacyMedicine.medicine.brand_name'
                )
                    ->label('Medicine')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('batch_number')
                    ->label('Batch')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('branch.name')
                    ->label('Branch')
                    ->searchable(),

                TextColumn::make('supplier.name')
                    ->label('Supplier')
                    ->placeholder('—')
                    ->searchable(),

                TextColumn::make('expiry_date')
                    ->label('Expiry')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('quantity_received')
                    ->label('Received')
                    ->numeric(decimalPlaces: 3)
                    ->sortable(),

                TextColumn::make('quantity_available')
                    ->label('Available')
                    ->numeric(decimalPlaces: 3)
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('unit_cost')
                    ->label('Unit cost')
                    ->numeric(decimalPlaces: 0)
                    ->suffix(' BIF')
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'quarantined' => 'warning',
                        'expired', 'recalled' => 'danger',
                        'depleted' => 'gray',
                        default => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'quarantined' => 'Quarantined',
                        'expired' => 'Expired',
                        'depleted' => 'Depleted',
                        'recalled' => 'Recalled',
                    ]),
            ])
            ->recordActions([]);
    }
}