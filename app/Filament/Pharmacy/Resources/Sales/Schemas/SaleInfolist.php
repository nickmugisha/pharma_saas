<?php

namespace App\Filament\Pharmacy\Resources\Sales\Schemas;

use App\Models\SaleItem;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use App\Models\Sale;

class SaleInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Receipt information')
                    ->columnSpanFull()
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                        'xl' => 4,
                    ])
                    ->schema([
                        TextEntry::make('sale_number')
                            ->label('Sale number')
                            ->copyable()
                            ->weight('bold'),

                        TextEntry::make('receipt_number')
                            ->label('Receipt number')
                            ->copyable()
                            ->placeholder('—'),

                        TextEntry::make('sold_at')
                            ->label('Sale date')
                            ->dateTime('d M Y H:i'),

                        TextEntry::make('completed_at')
                            ->label('Completed at')
                            ->dateTime('d M Y H:i')
                            ->placeholder('—'),

                        TextEntry::make('branch.name')
                            ->label('Selling branch')
                            ->placeholder('—'),

                        TextEntry::make('cashier.name')
                            ->label('Cashier')
                            ->placeholder('System'),

                        TextEntry::make('status')
                            ->badge()
                            ->formatStateUsing(
                                fn (string $state): string =>
                                    str($state)
                                        ->replace('_', ' ')
                                        ->title(),
                            )
                            ->color(
                                fn (string $state): string =>
                                    match ($state) {
                                        'completed' => 'success',
                                        'voided' => 'danger',
                                        'draft' => 'gray',
                                        default => 'gray',
                                    },
                            ),

                        TextEntry::make('payment_status')
                            ->label('Payment status')
                            ->badge()
                            ->formatStateUsing(
                                fn (string $state): string =>
                                    str($state)
                                        ->replace('_', ' ')
                                        ->title(),
                            )
                            ->color(
                                fn (string $state): string =>
                                    match ($state) {
                                        'paid' => 'success',
                                        'partially_paid' => 'warning',
                                        'unpaid' => 'danger',
                                        'refunded' => 'info',
                                        default => 'gray',
                                    },
                            ),

                        TextEntry::make('customer_name')
                            ->label('Customer name')
                            ->placeholder('Walk-in customer'),

                        TextEntry::make('customer_phone')
                            ->label('Customer phone')
                            ->placeholder('—'),

                        TextEntry::make('currency')
                            ->label('Currency'),

                        TextEntry::make('channel')
                            ->label('Sale channel')
                            ->formatStateUsing(
                                fn (string $state): string =>
                                    str($state)
                                        ->replace('_', ' ')
                                        ->upper(),
                            ),
                    ]),

                Section::make('Financial summary')
                    ->columnSpanFull()
                    ->columns([
                        'default' => 1,
                        'md' => 3,
                    ])
                    ->schema([
                        TextEntry::make('subtotal')
                            ->formatStateUsing(
                                fn ($state): string =>
                                    number_format(
                                        (float) $state,
                                        2,
                                    ).' BIF',
                            ),

                        TextEntry::make('discount_total')
                            ->label('Discount total')
                            ->formatStateUsing(
                                fn ($state): string =>
                                    number_format(
                                        (float) $state,
                                        2,
                                    ).' BIF',
                            ),

                        TextEntry::make('tax_total')
                            ->label('Tax total')
                            ->formatStateUsing(
                                fn ($state): string =>
                                    number_format(
                                        (float) $state,
                                        2,
                                    ).' BIF',
                            ),

                        TextEntry::make('grand_total')
                            ->label('Grand total')
                            ->formatStateUsing(
                                fn ($state): string =>
                                    number_format(
                                        (float) $state,
                                        2,
                                    ).' BIF',
                            )
                            ->weight('bold'),

                        TextEntry::make('paid_amount')
                            ->label('Amount applied')
                            ->formatStateUsing(
                                fn ($state): string =>
                                    number_format(
                                        (float) $state,
                                        2,
                                    ).' BIF',
                            ),

                        TextEntry::make('change_amount')
                            ->label('Cash change')
                            ->formatStateUsing(
                                fn ($state): string =>
                                    number_format(
                                        (float) $state,
                                        2,
                                    ).' BIF',
                            ),
                    ]),

                Section::make('Medicines sold')
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
                                TextEntry::make('medicine_name')
                                    ->label('Medicine')
                                    ->weight('bold'),

                                TextEntry::make('sku')
                                    ->label('SKU')
                                    ->placeholder('—'),

                                TextEntry::make('quantity')
                                    ->formatStateUsing(
                                        fn ($state): string =>
                                            number_format(
                                                (float) $state,
                                                3,
                                            ),
                                    ),

                                TextEntry::make('unit_price')
                                    ->label('Unit price')
                                    ->formatStateUsing(
                                        fn ($state): string =>
                                            number_format(
                                                (float) $state,
                                                2,
                                            ).' BIF',
                                    ),

                                TextEntry::make('discount_amount')
                                    ->label('Discount')
                                    ->formatStateUsing(
                                        fn ($state): string =>
                                            number_format(
                                                (float) $state,
                                                2,
                                            ).' BIF',
                                    ),

                                TextEntry::make('tax_rate')
                                    ->label('Tax rate')
                                    ->formatStateUsing(
                                        fn ($state): string =>
                                            number_format(
                                                (float) $state,
                                                3,
                                            ).'%',
                                    ),

                                TextEntry::make('tax_amount')
                                    ->label('Tax amount')
                                    ->formatStateUsing(
                                        fn ($state): string =>
                                            number_format(
                                                (float) $state,
                                                2,
                                            ).' BIF',
                                    ),

                                TextEntry::make('line_total')
                                    ->label('Line total')
                                    ->formatStateUsing(
                                        fn ($state): string =>
                                            number_format(
                                                (float) $state,
                                                2,
                                            ).' BIF',
                                    )
                                    ->weight('bold'),

                                TextEntry::make('batch_summary')
                                    ->label('Batches used')
                                    ->state(
                                        fn (
                                            SaleItem $record
                                        ): array =>
                                            $record
                                                ->batchAllocations
                                                ->map(
                                                    function (
                                                        $allocation
                                                    ): string {
                                                        $batch =
                                                            $allocation
                                                                ->medicineBatch;

                                                        $batchNumber =
                                                            $batch
                                                                ?->batch_number
                                                            ?? 'Unknown batch';

                                                        $expiry =
                                                            $batch
                                                                ?->expiry_date
                                                                ?->format(
                                                                    'd M Y'
                                                                )
                                                            ?? 'No expiry';

                                                        return sprintf(
                                                            '%s — %s units — expires %s',
                                                            $batchNumber,
                                                            number_format(
                                                                (float) $allocation
                                                                    ->quantity,
                                                                3,
                                                            ),
                                                            $expiry,
                                                        );
                                                    },
                                                )
                                                ->values()
                                                ->all(),
                                    )
                                    ->listWithLineBreaks()
                                    ->bulleted()
                                    ->placeholder(
                                        'No batch allocation details'
                                    )
                                    ->columnSpanFull(),

                                TextEntry::make('notes')
                                    ->label('Item notes')
                                    ->placeholder('—')
                                    ->columnSpanFull(),
                            ]),
                    ]),

                Section::make('Payments received')
                    ->columnSpanFull()
                    ->schema([
                        RepeatableEntry::make('payments')
                            ->label('')
                            ->columns([
                                'default' => 1,
                                'md' => 2,
                                'xl' => 4,
                            ])
                            ->schema([
                                TextEntry::make('payment_number')
                                    ->label('Payment number')
                                    ->copyable(),

                                TextEntry::make('payment_method')
                                    ->label('Method')
                                    ->badge()
                                    ->formatStateUsing(
                                        fn (
                                            string $state
                                        ): string =>
                                            str($state)
                                                ->replace('_', ' ')
                                                ->title(),
                                    ),

                                TextEntry::make('amount')
                                    ->formatStateUsing(
                                        fn ($state): string =>
                                            number_format(
                                                (float) $state,
                                                2,
                                            ).' BIF',
                                    )
                                    ->weight('bold'),

                                TextEntry::make('status')
                                    ->badge()
                                    ->formatStateUsing(
                                        fn (
                                            string $state
                                        ): string =>
                                            str($state)
                                                ->replace('_', ' ')
                                                ->title(),
                                    )
                                    ->color(
                                        fn (
                                            string $state
                                        ): string =>
                                            match ($state) {
                                                'completed' =>
                                                    'success',
                                                'voided' =>
                                                    'danger',
                                                default => 'gray',
                                            },
                                    ),

                                TextEntry::make('reference')
                                    ->label('Reference')
                                    ->copyable()
                                    ->placeholder('—'),

                                TextEntry::make(
                                    'receivedByUser.name'
                                )
                                    ->label('Received by')
                                    ->placeholder('System'),

                                TextEntry::make('paid_at')
                                    ->label('Paid at')
                                    ->dateTime('d M Y H:i'),

                                TextEntry::make('notes')
                                    ->label('Payment notes')
                                    ->placeholder('—'),
                            ]),
                    ]),

                Section::make('Additional information')

                    ->columnSpanFull()
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                    ])
                    ->schema([
                        TextEntry::make('notes')
                            ->label('Sale notes')
                            ->placeholder('—')
                            ->columnSpanFull(),

                        TextEntry::make('void_reason')
                            ->label('Void reason')
                            ->placeholder('Not voided')
                            ->columnSpanFull(),

                        TextEntry::make('voided_at')
                            ->label('Voided at')
                            ->dateTime('d M Y H:i')
                            ->placeholder('—'),

                        TextEntry::make('voidedByUser.name')
                            ->label('Voided by')
                            ->placeholder('—'),
                    ]),
           Section::make('Void information')
    ->description(
        'Permanent reversal record for this sale.'
    )
    ->columnSpanFull()
    ->visible(
        fn (Sale $record): bool =>
            $record->status === 'voided',
    )
    ->columns([
        'default' => 1,
        'md' => 2,
        'xl' => 3,
    ])
    ->schema([
        TextEntry::make('voidRecord.void_number')
            ->label('Void number')
            ->copyable()
            ->weight('bold')
            ->placeholder('—'),

        TextEntry::make('voidRecord.voided_at')
            ->label('Voided at')
            ->dateTime('d M Y H:i')
            ->placeholder('—'),

        TextEntry::make(
            'voidRecord.voidedByUser.name'
        )
            ->label('Authorised by')
            ->placeholder('System'),

        TextEntry::make(
            'voidRecord.restored_quantity'
        )
            ->label('Quantity restored')
            ->formatStateUsing(
                fn ($state): string =>
                    number_format(
                        (float) $state,
                        3,
                    ).' unit(s)',
            ),

        TextEntry::make(
            'voidRecord.reversed_payment_amount'
        )
            ->label('Payment amount reversed')
            ->formatStateUsing(
                fn ($state): string =>
                    number_format(
                        (float) $state,
                        2,
                    ).' BIF',
            ),

        TextEntry::make('voidRecord.reason')
            ->label('Void reason')
            ->columnSpanFull()
            ->placeholder('—'),
    ]),
                    ]);

    
    }
}