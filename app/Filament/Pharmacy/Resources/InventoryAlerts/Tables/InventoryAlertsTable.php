<?php

namespace App\Filament\Pharmacy\Resources\InventoryAlerts\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InventoryAlertsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('detected_at', 'desc')
            ->columns([
                TextColumn::make('message')
                    ->label('Alert')
                    ->searchable()
                    ->wrap()
                    ->limit(70)
                    ->weight('bold'),

                TextColumn::make(
                    'pharmacyMedicine.medicine.brand_name'
                )
                    ->label('Medicine')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('branch.name')
                    ->label('Branch')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('medicineBatch.batch_number')
                    ->label('Batch')
                    ->placeholder('—')
                    ->searchable(),

                TextColumn::make('alert_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(
                        fn (string $state): string =>
                            str($state)
                                ->replace('_', ' ')
                                ->title(),
                    )
                    ->color(fn (string $state): string => match ($state) {
                        'out_of_stock', 'expired' => 'danger',
                        'low_stock', 'expiring' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('severity')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'critical' => 'danger',
                        'warning' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'open' => 'danger',
                        'acknowledged' => 'warning',
                        'resolved' => 'success',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('detected_at')
                    ->label('Detected')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'open' => 'Open',
                        'acknowledged' => 'Acknowledged',
                        'resolved' => 'Resolved',
                    ]),

                SelectFilter::make('alert_type')
                    ->label('Alert type')
                    ->options([
                        'low_stock' => 'Low stock',
                        'out_of_stock' => 'Out of stock',
                        'expiring' => 'Expiring',
                        'expired' => 'Expired',
                    ]),

                SelectFilter::make('severity')
                    ->options([
                        'warning' => 'Warning',
                        'critical' => 'Critical',
                    ]),

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
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}