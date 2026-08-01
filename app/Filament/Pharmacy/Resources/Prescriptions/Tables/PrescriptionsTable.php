<?php

namespace App\Filament\Pharmacy\Resources\Prescriptions\Tables;

use App\Filament\Pharmacy\Resources\Prescriptions\PrescriptionResource;
use App\Models\Prescription;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PrescriptionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('prescription_number')
                    ->label('Prescription number')
                    ->searchable()
                    ->copyable()
                    ->weight('bold'),

                TextColumn::make('customer.name')
                    ->label('Customer / patient')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('customer.phone')
                    ->label('Phone')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('branch.name')
                    ->label('Branch')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('prescriber_name')
                    ->label('Prescriber')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('issued_at')
                    ->label('Issued')
                    ->date('d M Y')
                    ->placeholder('Not specified')
                    ->sortable(),

                TextColumn::make('valid_until')
                    ->label('Valid until')
                    ->date('d M Y')
                    ->placeholder('Not specified')
                    ->sortable(),

                TextColumn::make('items_count')
                    ->label('Medicines')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('attachments_count')
                    ->label('Files')
                    ->numeric()
                    ->toggleable(),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(
                        fn (string $state): string =>
                            str($state)
                                ->replace('_', ' ')
                                ->title()
                                ->toString(),
                    )
                    ->color(
                        fn (string $state): string =>
                            match ($state) {
                                Prescription::STATUS_DRAFT =>
                                    'gray',

                                Prescription::STATUS_SUBMITTED =>
                                    'info',

                                Prescription::STATUS_UNDER_REVIEW =>
                                    'warning',

                                Prescription::STATUS_APPROVED =>
                                    'success',

                                Prescription::STATUS_REJECTED =>
                                    'danger',

                                Prescription::STATUS_PARTIALLY_DISPENSED =>
                                    'warning',

                                Prescription::STATUS_DISPENSED =>
                                    'success',

                                Prescription::STATUS_CANCELLED =>
                                    'danger',

                                default => 'gray',
                            },
                    )
                    ->sortable(),

                TextColumn::make('source')
                    ->badge()
                    ->formatStateUsing(
                        fn (string $state): string =>
                            str($state)->headline()->toString(),
                    )
                    ->toggleable(),

                TextColumn::make('reviewedByUser.name')
                    ->label('Reviewed by')
                    ->placeholder('Not reviewed')
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        Prescription::STATUS_DRAFT =>
                            'Draft',

                        Prescription::STATUS_SUBMITTED =>
                            'Submitted',

                        Prescription::STATUS_UNDER_REVIEW =>
                            'Under review',

                        Prescription::STATUS_APPROVED =>
                            'Approved',

                        Prescription::STATUS_REJECTED =>
                            'Rejected',

                        Prescription::STATUS_PARTIALLY_DISPENSED =>
                            'Partially dispensed',

                        Prescription::STATUS_DISPENSED =>
                            'Dispensed',

                        Prescription::STATUS_CANCELLED =>
                            'Cancelled',
                    ]),

                SelectFilter::make('source')
                    ->options([
                        'uploaded' => 'Uploaded document',
                        'manual' => 'Manual entry',
                    ]),

                SelectFilter::make('pharmacy_branch_id')
                    ->label('Branch')
                    ->relationship(
                        name: 'branch',
                        titleAttribute: 'name',
                        modifyQueryUsing:
                            fn (Builder $query): Builder =>
                                $query
                                    ->where(
                                        'pharmacy_id',
                                        auth()->user()
                                            ?->pharmacy_id
                                            ?? 0,
                                    )
                                    ->where(
                                        'status',
                                        'active',
                                    ),
                    ),
            ])
            ->recordActions([
                ViewAction::make(),

                EditAction::make()
                    ->visible(
                        fn (Prescription $record): bool =>
                            PrescriptionResource::canEdit(
                                $record,
                            ),
                    ),
            ])
            ->emptyStateHeading(
                'No prescriptions registered'
            )
            ->emptyStateDescription(
                'Uploaded and manually entered prescriptions will appear here.'
            );
    }
}