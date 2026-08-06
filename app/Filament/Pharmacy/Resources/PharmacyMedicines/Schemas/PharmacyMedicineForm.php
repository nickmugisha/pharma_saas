<?php

namespace App\Filament\Pharmacy\Resources\PharmacyMedicines\Schemas;

use App\Models\Medicine;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class PharmacyMedicineForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Medicine selection')
                    ->description(
                        'Select an approved medicine from the central catalogue.'
                    )
                    ->schema([
                        Select::make('medicine_id')
                            ->label('Medicine')
                            ->relationship(
                                name: 'medicine',
                                titleAttribute: 'brand_name',
                                modifyQueryUsing: fn (Builder $query): Builder =>
                                    $query
                                        ->where('approval_status', 'approved')
                                        ->where('is_active', true),
                            )
                            ->getOptionLabelFromRecordUsing(
                                fn (Medicine $record): string => collect([
                                    $record->brand_name,
                                    $record->strength,
                                    $record->dosageForm?->name,
                                ])->filter()->implode(' — '),
                            )
                            ->searchable([
                                'brand_name',
                                'generic_name',
                                'barcode',
                            ])
                            ->preload()
                            ->required()
                            ->disabledOn('edit'),

                        TextInput::make('internal_sku')
                            ->label('Internal SKU')
                            ->placeholder('Example: PARA-500-TAB')
                            ->maxLength(100),
                    ]),

                Section::make('Pricing')
                    ->columns(2)
                    ->schema([
                        TextInput::make('selling_price')
                            ->label('Selling price')
                            ->numeric()
                            ->minValue(0)
                            ->suffix('BIF')
                            ->required(),

                        TextInput::make('online_price')
                            ->label('Online price')
                            ->helperText(
                                'Leave empty to use the normal selling price.'
                            )
                            ->numeric()
                            ->minValue(0)
                            ->suffix('BIF'),

                        Hidden::make('currency')
                            ->default('BIF'),
                    ]),

                Section::make('Availability and public marketplace')
                    ->columns(2)
                    ->schema([
                        Toggle::make('is_available')
                            ->label('Available for sale')
                            ->default(true),

                        Toggle::make('is_visible_online')
                            ->label('Visible in public marketplace')
                            ->default(false),

                        Select::make('status')
                            ->options([
                                'active' => 'Active',
                                'inactive' => 'Inactive',
                                'suspended' => 'Suspended',
                            ])
                            ->default('active')
                            ->required(),

                        Textarea::make('pharmacy_description')
                            ->label('Pharmacy description')
                            ->helperText(
                                'Optional description displayed by this pharmacy.'
                            )
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}