<?php

namespace App\Filament\Pharmacy\Resources\PurchaseReceipts\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PurchaseReceiptsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('received_at', 'desc')
            ->columns([
                TextColumn::make('receipt_number')
                    ->label('Receipt')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('purchaseOrder.order_number')
                    ->label('Purchase order')
                    ->searchable(),

                TextColumn::make('supplier.name')
                    ->label('Supplier')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('branch.name')
                    ->label('Receiving branch')
                    ->searchable(),

                TextColumn::make('items_count')
                    ->label('Batch lines')
                    ->counts('items'),

                TextColumn::make('receivedByUser.name')
                    ->label('Received by')
                    ->placeholder('—'),

                TextColumn::make('received_at')
                    ->label('Received')
                    ->dateTime('d M Y H:i')
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'cancelled' => 'danger',
                        default => 'warning',
                    }),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                    ]),
            ])
            ->recordActions([]);
    }
}