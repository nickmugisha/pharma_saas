<?php

namespace App\Filament\Resources\Pharmacies\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PharmaciesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->label('Pharmacy')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('license_number')
                    ->label('Licence')
                    ->searchable()
                    ->placeholder('Not provided'),

                TextColumn::make('phone')
                    ->searchable(),

                TextColumn::make('city')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => 'Pending review',
                        'approved' => 'Approved',
                        'suspended' => 'Suspended',
                        'rejected' => 'Rejected',
                        default => ucfirst($state),
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'success',
                        'suspended' => 'danger',
                        'rejected' => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('approved_at')
                    ->label('Approved')
                    ->dateTime('d M Y, H:i')
                    ->placeholder('Not approved')
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('Registered')
                    ->dateTime('d M Y, H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending review',
                        'approved' => 'Approved',
                        'suspended' => 'Suspended',
                        'rejected' => 'Rejected',
                    ]),

                SelectFilter::make('city')
                    ->options(
                        fn (): array => \App\Models\Pharmacy::query()
                            ->whereNotNull('city')
                            ->distinct()
                            ->orderBy('city')
                            ->pluck('city', 'city')
                            ->all()
                    )
                    ->searchable(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}