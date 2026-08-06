<?php

namespace App\Filament\Resources\WalletFundingRequests\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class WalletFundingRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('requested_at', 'desc')
            ->columns([
                TextColumn::make('request_number')
                    ->label('Request')
                    ->searchable()
                    ->copyable()
                    ->weight('bold'),
                TextColumn::make('user.name')
                    ->label('Client')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('wallet.wallet_number')
                    ->label('Wallet')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('amount')
                    ->formatStateUsing(fn ($state): string =>
                        number_format((float) $state, 2).' BIF')
                    ->weight('bold'),
                TextColumn::make('funding_method')
                    ->label('Method')
                    ->formatStateUsing(fn (string $state): string =>
                        str($state)->headline()),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string =>
                        str($state)->headline()),
                TextColumn::make('requested_at')
                    ->label('Requested')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                        'cancelled' => 'Cancelled',
                    ]),
                SelectFilter::make('funding_method')
                    ->label('Method')
                    ->options([
                        'demo_credit' => 'Demo credit',
                        'cash_deposit' => 'Cash deposit',
                        'mobile_money' => 'Mobile money',
                        'bank_transfer' => 'Bank transfer',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
