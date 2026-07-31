<?php

namespace App\Filament\Resources\DosageForms\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DosageFormForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Dosage form information')
                    ->description('Examples include tablets, capsules, syrups and injections.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Dosage form')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),

                        TextInput::make('abbreviation')
                            ->label('Abbreviation')
                            ->maxLength(30)
                            ->placeholder('Example: TAB'),

                        Textarea::make('description')
                            ->rows(4)
                            ->columnSpanFull(),

                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                    ]),
            ]);
    }
}