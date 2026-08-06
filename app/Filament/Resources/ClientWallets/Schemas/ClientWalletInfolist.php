<?php

namespace App\Filament\Resources\ClientWallets\Schemas;

use App\Models\ClientWallet;
use App\Models\LoginHistory;
use App\Models\MarketplaceOrder;
use App\Models\WalletFundingRequest;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Collection;

class ClientWalletInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Client account')
                ->description('Platform identity, contact information and account lifecycle.')
                ->columnSpanFull()
                ->columns([
                    'default' => 1,
                    'md' => 2,
                    'xl' => 4,
                ])
                ->schema([
                    TextEntry::make('user.name')
                        ->label('Client name')
                        ->weight('bold'),
                    TextEntry::make('user.email')
                        ->label('Email address')
                        ->copyable(),
                    TextEntry::make('user.clientProfile.phone')
                        ->label('Phone number')
                        ->placeholder('Not provided')
                        ->copyable(),
                    TextEntry::make('user.created_at')
                        ->label('Account created')
                        ->dateTime('d M Y H:i'),
                    TextEntry::make('user.email_verified_at')
                        ->label('Email verified')
                        ->dateTime('d M Y H:i')
                        ->placeholder('Not verified'),
                    TextEntry::make('user.last_login_at')
                        ->label('Last sign-in')
                        ->dateTime('d M Y H:i')
                        ->placeholder('No successful sign-in recorded'),
                    TextEntry::make('user.clientProfile.status')
                        ->label('Client profile')
                        ->badge()
                        ->formatStateUsing(fn (?string $state): string =>
                            filled($state) ? str($state)->headline()->toString() : 'Unavailable'),
                    TextEntry::make('user.clientProfile.last_seen_at')
                        ->label('Last marketplace activity')
                        ->dateTime('d M Y H:i')
                        ->placeholder('Not recorded'),
                ]),

            Section::make('Wallet summary')
                ->description('The balance is calculated from immutable credits and debits. It is never edited directly.')
                ->columnSpanFull()
                ->columns([
                    'default' => 1,
                    'md' => 2,
                    'xl' => 4,
                ])
                ->schema([
                    TextEntry::make('wallet_number')
                        ->label('Wallet number')
                        ->copyable()
                        ->weight('bold'),
                    TextEntry::make('available_balance')
                        ->label('Available balance')
                        ->state(fn (ClientWallet $record): string =>
                            number_format((float) $record->available_balance, 2).' BIF')
                        ->weight('bold'),
                    TextEntry::make('status')
                        ->badge()
                        ->formatStateUsing(fn (string $state): string =>
                            str($state)->headline()->toString())
                        ->color(fn (string $state): string =>
                            $state === 'active' ? 'success' : 'danger'),
                    TextEntry::make('currency'),
                    TextEntry::make('transactions_count')
                        ->label('Ledger entries')
                        ->numeric(),
                    TextEntry::make('funding_requests_count')
                        ->label('Funding requests')
                        ->numeric(),
                    TextEntry::make('activated_at')
                        ->label('Wallet activated')
                        ->dateTime('d M Y H:i'),
                    TextEntry::make('suspension_reason')
                        ->label('Suspension reason')
                        ->placeholder('Not suspended')
                        ->columnSpanFull(),
                ]),

            Section::make('Recent purchases')
                ->description('Marketplace purchases made across all partner pharmacies.')
                ->columnSpanFull()
                ->visible(fn (ClientWallet $record): bool =>
                    $record->user->marketplaceOrders()->exists())
                ->schema([
                    RepeatableEntry::make('recent_orders')
                        ->label('')
                        ->state(fn (ClientWallet $record): array =>
                            $record->user
                                ->marketplaceOrders()
                                ->with('pharmacy')
                                ->latest('placed_at')
                                ->limit(12)
                                ->get()
                                ->map(fn (MarketplaceOrder $order): array => [
                                    'order_number' => $order->order_number,
                                    'pharmacy' => $order->pharmacy?->name ?? 'Partner pharmacy',
                                    'status' => $order->status,
                                    'payment_status' => $order->payment_status,
                                    'grand_total' => $order->grand_total,
                                    'placed_at' => $order->placed_at,
                                ])
                                ->all())
                        ->columns([
                            'default' => 1,
                            'md' => 2,
                            'xl' => 5,
                        ])
                        ->schema([
                            TextEntry::make('order_number')
                                ->label('Order')
                                ->copyable()
                                ->weight('bold'),
                            TextEntry::make('pharmacy')
                                ->label('Pharmacy'),
                            TextEntry::make('status')
                                ->badge()
                                ->formatStateUsing(fn (string $state): string =>
                                    str($state)->headline()->toString()),
                            TextEntry::make('payment_status')
                                ->label('Payment')
                                ->badge()
                                ->formatStateUsing(fn (string $state): string =>
                                    str($state)->headline()->toString()),
                            TextEntry::make('grand_total')
                                ->label('Total')
                                ->formatStateUsing(fn ($state): string =>
                                    number_format((float) $state, 2).' BIF'),
                            TextEntry::make('placed_at')
                                ->label('Placed')
                                ->dateTime('d M Y H:i'),
                        ]),
                ]),

            Section::make('Funding requests')
                ->description('Requests submitted by the client and their review outcome.')
                ->columnSpanFull()
                ->visible(fn (ClientWallet $record): bool =>
                    $record->fundingRequests()->exists())
                ->schema([
                    RepeatableEntry::make('funding_history')
                        ->label('')
                        ->state(fn (ClientWallet $record): array =>
                            $record->fundingRequests()
                                ->with('reviewedByUser')
                                ->latest('requested_at')
                                ->limit(12)
                                ->get()
                                ->map(fn (WalletFundingRequest $request): array => [
                                    'request_number' => $request->request_number,
                                    'amount' => $request->amount,
                                    'funding_method' => $request->funding_method,
                                    'status' => $request->status,
                                    'requested_at' => $request->requested_at,
                                    'reviewer' => $request->reviewedByUser?->name,
                                    'rejection_reason' => $request->rejection_reason,
                                ])
                                ->all())
                        ->columns([
                            'default' => 1,
                            'md' => 2,
                            'xl' => 5,
                        ])
                        ->schema([
                            TextEntry::make('request_number')
                                ->label('Request')
                                ->copyable()
                                ->weight('bold'),
                            TextEntry::make('amount')
                                ->formatStateUsing(fn ($state): string =>
                                    number_format((float) $state, 2).' BIF'),
                            TextEntry::make('funding_method')
                                ->label('Method')
                                ->formatStateUsing(fn (?string $state): string =>
                                    filled($state) ? str($state)->headline()->toString() : 'Not specified'),
                            TextEntry::make('status')
                                ->badge()
                                ->formatStateUsing(fn (string $state): string =>
                                    str($state)->headline()->toString()),
                            TextEntry::make('requested_at')
                                ->label('Requested')
                                ->dateTime('d M Y H:i'),
                            TextEntry::make('reviewer')
                                ->label('Reviewed by')
                                ->placeholder('Pending review'),
                            TextEntry::make('rejection_reason')
                                ->label('Review note')
                                ->placeholder('No rejection note')
                                ->columnSpanFull(),
                        ]),
                ]),

            Section::make('Immutable wallet ledger')
                ->description('Every credit, debit, payment, refund, adjustment and reversal is permanently retained.')
                ->columnSpanFull()
                ->schema([
                    RepeatableEntry::make('transactions')
                        ->label('')
                        ->columns([
                            'default' => 1,
                            'md' => 2,
                            'xl' => 4,
                        ])
                        ->schema([
                            TextEntry::make('transaction_number')
                                ->label('Transaction')
                                ->copyable()
                                ->weight('bold'),
                            TextEntry::make('type')
                                ->badge()
                                ->formatStateUsing(fn (string $state): string =>
                                    str($state)->headline()->toString()),
                            TextEntry::make('direction')
                                ->badge()
                                ->color(fn (string $state): string =>
                                    $state === 'credit' ? 'success' : 'warning')
                                ->formatStateUsing(fn (string $state): string =>
                                    str($state)->headline()->toString()),
                            TextEntry::make('amount')
                                ->formatStateUsing(fn ($state): string =>
                                    number_format((float) $state, 2).' BIF'),
                            TextEntry::make('balance_after')
                                ->label('Balance after')
                                ->formatStateUsing(fn ($state): string =>
                                    number_format((float) $state, 2).' BIF'),
                            TextEntry::make('createdByUser.name')
                                ->label('Recorded by')
                                ->placeholder('System'),
                            TextEntry::make('occurred_at')
                                ->label('Occurred')
                                ->dateTime('d M Y H:i'),
                            TextEntry::make('description')
                                ->columnSpanFull(),
                        ]),
                ]),

            Section::make('Client activity history')
                ->description('Account creation, sign-ins, funding, wallet activity and purchases in one timeline.')
                ->columnSpanFull()
                ->schema([
                    RepeatableEntry::make('client_activity')
                        ->label('')
                        ->state(fn (ClientWallet $record): array =>
                            self::clientActivity($record))
                        ->columns([
                            'default' => 1,
                            'md' => 3,
                        ])
                        ->schema([
                            TextEntry::make('occurred_at')
                                ->label('Date and time')
                                ->dateTime('d M Y H:i'),
                            TextEntry::make('title')
                                ->weight('bold'),
                            TextEntry::make('description')
                                ->columnSpanFull(),
                        ]),
                ]),
        ]);
    }

    private static function clientActivity(ClientWallet $wallet): array
    {
        $user = $wallet->user;
        $items = new Collection([
            [
                'occurred_at' => $user->created_at,
                'title' => 'Client account created',
                'description' => 'The marketplace client profile and zero-balance wallet were created.',
            ],
        ]);

        if ($user->email_verified_at) {
            $items->push([
                'occurred_at' => $user->email_verified_at,
                'title' => 'Email address verified',
                'description' => $user->email.' was verified for marketplace access.',
            ]);
        }

        $logins = LoginHistory::query()
            ->where('user_id', $user->id)
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn (LoginHistory $history): array => [
                'occurred_at' => $history->created_at,
                'title' => str($history->event)->headline()->toString(),
                'description' => sprintf(
                    '%s panel · %s',
                    str($history->panel ?? 'client')->headline(),
                    $history->ip_address ?: 'IP unavailable',
                ),
            ]);

        $orders = $user->marketplaceOrders()
            ->latest('placed_at')
            ->limit(8)
            ->get()
            ->map(fn (MarketplaceOrder $order): array => [
                'occurred_at' => $order->paid_at ?? $order->placed_at,
                'title' => $order->payment_status === MarketplaceOrder::PAYMENT_PAID
                    ? 'Marketplace purchase paid'
                    : 'Marketplace order created',
                'description' => sprintf(
                    '%s · %s BIF · %s',
                    $order->order_number,
                    number_format((float) $order->grand_total, 2),
                    str($order->status)->headline(),
                ),
            ]);

        $funding = $wallet->fundingRequests()
            ->latest('requested_at')
            ->limit(8)
            ->get()
            ->map(fn (WalletFundingRequest $request): array => [
                'occurred_at' => $request->reviewed_at ?? $request->requested_at,
                'title' => 'Wallet funding '.str($request->status)->headline(),
                'description' => sprintf(
                    '%s · %s BIF',
                    $request->request_number,
                    number_format((float) $request->amount, 2),
                ),
            ]);

        $ledger = $wallet->transactions()
            ->latest('occurred_at')
            ->limit(10)
            ->get()
            ->map(fn ($transaction): array => [
                'occurred_at' => $transaction->occurred_at,
                'title' => str($transaction->type)->headline()->toString(),
                'description' => sprintf(
                    '%s %s BIF · balance %s BIF',
                    str($transaction->direction)->headline(),
                    number_format((float) $transaction->amount, 2),
                    number_format((float) $transaction->balance_after, 2),
                ),
            ]);

        return $items
            ->concat($logins)
            ->concat($orders)
            ->concat($funding)
            ->concat($ledger)
            ->filter(fn (array $item): bool => filled($item['occurred_at']))
            ->sortByDesc('occurred_at')
            ->take(24)
            ->values()
            ->all();
    }
}
