<?php

namespace App\Filament\Pharmacy\Resources\PharmacyMedicines\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PharmacyMedicinesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                ImageColumn::make('medicine.primaryImage.path')
                    ->label('Picture')
                    ->disk('public')
                    ->square()
                    ->imageSize(48),

                TextColumn::make('medicine.brand_name')
                    ->label('Medicine')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('medicine.generic_name')
                    ->label('Generic name')
                    ->placeholder('—')
                    ->searchable(),

                TextColumn::make('medicine.strength')
                    ->label('Strength')
                    ->placeholder('—'),

                TextColumn::make('internal_sku')
                    ->label('SKU')
                    ->badge()
                    ->placeholder('—')
                    ->searchable(),

                TextColumn::make('selling_price')
                    ->label('Selling price')
                    ->numeric(decimalPlaces: 0)
                    ->suffix(' BIF')
                    ->sortable(),

                TextColumn::make('online_price')
                    ->label('Online price')
                    ->numeric(decimalPlaces: 0)
                    ->suffix(' BIF')
                    ->placeholder('Uses selling price')
                    ->sortable(),

                IconColumn::make('is_available')
                    ->label('Available')
                    ->boolean()
                    ->sortable(),

                IconColumn::make('is_visible_online')
                    ->label('Online')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'inactive' => 'gray',
                        'suspended' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Added')
                    ->dateTime('d M Y, H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                        'suspended' => 'Suspended',
                    ]),

                SelectFilter::make('is_available')
                    ->label('Availability')
                    ->options([
                        1 => 'Available',
                        0 => 'Unavailable',
                    ]),

                SelectFilter::make('is_visible_online')
                    ->label('Online visibility')
                    ->options([
                        1 => 'Visible online',
                        0 => 'Not visible online',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}