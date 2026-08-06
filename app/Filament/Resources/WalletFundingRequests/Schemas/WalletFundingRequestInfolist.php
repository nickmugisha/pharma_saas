<?php

namespace App\Filament\Resources\WalletFundingRequests\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class WalletFundingRequestInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Funding request')
                ->columnSpanFull()
                ->columns([
                    'default' => 1,
                    'md' => 2,
                    'xl' => 4,
                ])
                ->schema([
                    TextEntry::make('request_number')
                        ->label('Request number')
                        ->copyable()
                        ->weight('bold'),
                    TextEntry::make('status')
                        ->badge()
                        ->formatStateUsing(fn (string $state): string =>
                            str($state)->headline()),
                    TextEntry::make('amount')
                        ->formatStateUsing(fn ($state): string =>
                            number_format((float) $state, 2).' BIF')
                        ->weight('bold'),
                    TextEntry::make('funding_method')
                        ->label('Funding method')
                        ->formatStateUsing(fn (string $state): string =>
                            str($state)->headline()),
                    TextEntry::make('user.name')
                        ->label('Client'),
                    TextEntry::make('user.email')
                        ->label('Email')
                        ->copyable(),
                    TextEntry::make('wallet.wallet_number')
                        ->label('Wallet')
                        ->copyable(),
                    TextEntry::make('external_reference')
                        ->label('External reference')
                        ->placeholder('Not provided'),
                    TextEntry::make('requested_at')
                        ->label('Requested')
                        ->dateTime('d M Y H:i'),
                    TextEntry::make('reviewedByUser.name')
                        ->label('Reviewed by')
                        ->placeholder('Pending'),
                    TextEntry::make('reviewed_at')
                        ->label('Reviewed')
                        ->dateTime('d M Y H:i')
                        ->placeholder('Pending'),
                    TextEntry::make('walletTransaction.transaction_number')
                        ->label('Ledger transaction')
                        ->copyable()
                        ->placeholder('Not posted'),
                    TextEntry::make('notes')
                        ->placeholder('No note')
                        ->columnSpanFull(),
                    TextEntry::make('rejection_reason')
                        ->label('Rejection reason')
                        ->placeholder('Not rejected')
                        ->columnSpanFull(),
                ]),
        ]);
    }
}
