<?php

namespace App\Filament\Pharmacy\Resources\Suppliers\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SupplierForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Supplier identity')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Supplier name')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('contact_person')
                            ->label('Contact person')
                            ->maxLength(255),

                        TextInput::make('registration_number')
                            ->label('Registration number')
                            ->maxLength(255),

                        TextInput::make('tax_number')
                            ->label('Tax number')
                            ->maxLength(255),

                        Select::make('status')
                            ->options([
                                'active' => 'Active',
                                'inactive' => 'Inactive',
                                'blocked' => 'Blocked',
                            ])
                            ->default('active')
                            ->required(),
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
                            ->label('Alternate phone')
                            ->tel()
                            ->maxLength(30),

                        TextInput::make('city')
                            ->maxLength(255),

                        TextInput::make('province')
                            ->maxLength(255),

                        TextInput::make('country')
                            ->default('Burundi')
                            ->required()
                            ->maxLength(255),

                        Textarea::make('address')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),

                Section::make('Commercial terms')
                    ->columns(2)
                    ->schema([
                        TextInput::make('payment_terms_days')
                            ->label('Payment terms')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->suffix('days')
                            ->required(),

                        TextInput::make('credit_limit')
                            ->label('Credit limit')
                            ->numeric()
                            ->minValue(0)
                            ->suffix('BIF'),

                        Textarea::make('notes')
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}