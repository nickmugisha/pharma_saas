<?php

namespace App\Filament\Pharmacy\Resources\Customers\Schemas;

use App\Models\Customer;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class CustomerInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Customer account')
                    ->description(
                        'Commercial identity and account status.'
                    )
                    ->columnSpanFull()
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                        'xl' => 3,
                    ])
                    ->schema([
                        TextEntry::make('customer_number')
                            ->label('Customer number')
                            ->copyable()
                            ->badge()
                            ->color('primary'),

                        TextEntry::make('name')
                            ->label('Full name')
                            ->weight('bold'),

                        TextEntry::make('status')
                            ->badge()
                            ->color(
                                fn (string $state): string =>
                                    match ($state) {
                                        'active' => 'success',
                                        'inactive' => 'warning',
                                        'blocked' => 'danger',
                                        default => 'gray',
                                    },
                            ),

                        TextEntry::make('phone')
                            ->label('Phone number')
                            ->copyable()
                            ->placeholder('Not provided'),

                        TextEntry::make('email')
                            ->copyable()
                            ->placeholder('Not provided'),

                        TextEntry::make('registeredBranch.name')
                            ->label('Registration branch')
                            ->placeholder('Not assigned'),

                        TextEntry::make('preferred_language')
                            ->label('Preferred language')
                            ->formatStateUsing(
                                fn (?string $state): string =>
                                    match ($state) {
                                        'fr' => 'French',
                                        'rn' => 'Kirundi',
                                        'en' => 'English',
                                        default => 'Not specified',
                                    },
                            ),

                        TextEntry::make('registered_at')
                            ->label('Registered')
                            ->dateTime('d M Y H:i')
                            ->placeholder('Not recorded'),

                        TextEntry::make('last_activity_at')
                            ->label('Last activity')
                            ->dateTime('d M Y H:i')
                            ->placeholder('No activity yet'),
                    ]),

                Section::make('Contact and location')
                    ->columnSpanFull()
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                    ])
                    ->schema([
                        TextEntry::make('address')
                            ->placeholder('Not provided'),

                        TextEntry::make('city')
                            ->placeholder('Not provided'),

                        TextEntry::make('country')
                            ->placeholder('Not provided'),

                        TextEntry::make('notes')
                            ->label('Internal notes')
                            ->placeholder('No internal notes')
                            ->columnSpanFull(),
                    ]),

                Section::make('Patient profile')
                    ->description(
                        'Basic patient identity and emergency-contact information.'
                    )
                    ->columnSpanFull()
                    ->visible(
                        fn (Customer $record): bool =>
                            $record->patientProfile !== null,
                    )
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                        'xl' => 3,
                    ])
                    ->schema([
                        TextEntry::make(
                            'patientProfile.date_of_birth'
                        )
                            ->label('Date of birth')
                            ->date('d M Y')
                            ->placeholder('Not provided'),

                        TextEntry::make('patientProfile.sex')
                            ->label('Sex')
                            ->formatStateUsing(
                                fn (?string $state): string =>
                                    filled($state)
                                        ? Str::headline($state)
                                        : 'Not provided',
                            ),

                        TextEntry::make(
                            'patientProfile.emergency_contact_name'
                        )
                            ->label('Emergency contact')
                            ->placeholder('Not provided'),

                        TextEntry::make(
                            'patientProfile.emergency_contact_phone'
                        )
                            ->label('Emergency phone')
                            ->copyable()
                            ->placeholder('Not provided'),

                        TextEntry::make(
                            'patientProfile.emergency_contact_relation'
                        )
                            ->label('Relationship')
                            ->placeholder('Not provided'),
                    ]),

                Section::make('Sales history')
                    ->description(
                        'Read-only transactions linked to this customer account.'
                    )
                    ->columnSpanFull()
                    ->schema([
                        RepeatableEntry::make('sales')
                            ->label('Sales')
                            ->columns([
                                'default' => 1,
                                'md' => 2,
                                'xl' => 4,
                            ])
                            ->schema([
                                TextEntry::make('receipt_number')
                                    ->label('Receipt')
                                    ->copyable()
                                    ->weight('bold')
                                    ->placeholder('No receipt'),

                                TextEntry::make('sold_at')
                                    ->label('Sale date')
                                    ->dateTime('d M Y H:i')
                                    ->placeholder('Not recorded'),

                                TextEntry::make('branch.name')
                                    ->label('Branch')
                                    ->placeholder('Not assigned'),

                                TextEntry::make('cashier.name')
                                    ->label('Cashier')
                                    ->placeholder('Not recorded'),

                                TextEntry::make('grand_total')
                                    ->label('Total')
                                    ->formatStateUsing(
                                        fn ($state): string =>
                                            number_format(
                                                (float) $state,
                                                0,
                                                '.',
                                                ' ',
                                            ).' BIF',
                                    )
                                    ->weight('bold'),

                                TextEntry::make('status')
                                    ->badge()
                                    ->color(
                                        fn (string $state): string =>
                                            match ($state) {
                                                'completed' => 'success',
                                                'voided' => 'danger',
                                                'draft' => 'warning',
                                                default => 'gray',
                                            },
                                    ),

                                TextEntry::make('payment_status')
                                    ->label('Payment')
                                    ->badge()
                                    ->color(
                                        fn (string $state): string =>
                                            match ($state) {
                                                'paid' => 'success',
                                                'partial' => 'warning',
                                                'voided' => 'danger',
                                                default => 'gray',
                                            },
                                    ),

                                TextEntry::make('void_reason')
                                    ->label('Void reason')
                                    ->placeholder('—')
                                    ->visible(
                                        fn ($record): bool =>
                                            $record?->status === 'voided',
                                    ),
                            ]),
                    ]),

                Section::make('Activity timeline')
                    ->description(
                        'Permanent history of important customer-account events.'
                    )
                    ->columnSpanFull()
                    ->schema([
                        RepeatableEntry::make('activities')
                            ->label('Activities')
                            ->columns([
                                'default' => 1,
                                'md' => 2,
                                'xl' => 4,
                            ])
                            ->schema([
                                TextEntry::make('occurred_at')
                                    ->label('Date and time')
                                    ->dateTime('d M Y H:i'),

                                TextEntry::make('activity_type')
                                    ->label('Activity type')
                                    ->badge()
                                    ->formatStateUsing(
                                        fn (string $state): string =>
                                            Str::headline($state),
                                    ),

                                TextEntry::make('branch.name')
                                    ->label('Branch')
                                    ->placeholder('Not assigned'),

                                TextEntry::make('actorUser.name')
                                    ->label('Performed by')
                                    ->placeholder('System'),

                                TextEntry::make('title')
                                    ->weight('bold')
                                    ->columnSpanFull(),

                                TextEntry::make('description')
                                    ->placeholder(
                                        'No additional description'
                                    )
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ]);
    }
}