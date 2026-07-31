<?php

namespace App\Filament\Pharmacy\Resources\Suppliers\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SuppliersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Supplier')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('contact_person')
                    ->label('Contact person')
                    ->placeholder('—')
                    ->searchable(),

                TextColumn::make('phone')
                    ->searchable(),

                TextColumn::make('city')
                    ->placeholder('—')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('payment_terms_days')
                    ->label('Payment terms')
                    ->suffix(' days')
                    ->sortable(),

                TextColumn::make('credit_limit')
                    ->label('Credit limit')
                    ->numeric(decimalPlaces: 0)
                    ->suffix(' BIF')
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'inactive' => 'gray',
                        'blocked' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Registered')
                    ->dateTime('d M Y, H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                        'blocked' => 'Blocked',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}