<?php

namespace App\Filament\Resources\ClientWallets\Tables;

use App\Models\ClientWallet;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ClientWalletsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('wallet_number')
                    ->label('Wallet')
                    ->searchable()
                    ->copyable()
                    ->weight('bold'),
                TextColumn::make('user.name')
                    ->label('Client')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('user.email')
                    ->label('Email')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('user.clientProfile.phone')
                    ->label('Phone')
                    ->searchable()
                    ->placeholder('Not provided'),
                TextColumn::make('available_balance')
                    ->label('Available balance')
                    ->state(fn (ClientWallet $record): string =>
                        number_format((float) $record->available_balance, 2).' BIF')
                    ->weight('bold'),
                TextColumn::make('transactions_count')
                    ->label('Ledger entries')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string =>
                        $state === 'active' ? 'success' : 'danger')
                    ->formatStateUsing(fn (string $state): string =>
                        str($state)->headline()->toString()),
                TextColumn::make('user.created_at')
                    ->label('Client since')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'suspended' => 'Suspended',
                    ]),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('Open client profile'),
            ]);
    }
}
