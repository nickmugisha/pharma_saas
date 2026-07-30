<?php

namespace App\Filament\Pharmacy\Resources\PharmacyBranches\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PharmacyBranchForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Branch identity')
                    ->description('Branch identification and operational status.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Branch name')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('code')
                            ->label('Branch code')
                            ->required()
                            ->maxLength(50)
                            ->helperText('Examples: HQ, ROHERO, KAMENGE.'),

                        Toggle::make('is_main')
                            ->label('Main branch')
                            ->default(false),

                        Select::make('status')
                            ->options([
                                'active' => 'Active',
                                'inactive' => 'Inactive',
                                'suspended' => 'Suspended',
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
                            ->maxLength(30),

                        TextInput::make('city')
                            ->maxLength(255),

                        TextInput::make('province')
                            ->maxLength(255),

                        Textarea::make('address')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}