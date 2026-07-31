<?php

namespace App\Filament\Pharmacy\Resources\InventoryAlerts\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class InventoryAlertInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Alert details')
                    ->columnSpanFull()
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                    ])
                    ->schema([
                        TextEntry::make('message')
                            ->label('Alert message')
                            ->columnSpanFull(),

                        TextEntry::make(
                            'pharmacyMedicine.medicine.brand_name'
                        )
                            ->label('Medicine')
                            ->placeholder('—'),

                        TextEntry::make('branch.name')
                            ->label('Branch')
                            ->placeholder('—'),

                        TextEntry::make('medicineBatch.batch_number')
                            ->label('Batch number')
                            ->placeholder('Not batch-specific'),

                        TextEntry::make('alert_type')
                            ->label('Alert type')
                            ->badge()
                            ->formatStateUsing(
                                fn (string $state): string =>
                                    str($state)
                                        ->replace('_', ' ')
                                        ->title(),
                            )
                            ->color(fn (string $state): string => match ($state) {
                                'out_of_stock', 'expired' => 'danger',
                                'low_stock', 'expiring' => 'warning',
                                default => 'gray',
                            }),

                        TextEntry::make('severity')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'critical' => 'danger',
                                'warning' => 'warning',
                                default => 'gray',
                            }),

                        TextEntry::make('status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'open' => 'danger',
                                'acknowledged' => 'warning',
                                'resolved' => 'success',
                                default => 'gray',
                            }),

                        TextEntry::make('current_value')
                            ->label('Current value')
                            ->formatStateUsing(
                                fn ($state): string =>
                                    blank($state)
                                        ? '—'
                                        : number_format(
                                            (float) $state,
                                            3,
                                        ),
                            ),

                        TextEntry::make('threshold_value')
                            ->label('Threshold')
                            ->formatStateUsing(
                                fn ($state): string =>
                                    blank($state)
                                        ? '—'
                                        : number_format(
                                            (float) $state,
                                            3,
                                        ),
                            ),
                    ]),

                Section::make('Alert timeline')
                    ->columnSpanFull()
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                    ])
                    ->schema([
                        TextEntry::make('detected_at')
                            ->label('Detected')
                            ->dateTime('d M Y H:i'),

                        TextEntry::make('acknowledged_at')
                            ->label('Acknowledged')
                            ->dateTime('d M Y H:i')
                            ->placeholder('Not acknowledged'),

                        TextEntry::make('acknowledgedByUser.name')
                            ->label('Acknowledged by')
                            ->placeholder('—'),

                        TextEntry::make('resolved_at')
                            ->label('Resolved')
                            ->dateTime('d M Y H:i')
                            ->placeholder('Not resolved'),

                        TextEntry::make('resolvedByUser.name')
                            ->label('Resolved by')
                            ->placeholder('System or not resolved'),

                        TextEntry::make('alert_key')
                            ->label('System reference')
                            ->copyable(),
                    ]),
            ]);
    }
}