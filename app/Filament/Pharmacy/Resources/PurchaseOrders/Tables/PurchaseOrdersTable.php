<?php

namespace App\Filament\Pharmacy\Resources\PurchaseOrders\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PurchaseOrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('order_number')
                    ->label('Order number')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('supplier.name')
                    ->label('Supplier')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('branch.name')
                    ->label('Receiving branch')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('order_date')
                    ->label('Order date')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('expected_delivery_date')
                    ->label('Expected delivery')
                    ->date('d M Y')
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('items_count')
                    ->label('Items')
                    ->counts('items')
                    ->badge(),

                TextColumn::make('grand_total')
                    ->label('Total')
                    ->numeric(decimalPlaces: 0)
                    ->suffix(' BIF')
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(
                        fn (string $state): string =>
                            str($state)->replace('_', ' ')->title(),
                    )
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'submitted' => 'warning',
                        'approved' => 'info',
                        'partially_received' => 'warning',
                        'received' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('d M Y, H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'submitted' => 'Submitted',
                        'approved' => 'Approved',
                        'partially_received' => 'Partially received',
                        'received' => 'Received',
                        'cancelled' => 'Cancelled',
                    ]),

                SelectFilter::make('supplier_id')
                    ->label('Supplier')
                    ->relationship('supplier', 'name'),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}