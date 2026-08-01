<?php

namespace App\Filament\Pharmacy\Resources\Sales\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SalesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sold_at', 'desc')
            ->columns([
                TextColumn::make('sale_number')
                    ->label('Sale number')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold'),

                TextColumn::make('receipt_number')
                    ->label('Receipt')
                    ->searchable()
                    ->copyable()
                    ->placeholder('—'),

                TextColumn::make('sold_at')
                    ->label('Sale date')
                    ->dateTime('d M Y H:i')
                    ->sortable(),

                TextColumn::make('branch.name')
                    ->label('Branch')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('customer_name')
                    ->label('Customer')
                    ->searchable()
                    ->placeholder('Walk-in customer'),

                TextColumn::make('grand_total')
                    ->label('Total')
                    ->formatStateUsing(
                        fn ($state): string =>
                            number_format(
                                (float) $state,
                                2,
                            ).' BIF',
                    )
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(
                        fn (string $state): string =>
                            str($state)
                                ->replace('_', ' ')
                                ->title(),
                    )
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'voided' => 'danger',
                        'draft' => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('payment_status')
                    ->label('Payment')
                    ->badge()
                    ->formatStateUsing(
                        fn (string $state): string =>
                            str($state)
                                ->replace('_', ' ')
                                ->title(),
                    )
                    ->color(fn (string $state): string => match ($state) {
                        'paid' => 'success',
                        'partially_paid' => 'warning',
                        'unpaid' => 'danger',
                        'refunded' => 'info',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('cashier.name')
                    ->label('Cashier')
                    ->searchable()
                    ->placeholder('System'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'completed' => 'Completed',
                        'voided' => 'Voided',
                    ]),

                SelectFilter::make('payment_status')
                    ->label('Payment status')
                    ->options([
                        'unpaid' => 'Unpaid',
                        'partially_paid' => 'Partially paid',
                        'paid' => 'Paid',
                        'refunded' => 'Refunded',
                    ]),

                SelectFilter::make('pharmacy_branch_id')
                    ->label('Branch')
                    ->relationship(
                        name: 'branch',
                        titleAttribute: 'name',
                        modifyQueryUsing:
                            fn (Builder $query): Builder =>
                                $query->where(
                                    'pharmacy_id',
                                    auth()->user()?->pharmacy_id
                                        ?? 0,
                                ),
                    ),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('View receipt'),
            ])
            ->emptyStateHeading('No POS sales recorded')
            ->emptyStateDescription(
                'Completed POS sales will appear here.'
            );
    }
}