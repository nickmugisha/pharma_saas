<?php

namespace App\Filament\Pharmacy\Resources\Prescriptions\Schemas;

use App\Models\Prescription;
use App\Models\PrescriptionAttachment;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use App\Filament\Pharmacy\Resources\Sales\SaleResource;
use App\Models\PrescriptionDispensing;

class PrescriptionInfolist
{
    public static function configure(
        Schema $schema,
    ): Schema {
        return $schema
            ->components([
                Section::make('Prescription summary')
                    ->description(
                        'Prescription identity, status and pharmacy information.'
                    )
                    ->columnSpanFull()
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                        'xl' => 4,
                    ])
                    ->schema([
                        TextEntry::make(
                            'prescription_number'
                        )
                            ->label(
                                'Prescription number'
                            )
                            ->copyable()
                            ->badge()
                            ->color('primary'),

                        TextEntry::make('status')
                            ->badge()
                            ->formatStateUsing(
                                fn (string $state): string =>
                                    Str::headline($state),
                            )
                            ->color(
                                fn (string $state): string =>
                                    match ($state) {
                                        Prescription::STATUS_DRAFT =>
                                            'gray',

                                        Prescription::STATUS_SUBMITTED =>
                                            'info',

                                        Prescription::STATUS_UNDER_REVIEW =>
                                            'warning',

                                        Prescription::STATUS_APPROVED =>
                                            'success',

                                        Prescription::STATUS_REJECTED =>
                                            'danger',

                                        Prescription::STATUS_PARTIALLY_DISPENSED =>
                                            'warning',

                                        Prescription::STATUS_DISPENSED =>
                                            'success',

                                        Prescription::STATUS_CANCELLED =>
                                            'danger',

                                        default => 'gray',
                                    },
                            ),

                        TextEntry::make('source')
                            ->badge()
                            ->formatStateUsing(
                                fn (string $state): string =>
                                    Str::headline($state),
                            ),

                        TextEntry::make('branch.name')
                            ->label('Pharmacy branch'),

                        TextEntry::make(
                            'createdByUser.name'
                        )
                            ->label('Registered by')
                            ->placeholder('System'),

                        TextEntry::make(
                            'reviewedByUser.name'
                        )
                            ->label('Reviewed by')
                            ->placeholder('Not reviewed'),

                        TextEntry::make('created_at')
                            ->label('Registered')
                            ->dateTime('d M Y H:i'),

                        TextEntry::make('submitted_at')
                            ->label('Submitted')
                            ->dateTime('d M Y H:i')
                            ->placeholder('Not submitted'),
                    ]),

                Section::make('Customer / patient')
                    ->description(
                        'Customer identity linked to this prescription.'
                    )
                    ->columnSpanFull()
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                        'xl' => 3,
                    ])
                    ->schema([
                        TextEntry::make('customer.name')
                            ->label('Full name')
                            ->weight('bold'),

                        TextEntry::make(
                            'customer.customer_number'
                        )
                            ->label('Customer number')
                            ->copyable(),

                        TextEntry::make('customer.phone')
                            ->label('Phone number')
                            ->copyable()
                            ->placeholder('Not provided'),

                        TextEntry::make('customer.email')
                            ->label('Email')
                            ->copyable()
                            ->placeholder('Not provided'),

                        TextEntry::make(
                            'customer.patientProfile.date_of_birth'
                        )
                            ->label('Date of birth')
                            ->date('d M Y')
                            ->placeholder(
                                'No patient profile'
                            ),

                        TextEntry::make(
                            'customer.patientProfile.sex'
                        )
                            ->label('Sex')
                            ->formatStateUsing(
                                fn (?string $state): string =>
                                    filled($state)
                                        ? Str::headline($state)
                                        : 'Not provided',
                            ),
                    ]),

                Section::make('Prescriber details')
                    ->columnSpanFull()
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                        'xl' => 3,
                    ])
                    ->schema([
                        TextEntry::make(
                            'prescriber_name'
                        )
                            ->label('Prescriber name')
                            ->weight('bold'),

                        TextEntry::make(
                            'prescriber_phone'
                        )
                            ->label('Prescriber phone')
                            ->copyable()
                            ->placeholder('Not provided'),

                        TextEntry::make(
                            'prescriber_facility'
                        )
                            ->label(
                                'Hospital, clinic or facility'
                            )
                            ->placeholder('Not provided'),

                        TextEntry::make(
                            'prescriber_registration_number'
                        )
                            ->label(
                                'Professional registration number'
                            )
                            ->placeholder('Not provided'),

                        TextEntry::make('issued_at')
                            ->label('Issue date')
                            ->date('d M Y')
                            ->placeholder('Not specified'),

                        TextEntry::make('valid_until')
                            ->label('Valid until')
                            ->date('d M Y')
                            ->placeholder('Not specified'),

                        TextEntry::make('notes')
                            ->label('Internal notes')
                            ->placeholder('No notes')
                            ->columnSpanFull(),
                    ]),

                Section::make('Prescribed medicines')
                    ->description(
                        'Read-only medicine instructions and dispensing progress.'
                    )
                    ->columnSpanFull()
                    ->schema([
                        RepeatableEntry::make('items')
                            ->label('Medicine items')
                            ->columns([
                                'default' => 1,
                                'md' => 2,
                                'xl' => 4,
                            ])
                            ->schema([
                                TextEntry::make(
                                    'prescribed_name'
                                )
                                    ->label('Medicine')
                                    ->weight('bold'),

                                TextEntry::make('strength')
                                    ->placeholder('—'),

                                TextEntry::make(
                                    'dosage_form'
                                )
                                    ->label('Dosage form')
                                    ->placeholder('—'),

                                TextEntry::make('dosage')
                                    ->placeholder('—'),

                                TextEntry::make('frequency')
                                    ->placeholder('—'),

                                TextEntry::make('duration')
                                    ->placeholder('—'),

                                TextEntry::make(
                                    'quantity_prescribed'
                                )
                                    ->label(
                                        'Quantity prescribed'
                                    )
                                    ->numeric(
                                        decimalPlaces: 3,
                                    ),

                                TextEntry::make(
                                    'quantity_dispensed'
                                )
                                    ->label(
                                        'Quantity dispensed'
                                    )
                                    ->numeric(
                                        decimalPlaces: 3,
                                    ),

                                TextEntry::make('status')
                                    ->badge()
                                    ->formatStateUsing(
                                        fn (
                                            string $state,
                                        ): string =>
                                            Str::headline(
                                                $state,
                                            ),
                                    ),

                                TextEntry::make(
                                    'substitution_allowed'
                                )
                                    ->label(
                                        'Substitution'
                                    )
                                    ->formatStateUsing(
                                        fn (
                                            bool $state,
                                        ): string =>
                                            $state
                                                ? 'Allowed'
                                                : 'Not allowed',
                                    )
                                    ->badge()
                                    ->color(
                                        fn (
                                            bool $state,
                                        ): string =>
                                            $state
                                                ? 'success'
                                                : 'gray',
                                    ),

                                TextEntry::make(
                                    'instructions'
                                )
                                    ->placeholder(
                                        'No instructions'
                                    )
                                    ->columnSpanFull(),
                            ]),
                    ]),

                Section::make('Prescription documents')
                    ->description(
                        'Private files are available only to authorized pharmacy users.'
                    )
                    ->columnSpanFull()
                    ->visible(
                        fn (
                            Prescription $record,
                        ): bool =>
                            $record
                                ->attachments()
                                ->exists(),
                    )
                    ->schema([
                        RepeatableEntry::make(
                            'attachments'
                        )
                            ->label('Attachments')
                            ->columns([
                                'default' => 1,
                                'md' => 2,
                                'xl' => 4,
                            ])
                            ->schema([
                                TextEntry::make(
                                    'original_name'
                                )
                                    ->label('File')
                                    ->icon(
                                        'heroicon-o-arrow-down-tray'
                                    )
                                    ->weight('bold')
                                    ->url(
                                        fn (
                                            PrescriptionAttachment $record,
                                        ): string =>
                                            route(
                                                'pharmacy.prescription-attachments.download',
                                                [
                                                    'attachment' =>
                                                        $record,
                                                ],
                                            ),
                                    ),

                                TextEntry::make('mime_type')
                                    ->label('File type')
                                    ->placeholder('Unknown'),

                                TextEntry::make('size_bytes')
                                    ->label('Size')
                                    ->formatStateUsing(
                                        fn (
                                            $state,
                                        ): string =>
                                            self::formatBytes(
                                                (int) $state,
                                            ),
                                    ),

                                TextEntry::make(
                                    'uploadedByUser.name'
                                )
                                    ->label('Uploaded by')
                                    ->placeholder('System'),

                                TextEntry::make('created_at')
                                    ->label('Uploaded')
                                    ->dateTime('d M Y H:i'),
                            ]),
                    ]),

                Section::make('Review decision')
                    ->columnSpanFull()
                    ->visible(
                        fn (
                            Prescription $record,
                        ): bool =>
                            in_array(
                                $record->status,
                                [
                                    Prescription::STATUS_APPROVED,
                                    Prescription::STATUS_REJECTED,
                                    Prescription::STATUS_PARTIALLY_DISPENSED,
                                    Prescription::STATUS_DISPENSED,
                                ],
                                true,
                            ),
                    )
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                    ])
                    ->schema([
                        TextEntry::make('reviewed_at')
                            ->label('Reviewed')
                            ->dateTime('d M Y H:i')
                            ->placeholder('Not recorded'),

                        TextEntry::make('approved_at')
                            ->label('Approved')
                            ->dateTime('d M Y H:i')
                            ->placeholder('Not approved'),

                        TextEntry::make('rejected_at')
                            ->label('Rejected')
                            ->dateTime('d M Y H:i')
                            ->placeholder('Not rejected'),

                        TextEntry::make(
                            'rejection_reason'
                        )
                            ->label('Rejection reason')
                            ->placeholder('Not applicable')
                            ->columnSpanFull(),
                    ]),

                Section::make('Dispensing history')
    ->description(
        'Permanent dispensing events, linked sales and quantities supplied.'
    )
    ->columnSpanFull()
    ->visible(
        fn (
            Prescription $record,
        ): bool =>
            $record
                ->dispensings()
                ->exists(),
    )
    ->schema([
        RepeatableEntry::make('dispensings')
            ->label('Dispensing events')
            ->columns([
                'default' => 1,
                'md' => 2,
                'xl' => 4,
            ])
            ->schema([
                TextEntry::make(
                    'dispensing_number'
                )
                    ->label(
                        'Dispensing number'
                    )
                    ->copyable()
                    ->badge()
                    ->color('primary'),

                TextEntry::make('status')
                    ->badge()
                    ->formatStateUsing(
                        fn (
                            string $state,
                        ): string =>
                            Str::headline($state),
                    )
                    ->color(
                        fn (
                            string $state,
                        ): string =>
                            $state ===
                            PrescriptionDispensing
                                ::STATUS_VOIDED
                                ? 'danger'
                                : 'success',
                    ),

                TextEntry::make('dispensed_at')
                    ->label('Dispensed')
                    ->dateTime('d M Y H:i'),

                TextEntry::make(
                    'dispensedByUser.name'
                )
                    ->label('Dispensed by')
                    ->placeholder('System'),

                TextEntry::make(
                    'sale.sale_number'
                )
                    ->label('Linked sale')
                    ->icon(
                        'heroicon-o-shopping-bag'
                    )
                    ->url(
                        fn (
                            PrescriptionDispensing $record,
                        ): string =>
                            SaleResource::getUrl(
                                'view',
                                [
                                    'record' =>
                                        $record->sale,
                                ],
                            ),
                    ),

                TextEntry::make(
                    'sale.receipt_number'
                )
                    ->label('Receipt')
                    ->copyable()
                    ->placeholder(
                        'Not available'
                    )
                    ->url(
                        fn (
                            PrescriptionDispensing $record,
                        ): string =>
                            SaleResource::getUrl(
                                'view',
                                [
                                    'record' =>
                                        $record->sale,
                                ],
                            ),
                    ),

                TextEntry::make(
                    'sale.grand_total'
                )
                    ->label('Sale total')
                    ->formatStateUsing(
                        fn ($state): string =>
                            number_format(
                                (float) $state,
                                2,
                            ).' BIF',
                    ),

                TextEntry::make(
                    'sale.payment_status'
                )
                    ->label('Payment')
                    ->badge()
                    ->formatStateUsing(
                        fn (
                            string $state,
                        ): string =>
                            Str::headline($state),
                    ),

                TextEntry::make(
                    'dispensed_items_summary'
                )
                    ->label(
                        'Medicines supplied'
                    )
                    ->state(
                        fn (
                            PrescriptionDispensing $record,
                        ): array =>
                            $record
                                ->items
                                ->map(
                                    function (
                                        $item,
                                    ): string {
                                        $name =
                                            $item
                                                ->prescriptionItem
                                                ?->prescribed_name
                                            ?? 'Medicine';

                                        return sprintf(
                                            '%s — %s unit(s)',
                                            $name,
                                            number_format(
                                                (float) $item
                                                    ->quantity_dispensed,
                                                3,
                                            ),
                                        );
                                    },
                                )
                                ->all(),
                    )
                    ->listWithLineBreaks()
                    ->bulleted()
                    ->columnSpanFull(),

                TextEntry::make('notes')
                    ->label('Dispensing note')
                    ->placeholder('No note')
                    ->columnSpanFull(),
            ]),
    ]),

                Section::make('Activity timeline')
                    ->description(
                        'Permanent history of prescription workflow events.'
                    )
                    ->columnSpanFull()
                    ->schema([
                        RepeatableEntry::make(
                            'activities'
                        )
                            ->label('Activities')
                            ->columns([
                                'default' => 1,
                                'md' => 2,
                                'xl' => 4,
                            ])
                            ->schema([
                                TextEntry::make(
                                    'occurred_at'
                                )
                                    ->label('Date and time')
                                    ->dateTime('d M Y H:i'),

                                TextEntry::make(
                                    'activity_type'
                                )
                                    ->label('Activity')
                                    ->badge()
                                    ->formatStateUsing(
                                        fn (
                                            string $state,
                                        ): string =>
                                            Str::headline(
                                                $state,
                                            ),
                                    ),

                                TextEntry::make(
                                    'actorUser.name'
                                )
                                    ->label('Performed by')
                                    ->placeholder('System'),

                                TextEntry::make(
                                    'branch.name'
                                )
                                    ->label('Branch')
                                    ->placeholder('Not assigned'),

                                TextEntry::make('title')
                                    ->weight('bold')
                                    ->columnSpanFull(),

                                TextEntry::make(
                                    'description'
                                )
                                    ->placeholder(
                                        'No additional description'
                                    )
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ]);
    }

    private static function formatBytes(
        int $bytes,
    ): string {
        if ($bytes <= 0) {
            return 'Unknown';
        }

        if ($bytes >= 1_048_576) {
            return number_format(
                $bytes / 1_048_576,
                2,
            ).' MB';
        }

        if ($bytes >= 1024) {
            return number_format(
                $bytes / 1024,
                2,
            ).' KB';
        }

        return $bytes.' bytes';
    }
}