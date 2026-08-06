<?php

namespace App\Filament\Pharmacy\Resources\MarketplaceOrders\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MarketplaceOrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('placed_at', 'desc')
            ->columns([
                TextColumn::make('order_number')
                    ->label('Order')
                    ->searchable()
                    ->copyable()
                    ->weight('bold'),

                TextColumn::make('placed_at')
                    ->label('Placed')
                    ->dateTime('d M Y H:i')
                    ->sortable(),

                TextColumn::make('client_name')
                    ->label('Client')
                    ->searchable(),

                TextColumn::make('branch.name')
                    ->label('Branch')
                    ->searchable(),

                TextColumn::make('fulfillment_method')
                    ->label('Fulfilment')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => str($state)->headline()),

                TextColumn::make('grand_total')
                    ->label('Total')
                    ->formatStateUsing(fn ($state): string =>
                        number_format((float) $state, 2).' BIF')
                    ->weight('bold'),

                TextColumn::make('payment_status')
                    ->label('Payment')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => str($state)->headline())
                    ->color(fn (string $state): string => match ($state) {
                        'paid' => 'success',
                        'refunded' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('prescription_status')
                    ->label('Prescription')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => str($state)->headline())
                    ->color(fn (string $state): string => match ($state) {
                        'approved', 'not_required' => 'success',
                        'pending_review' => 'warning',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => str($state)->headline())
                    ->color(fn (string $state): string => match ($state) {
                        'awaiting_prescription_review' => 'warning',
                        'awaiting_wallet_payment' => 'info',
                        'confirmed' => 'success',
                        'cancelled', 'expired' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('reservation_expires_at')
                    ->label('Reservation expiry')
                    ->dateTime('d M Y H:i')
                    ->placeholder('Not reserved'),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    'awaiting_prescription_review' => 'Awaiting prescription review',
                    'awaiting_wallet_payment' => 'Awaiting wallet payment',
                    'confirmed' => 'Confirmed',
                    'cancelled' => 'Cancelled',
                    'expired' => 'Expired',
                ]),

                SelectFilter::make('pharmacy_branch_id')
                    ->label('Branch')
                    ->relationship(
                        name: 'branch',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query): Builder =>
                            $query->where('pharmacy_id', auth()->user()?->pharmacy_id ?? 0),
                    ),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->emptyStateHeading('No online orders yet')
            ->emptyStateDescription(
                'Marketplace reservations and prescription review requests will appear here.'
            );
    }
}
