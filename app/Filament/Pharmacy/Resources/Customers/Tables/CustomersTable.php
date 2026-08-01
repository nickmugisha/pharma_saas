<?php

namespace App\Filament\Pharmacy\Resources\Customers\Tables;

use App\Filament\Pharmacy\Resources\Customers\CustomerResource;
use App\Models\Customer;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CustomersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('registered_at', 'desc')
            ->columns([
                TextColumn::make('customer_number')
                    ->label('Customer number')
                    ->searchable()
                    ->copyable()
                    ->weight('bold'),

                TextColumn::make('name')
                    ->label('Customer')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('phone')
                    ->searchable()
                    ->placeholder('—'),

                TextColumn::make('email')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('registeredBranch.name')
                    ->label('Registration branch')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),

                IconColumn::make('has_patient_profile')
                    ->label('Patient')
                    ->getStateUsing(
                        fn (Customer $record): bool =>
                            $record->patientProfile !== null,
                    )
                    ->boolean(),

                TextColumn::make('sales_count')
                    ->label('Sales')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('activities_count')
                    ->label('Activities')
                    ->numeric()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'inactive' => 'warning',
                        'blocked' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('last_activity_at')
                    ->label('Last activity')
                    ->dateTime('d M Y H:i')
                    ->placeholder('No activity')
                    ->sortable(),

                TextColumn::make('registered_at')
                    ->label('Registered')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                        'blocked' => 'Blocked',
                    ]),

                SelectFilter::make('registered_branch_id')
                    ->label('Registration branch')
                    ->relationship(
                        name: 'registeredBranch',
                        titleAttribute: 'name',
                        modifyQueryUsing:
                            fn (Builder $query): Builder =>
                                $query->where(
                                    'pharmacy_id',
                                    auth()->user()
                                        ?->pharmacy_id
                                        ?? 0,
                                ),
                    ),
            ])
            ->recordActions([
                ViewAction::make(),

                EditAction::make()
                    ->visible(
                        fn (Customer $record): bool =>
                            CustomerResource::canEdit($record),
                    ),
            ])
            ->emptyStateHeading('No customers registered')
            ->emptyStateDescription(
                'Customer accounts will appear here.'
            );
    }
}