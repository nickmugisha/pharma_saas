<?php

namespace App\Filament\Pharmacy\Resources\MarketplaceOffers\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class MarketplaceOffersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('pharmacyMedicine.medicine.brand_name')
                    ->label('Medicine')
                    ->searchable()
                    ->weight('bold'),

                TextColumn::make('pharmacyMedicine.medicine.strength')
                    ->label('Strength')
                    ->placeholder('—'),

                TextColumn::make('branch.name')
                    ->label('Branch')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('online_price')
                    ->label('Branch price')
                    ->formatStateUsing(fn ($state): string => $state === null
                        ? 'Uses listing price'
                        : number_format((float) $state, 2).' BIF'),

                IconColumn::make('pickup_enabled')
                    ->label('Pickup')
                    ->boolean(),

                IconColumn::make('delivery_enabled')
                    ->label('Delivery')
                    ->boolean(),

                TextColumn::make('delivery_fee')
                    ->label('Delivery fee')
                    ->formatStateUsing(fn ($state): string =>
                        number_format((float) $state, 2).' BIF'),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string =>
                        $state === 'active' ? 'success' : 'gray'),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    'active' => 'Active online',
                    'inactive' => 'Hidden',
                ]),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->emptyStateHeading('No branch marketplace offers')
            ->emptyStateDescription(
                'Visible pharmacy listings still use their default price. Add an offer to configure branch-specific price, pickup or delivery.'
            );
    }
}
