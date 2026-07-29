<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\User;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Account information')
                    ->description('Identity and authentication details.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('email')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),

                        TextInput::make('password')
    ->label('Password')
    ->password()
    ->autocomplete('new-password')
    ->afterStateHydrated(
        fn (TextInput $component) => $component->state(null)
    )
    ->minLength(12)
    ->required(fn (string $operation): bool => $operation === 'create')
    ->dehydrated(fn (?string $state): bool => filled($state))
    ->helperText(
        'Required when creating an account. Leave empty while editing to preserve the current password.'
    ),

                        DateTimePicker::make('email_verified_at')
                            ->label('Email verified at')
                            ->seconds(false),
                    ]),

                Section::make('Access and security')
                    ->description('Role, account state and blocking details.')
                    ->columns(2)
                    ->schema([
                        Select::make('roles')
                            ->label('Role')
                            ->disabled(
                                fn (?User $record): bool =>
                                    $record?->is(auth()->user()) ?? false
                            )
                            ->relationship(name: 'roles', titleAttribute: 'name')
                            ->multiple()
                            ->maxItems(1)
                            ->searchable()
                            ->preload()
                            ->required()
                            ->helperText('Each account currently receives one primary role.'),

                        Toggle::make('is_active')
                            ->label('Active account')
                            ->default(true)
                            ->disabled(
                                fn (?User $record): bool =>
                                    $record?->is(auth()->user()) ?? false
                            ),

                        Textarea::make('blocked_reason')
                            ->label('Blocking reason')
                            ->rows(3)
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}