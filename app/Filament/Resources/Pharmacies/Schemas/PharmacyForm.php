<?php

namespace App\Filament\Resources\Pharmacies\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PharmacyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Pharmacy identity')
                    ->description('Legal and operational information.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('legal_name')
                            ->label('Legal name')
                            ->maxLength(255),

                        TextInput::make('registration_number')
                            ->label('Registration number')
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),

                        TextInput::make('license_number')
                            ->label('Pharmacy licence number')
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),

                        TextInput::make('tax_number')
                            ->label('Tax number')
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                    ]),

                Section::make('Contact and location')
                    ->columns(2)
                    ->schema([
                        TextInput::make('email')
                            ->email()
                            ->maxLength(255),

                        TextInput::make('phone')
                            ->tel()
                            ->required()
                            ->maxLength(30),

                        TextInput::make('alternate_phone')
                            ->label('Alternative phone')
                            ->tel()
                            ->maxLength(30),

                        TextInput::make('city')
                            ->maxLength(255),

                        TextInput::make('province')
                            ->maxLength(255),

                        TextInput::make('country')
                            ->required()
                            ->default('Burundi')
                            ->maxLength(255),

                        Textarea::make('address')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),

                Section::make('Partnership status')
                    ->description('Platform approval and compliance state.')
                    ->columns(2)
                    ->schema([
                        Select::make('status')
                            ->options([
                                'pending' => 'Pending review',
                                'approved' => 'Approved',
                                'suspended' => 'Suspended',
                                'rejected' => 'Rejected',
                            ])
                            ->default('pending')
                            ->required(),

                        TextInput::make('suspension_reason')
                            ->label('Suspension or rejection reason')
                            ->maxLength(255),

                        Textarea::make('notes')
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}