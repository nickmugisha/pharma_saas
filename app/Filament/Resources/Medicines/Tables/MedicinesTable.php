<?php

namespace App\Filament\Resources\Medicines\Tables;

use App\Models\Medicine;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class MedicinesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                ImageColumn::make('primaryImage.path')
                    ->label('Picture')
                    ->disk('public')
                    ->square()
                    ->imageSize(48),

                TextColumn::make('brand_name')
                    ->label('Medicine')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('generic_name')
                    ->label('Generic name')
                    ->searchable()
                    ->placeholder('—'),

                TextColumn::make('strength')
                    ->placeholder('—'),

                TextColumn::make('dosageForm.name')
                    ->label('Form')
                    ->placeholder('—'),

                TextColumn::make('category.name')
                    ->label('Category')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('prescription_status')
                    ->label('Prescription')
                    ->badge()
                    ->formatStateUsing(
                        fn (string $state): string => match ($state) {
                            'otc' => 'OTC',
                            'prescription' => 'Required',
                            'controlled' => 'Controlled',
                            default => $state,
                        },
                    )
                    ->color(fn (string $state): string => match ($state) {
                        'otc' => 'success',
                        'prescription' => 'warning',
                        'controlled' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('approval_status')
                    ->label('Approval')
                    ->badge()
                    ->formatStateUsing(
                        fn (string $state): string =>
                            str($state)->replace('_', ' ')->title(),
                    )
                    ->color(fn (string $state): string => match ($state) {
                        'approved' => 'success',
                        'pending_review' => 'warning',
                        'changes_requested' => 'info',
                        'rejected', 'suspended' => 'danger',
                        'draft' => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('submittedByPharmacy.name')
                    ->label('Submitted by')
                    ->placeholder('Platform administrator')
                    ->toggleable(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('d M Y, H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('approval_status')
                    ->label('Approval')
                    ->options([
                        'draft' => 'Draft',
                        'pending_review' => 'Pending review',
                        'approved' => 'Approved',
                        'changes_requested' => 'Changes requested',
                        'rejected' => 'Rejected',
                        'suspended' => 'Suspended',
                    ]),

                SelectFilter::make('prescription_status')
                    ->label('Prescription')
                    ->options([
                        'otc' => 'Over the counter',
                        'prescription' => 'Prescription required',
                        'controlled' => 'Controlled',
                    ]),

                SelectFilter::make('medicine_category_id')
                    ->label('Category')
                    ->relationship('category', 'name'),

                SelectFilter::make('is_active')
                    ->label('Activity')
                    ->options([
                        1 => 'Active',
                        0 => 'Inactive',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}