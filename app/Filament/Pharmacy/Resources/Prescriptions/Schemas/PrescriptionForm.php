<?php

namespace App\Filament\Pharmacy\Resources\Prescriptions\Schemas;

use App\Models\Customer;
use App\Models\PharmacyBranch;
use App\Models\PharmacyMedicine;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class PrescriptionForm
{
    public static function configure(
        Schema $schema,
    ): Schema {
        return $schema
            ->components([
                Section::make(
                    'Customer and pharmacy branch'
                )
                    ->description(
                        'Search by readable customer and branch information.'
                    )
                    ->columnSpanFull()
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                    ])
                    ->schema([
                        Select::make('customer_id')
                            ->label('Customer / patient')
                            ->placeholder(
                                'Search by name, number, phone or email'
                            )
                            ->searchable()
                            ->getSearchResultsUsing(
                                fn (string $search): array =>
                                    Customer::query()
                                        ->where(
                                            'pharmacy_id',
                                            auth()->user()
                                                ?->pharmacy_id
                                                ?? 0,
                                        )
                                        ->where(
                                            'status',
                                            '!=',
                                            'blocked',
                                        )
                                        ->where(
                                            function ($query) use (
                                                $search,
                                            ): void {
                                                $query
                                                    ->where(
                                                        'name',
                                                        'like',
                                                        "%{$search}%",
                                                    )
                                                    ->orWhere(
                                                        'customer_number',
                                                        'like',
                                                        "%{$search}%",
                                                    )
                                                    ->orWhere(
                                                        'phone',
                                                        'like',
                                                        "%{$search}%",
                                                    )
                                                    ->orWhere(
                                                        'email',
                                                        'like',
                                                        "%{$search}%",
                                                    );
                                            },
                                        )
                                        ->orderBy('name')
                                        ->limit(50)
                                        ->get()
                                        ->mapWithKeys(
                                            fn (
                                                Customer $customer,
                                            ): array => [
                                                $customer->id =>
                                                    self::customerLabel(
                                                        $customer,
                                                    ),
                                            ],
                                        )
                                        ->all(),
                            )
                            ->getOptionLabelUsing(
                                function ($value): ?string {
                                    if (blank($value)) {
                                        return null;
                                    }

                                    $customer =
                                        Customer::query()
                                            ->whereKey($value)
                                            ->where(
                                                'pharmacy_id',
                                                auth()->user()
                                                    ?->pharmacy_id
                                                    ?? 0,
                                            )
                                            ->first();

                                    return $customer
                                        ? self::customerLabel(
                                            $customer,
                                        )
                                        : null;
                                },
                            )
                            ->required(),

                        Select::make(
                            'pharmacy_branch_id'
                        )
                            ->label('Pharmacy branch')
                            ->options(
                                fn (): array =>
                                    PharmacyBranch::query()
                                        ->where(
                                            'pharmacy_id',
                                            auth()->user()
                                                ?->pharmacy_id
                                                ?? 0,
                                        )
                                        ->where(
                                            'status',
                                            'active',
                                        )
                                        ->orderBy('name')
                                        ->pluck('name', 'id')
                                        ->all(),
                            )
                            ->default(
                                fn (): ?int =>
                                    auth()->user()
                                        ?->pharmacy_branch_id,
                            )
                            ->searchable()
                            ->preload()
                            ->required(),
                    ]),

                Section::make('Prescription details')
                    ->columnSpanFull()
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                    ])
                    ->schema([
                        Select::make('source')
                            ->options([
                                'uploaded' =>
                                    'Uploaded prescription',
                                'manual' =>
                                    'Manual entry',
                            ])
                            ->default('uploaded')
                            ->required()
                            ->live(),

                        TextInput::make('prescriber_name')
                            ->label('Prescriber name')
                            ->required()
                            ->maxLength(191),

                        TextInput::make(
                            'prescriber_phone'
                        )
                            ->label('Prescriber phone')
                            ->tel()
                            ->maxLength(50),

                        TextInput::make(
                            'prescriber_facility'
                        )
                            ->label(
                                'Hospital, clinic or facility'
                            )
                            ->maxLength(191),

                        TextInput::make(
                            'prescriber_registration_number'
                        )
                            ->label(
                                'Professional registration number'
                            )
                            ->maxLength(100),

                        DatePicker::make('issued_at')
                            ->label('Issue date')
                            ->native(false)
                            ->maxDate(today())
                            ->required(),

                        DatePicker::make('valid_until')
                            ->label('Valid until')
                            ->native(false),

                        Textarea::make('notes')
                            ->label('Internal notes')
                            ->rows(3)
                            ->maxLength(3000)
                            ->columnSpanFull(),
                    ]),

                Section::make('Prescription document')
                    ->description(
                        'Upload clear images or a PDF. Existing files are permanent and cannot be removed from the edit form.'
                    )
                    ->columnSpanFull()
                    ->visible(
                        fn (Get $get): bool =>
                            $get('source') === 'uploaded',
                    )
                    ->schema([
                        FileUpload::make(
                            'new_attachment_paths'
                        )
                            ->label(
                                'Prescription images or PDF'
                            )
                            ->disk('local')
                            ->directory(
                                fn (): string =>
                                    'prescriptions/'
                                    .(
                                        auth()->user()
                                            ?->pharmacy_id
                                        ?? 0
                                    ),
                            )
                            ->visibility('private')
                            ->acceptedFileTypes([
                                'application/pdf',
                                'image/jpeg',
                                'image/png',
                                'image/webp',
                            ])
                            ->maxSize(10240)
                            ->multiple()
                            ->maxFiles(5)
                            ->maxParallelUploads(1)
                            ->storeFileNamesIn(
                                'attachment_original_names'
                            )
                            ->preventFilePathTampering()
                            ->helperText(
                                'PDF, JPG, PNG or WEBP. Maximum 10 MB per file.'
                            ),
                    ]),

                Section::make('Prescribed medicines')
                    ->description(
                        'Select a pharmacy medicine from the searchable list or enter the prescribed name manually.'
                    )
                    ->columnSpanFull()
                    ->schema([
                        Repeater::make('items')
                            ->label('Medicine items')
                            ->defaultItems(1)
                            ->minItems(1)
                            ->addActionLabel(
                                'Add prescribed medicine'
                            )
                            ->reorderable(false)
                            ->collapsible()
                            ->columns([
                                'default' => 1,
                                'md' => 2,
                                'xl' => 3,
                            ])
                            ->schema([
                                Select::make(
                                    'pharmacy_medicine_id'
                                )
                                    ->label(
                                        'Medicine from pharmacy catalogue'
                                    )
                                    ->placeholder(
                                        'Search by medicine name or SKU'
                                    )
                                    ->searchable()
                                    ->getSearchResultsUsing(
                                        fn (
                                            string $search,
                                        ): array =>
                                            PharmacyMedicine::query()
                                                ->with(
                                                    'medicine'
                                                )
                                                ->where(
                                                    'pharmacy_id',
                                                    auth()->user()
                                                        ?->pharmacy_id
                                                        ?? 0,
                                                )
                                                ->where(
                                                    'status',
                                                    'active',
                                                )
                                                ->where(
                                                    function (
                                                        $query,
                                                    ) use (
                                                        $search,
                                                    ): void {
                                                        $query
                                                            ->where(
                                                                'sku',
                                                                'like',
                                                                "%{$search}%",
                                                            )
                                                            ->orWhereHas(
                                                                'medicine',
                                                                fn (
                                                                    $medicineQuery,
                                                                ) =>
                                                                    $medicineQuery
                                                                        ->where(
                                                                            'brand_name',
                                                                            'like',
                                                                            "%{$search}%",
                                                                        ),
                                                            );
                                                    },
                                                )
                                                ->limit(50)
                                                ->get()
                                                ->mapWithKeys(
                                                    fn (
                                                        PharmacyMedicine $listing,
                                                    ): array => [
                                                        $listing->id =>
                                                            self::medicineLabel(
                                                                $listing,
                                                            ),
                                                    ],
                                                )
                                                ->all(),
                                    )
                                    ->getOptionLabelUsing(
                                        function (
                                            $value,
                                        ): ?string {
                                            if (blank($value)) {
                                                return null;
                                            }

                                            $listing =
                                                PharmacyMedicine::query()
                                                    ->with(
                                                        'medicine'
                                                    )
                                                    ->whereKey(
                                                        $value,
                                                    )
                                                    ->where(
                                                        'pharmacy_id',
                                                        auth()->user()
                                                            ?->pharmacy_id
                                                            ?? 0,
                                                    )
                                                    ->first();

                                            return $listing
                                                ? self::medicineLabel(
                                                    $listing,
                                                )
                                                : null;
                                        },
                                    )
                                    ->live()
                                    ->afterStateUpdated(
                                        function (
                                            $state,
                                            Set $set,
                                        ): void {
                                            if (blank($state)) {
                                                return;
                                            }

                                            $listing =
                                                PharmacyMedicine::query()
                                                    ->with(
                                                        'medicine'
                                                    )
                                                    ->whereKey(
                                                        $state,
                                                    )
                                                    ->where(
                                                        'pharmacy_id',
                                                        auth()->user()
                                                            ?->pharmacy_id
                                                            ?? 0,
                                                    )
                                                    ->first();

                                            if (
                                                $listing
                                                    ?->medicine
                                                    ?->brand_name
                                            ) {
                                                $set(
                                                    'prescribed_name',
                                                    $listing
                                                        ->medicine
                                                        ->brand_name,
                                                );
                                            }
                                        },
                                    ),

                                TextInput::make(
                                    'prescribed_name'
                                )
                                    ->label(
                                        'Prescribed medicine name'
                                    )
                                    ->required()
                                    ->maxLength(191),

                                TextInput::make('strength')
                                    ->placeholder('e.g. 500 mg')
                                    ->maxLength(100),

                                TextInput::make(
                                    'dosage_form'
                                )
                                    ->label('Dosage form')
                                    ->placeholder(
                                        'e.g. Tablet, capsule'
                                    )
                                    ->maxLength(100),

                                TextInput::make('dosage')
                                    ->placeholder(
                                        'e.g. 1 tablet'
                                    )
                                    ->maxLength(191),

                                TextInput::make('frequency')
                                    ->placeholder(
                                        'e.g. Twice daily'
                                    )
                                    ->maxLength(191),

                                TextInput::make('duration')
                                    ->placeholder(
                                        'e.g. 7 days'
                                    )
                                    ->maxLength(191),

                                TextInput::make(
                                    'quantity_prescribed'
                                )
                                    ->label(
                                        'Prescribed quantity'
                                    )
                                    ->numeric()
                                    ->minValue(0.001)
                                    ->step(0.001)
                                    ->required(),

                                Toggle::make(
                                    'substitution_allowed'
                                )
                                    ->label(
                                        'Substitution allowed'
                                    )
                                    ->default(false),

                                Textarea::make(
                                    'instructions'
                                )
                                    ->rows(2)
                                    ->maxLength(2000)
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ]);
    }

    private static function customerLabel(
        Customer $customer,
    ): string {
        $contact = $customer->phone
            ?: $customer->email
            ?: 'No contact';

        return sprintf(
            '%s — %s — %s',
            $customer->name,
            $customer->customer_number,
            $contact,
        );
    }

    private static function medicineLabel(
        PharmacyMedicine $listing,
    ): string {
        return sprintf(
            '%s — SKU: %s',
            $listing->medicine?->brand_name
                ?? 'Unnamed medicine',
            $listing->sku,
        );
    }
}