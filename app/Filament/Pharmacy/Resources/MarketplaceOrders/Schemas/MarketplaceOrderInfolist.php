<?php

namespace App\Filament\Pharmacy\Resources\MarketplaceOrders\Schemas;

use App\Models\MarketplaceOrderItem;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class MarketplaceOrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Order summary')
                ->columnSpanFull()
                ->columns([
                    'default' => 1,
                    'md' => 2,
                    'xl' => 4,
                ])
                ->schema([
                    TextEntry::make('order_number')->copyable()->weight('bold'),
                    TextEntry::make('status')
                        ->badge()
                        ->formatStateUsing(fn (string $state): string => str($state)->headline()),
                    TextEntry::make('payment_status')
                        ->label('Payment')
                        ->badge()
                        ->formatStateUsing(fn (string $state): string => str($state)->headline()),
                    TextEntry::make('prescription_status')
                        ->label('Prescription review')
                        ->badge()
                        ->formatStateUsing(fn (string $state): string => str($state)->headline()),
                    TextEntry::make('fulfillment_method')
                        ->label('Fulfilment')
                        ->badge()
                        ->formatStateUsing(fn (string $state): string => str($state)->headline()),
                    TextEntry::make('placed_at')->dateTime('d M Y H:i'),
                    TextEntry::make('reservation_expires_at')
                        ->label('Reservation expires')
                        ->dateTime('d M Y H:i')
                        ->placeholder('Not reserved'),
                    TextEntry::make('branch.name')->label('Branch'),
                    TextEntry::make('wallet.wallet_number')->label('Client wallet')->copyable(),
                    TextEntry::make('walletPaymentTransaction.transaction_number')
                        ->label('Payment transaction')
                        ->copyable()
                        ->placeholder('Not paid'),
                    TextEntry::make('walletRefundTransaction.transaction_number')
                        ->label('Refund transaction')
                        ->copyable()
                        ->placeholder('Not refunded'),
                    TextEntry::make('paid_at')
                        ->label('Paid')
                        ->dateTime('d M Y H:i')
                        ->placeholder('Not paid'),
                    TextEntry::make('refunded_at')
                        ->label('Refunded')
                        ->dateTime('d M Y H:i')
                        ->placeholder('Not refunded'),
                ]),

            Section::make('Client and delivery')
                ->columnSpanFull()
                ->columns([
                    'default' => 1,
                    'md' => 2,
                    'xl' => 3,
                ])
                ->schema([
                    TextEntry::make('client_name')->label('Client name')->weight('bold'),
                    TextEntry::make('client_email')->label('Email')->copyable(),
                    TextEntry::make('client_phone')->label('Phone')->copyable()->placeholder('—'),
                    TextEntry::make('address_line_1')->label('Delivery address')->placeholder('Pickup order'),
                    TextEntry::make('city')->placeholder('—'),
                    TextEntry::make('delivery_instructions')->placeholder('No instructions')->columnSpanFull(),
                ]),

            Section::make('Ordered medicines')
                ->columnSpanFull()
                ->schema([
                    RepeatableEntry::make('items')
                        ->label('')
                        ->columns([
                            'default' => 1,
                            'md' => 2,
                            'xl' => 4,
                        ])
                        ->schema([
                            TextEntry::make('medicine_name')->label('Medicine')->weight('bold'),
                            TextEntry::make('strength')->placeholder('—'),
                            TextEntry::make('quantity')->numeric(decimalPlaces: 3),
                            TextEntry::make('unit_price')
                                ->label('Unit price')
                                ->formatStateUsing(fn ($state): string =>
                                    number_format((float) $state, 2).' BIF'),
                            TextEntry::make('line_total')
                                ->label('Line total')
                                ->formatStateUsing(fn ($state): string =>
                                    number_format((float) $state, 2).' BIF')
                                ->weight('bold'),
                            TextEntry::make('online_sale_mode')
                                ->label('Online rule')
                                ->badge()
                                ->formatStateUsing(fn (string $state): string => str($state)->headline()),
                            TextEntry::make('prescription_review_status')
                                ->label('Review status')
                                ->badge()
                                ->formatStateUsing(fn (string $state): string => str($state)->headline()),
                            TextEntry::make('clientPrescription.original_name')
                                ->label('Prescription document')
                                ->placeholder('Not attached')
                                ->url(fn (MarketplaceOrderItem $record): ?string =>
                                    $record->clientPrescription
                                        ? route('client.prescriptions.download', $record->clientPrescription)
                                        : null)
                                ->openUrlInNewTab(),
                            TextEntry::make('rejection_reason')
                                ->label('Review note')
                                ->placeholder('—')
                                ->columnSpanFull(),
                        ]),
                ]),

            Section::make('Financial summary')
                ->columnSpanFull()
                ->columns(3)
                ->schema([
                    TextEntry::make('subtotal')
                        ->formatStateUsing(fn ($state): string =>
                            number_format((float) $state, 2).' BIF'),
                    TextEntry::make('delivery_fee')
                        ->label('Delivery fee')
                        ->formatStateUsing(fn ($state): string =>
                            number_format((float) $state, 2).' BIF'),
                    TextEntry::make('grand_total')
                        ->label('Grand total')
                        ->formatStateUsing(fn ($state): string =>
                            number_format((float) $state, 2).' BIF')
                        ->weight('bold'),
                ]),

            Section::make('Order history')
                ->columnSpanFull()
                ->schema([
                    RepeatableEntry::make('events')
                        ->label('')
                        ->columns([
                            'default' => 1,
                            'md' => 2,
                            'xl' => 4,
                        ])
                        ->schema([
                            TextEntry::make('occurred_at')->dateTime('d M Y H:i'),
                            TextEntry::make('event_type')
                                ->label('Event')
                                ->badge()
                                ->formatStateUsing(fn (string $state): string => str($state)->headline()),
                            TextEntry::make('actorUser.name')->label('Performed by')->placeholder('System'),
                            TextEntry::make('title')->weight('bold'),
                            TextEntry::make('description')->placeholder('—')->columnSpanFull(),
                        ]),
                ]),
        ]);
    }
}
