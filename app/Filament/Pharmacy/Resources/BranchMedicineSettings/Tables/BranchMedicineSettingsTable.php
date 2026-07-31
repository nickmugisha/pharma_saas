<?php

namespace App\Filament\Pharmacy\Resources\BranchMedicineSettings\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BranchMedicineSettingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make(
                    'pharmacyMedicine.medicine.brand_name'
                )
                    ->label('Medicine')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('branch.name')
                    ->label('Branch')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('minimum_stock_level')
                    ->label('Minimum stock')
                    ->numeric(decimalPlaces: 3)
                    ->sortable(),

                TextColumn::make('reorder_quantity')
                    ->label('Reorder quantity')
                    ->numeric(decimalPlaces: 3)
                    ->sortable(),

                TextColumn::make('expiry_warning_days')
                    ->label('Expiry warning')
                    ->suffix(' days')
                    ->sortable(),

                IconColumn::make('alerts_enabled')
                    ->label('Alerts')
                    ->boolean(),

                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('pharmacy_branch_id')
                    ->label('Branch')
                    ->relationship(
                        name: 'branch',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query): Builder =>
                            $query->where(
                                'pharmacy_id',
                                auth()->user()?->pharmacy_id ?? 0,
                            ),
                    ),

                SelectFilter::make('alerts_enabled')
                    ->label('Alert status')
                    ->options([
                        '1' => 'Enabled',
                        '0' => 'Disabled',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}