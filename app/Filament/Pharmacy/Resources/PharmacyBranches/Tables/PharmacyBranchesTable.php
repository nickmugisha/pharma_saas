<?php

namespace App\Filament\Pharmacy\Resources\PharmacyBranches\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PharmacyBranchesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('is_main', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->label('Branch')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('code')
                    ->label('Code')
                    ->badge()
                    ->searchable(),

                IconColumn::make('is_main')
                    ->label('Main')
                    ->boolean(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'inactive' => 'gray',
                        'suspended' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('city')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),

                TextColumn::make('province')
                    ->searchable()
                    ->placeholder('—'),

                TextColumn::make('phone')
                    ->placeholder('—'),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('d M Y, H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                        'suspended' => 'Suspended',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}