<?php

namespace App\Filament\Pharmacy\Resources\Customers\Schemas;

use App\Models\Customer;
use App\Models\PharmacyBranch;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class CustomerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Customer information')
                    ->description(
                        'Commercial identity and contact details.'
                    )
                    ->columnSpanFull()
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                    ])
                    ->schema([
                        TextInput::make('name')
                            ->label('Full name')
                            ->required()
                            ->maxLength(191)
                            ->autofocus(),

                        Select::make('registered_branch_id')
                            ->label('Registration branch')
                            ->options(
                                fn (): array =>
                                    PharmacyBranch::query()
                                        ->where(
                                            'pharmacy_id',
                                            auth()->user()
                                                ?->pharmacy_id
                                                ?? 0,
                                        )
                                        ->where('status', 'active')
                                        ->orderBy('name')
                                        ->pluck('name', 'id')
                                        ->all(),
                            )
                            ->default(
                                fn (): ?int =>
                                    auth()->user()
                                        ?->pharmacy_branch_id,
                            )
                            ->required()
                            ->searchable()
                            ->preload(),

                        TextInput::make('phone')
                            ->label('Phone number')
                            ->tel()
                            ->maxLength(50)
                            ->placeholder('+257 ...'),

                        TextInput::make('email')
                            ->email()
                            ->maxLength(191),

                        TextInput::make('address')
                            ->maxLength(255)
                            ->columnSpanFull(),

                        TextInput::make('city')
                            ->maxLength(100),

                        TextInput::make('country')
                            ->maxLength(100)
                            ->default('Burundi')
                            ->required(),

                        Select::make('preferred_language')
                            ->label('Preferred language')
                            ->options([
                                'fr' => 'French',
                                'rn' => 'Kirundi',
                                'en' => 'English',
                            ])
                            ->default('fr')
                            ->required(),

                        Select::make('status')
                            ->options([
                                'active' => 'Active',
                                'inactive' => 'Inactive',
                                'blocked' => 'Blocked',
                            ])
                            ->default('active')
                            ->required(),

                        Textarea::make('notes')
                            ->rows(4)
                            ->maxLength(3000)
                            ->columnSpanFull(),
                    ]),

                Section::make('Patient profile')
                    ->description(
                        'Enable this only when the customer also needs a patient identity.'
                    )
                    ->columnSpanFull()
                    ->schema([
                        Toggle::make('has_patient_profile')
                            ->label('Create a patient profile')
                            ->helperText(
                                'An existing patient profile cannot be removed from this form.'
                            )
                            ->default(false)
                            ->live()
                            ->disabled(
                                fn (?Customer $record): bool =>
                                    $record?->patientProfile()
                                        ->exists()
                                    ?? false,
                            )
                            ->dehydrated(),
                    ]),

                Section::make('Basic patient details')
                    ->description(
                        'Detailed clinical history will be managed separately in SLOT 17.'
                    )
                    ->columnSpanFull()
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                    ])
                    ->visible(
                        fn (Get $get): bool =>
                            (bool) $get('has_patient_profile'),
                    )
                    ->schema([
                        DatePicker::make('patient_date_of_birth')
                            ->label('Date of birth')
                            ->native(false)
                            ->displayFormat('d M Y')
                            ->maxDate(today()),

                        Select::make('patient_sex')
                            ->label('Sex')
                            ->options([
                                'female' => 'Female',
                                'male' => 'Male',
                                'other' => 'Other',
                                'unspecified' => 'Unspecified',
                            ]),

                        TextInput::make(
                            'patient_emergency_contact_name'
                        )
                            ->label('Emergency contact name')
                            ->maxLength(191),

                        TextInput::make(
                            'patient_emergency_contact_phone'
                        )
                            ->label('Emergency contact phone')
                            ->tel()
                            ->maxLength(50),

                        TextInput::make(
                            'patient_emergency_contact_relation'
                        )
                            ->label('Relationship')
                            ->maxLength(100),
                    ]),
            ]);
    }
}