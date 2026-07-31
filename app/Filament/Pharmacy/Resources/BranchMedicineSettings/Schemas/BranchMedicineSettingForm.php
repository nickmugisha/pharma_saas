<?php

namespace App\Filament\Pharmacy\Resources\BranchMedicineSettings\Schemas;

use App\Models\PharmacyMedicine;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class BranchMedicineSettingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Medicine and branch')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        Select::make('pharmacy_branch_id')
                            ->label('Branch')
                            ->relationship(
                                name: 'branch',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn (Builder $query): Builder =>
                                    $query
                                        ->where(
                                            'pharmacy_id',
                                            auth()->user()?->pharmacy_id ?? 0,
                                        )
                                        ->where('status', 'active'),
                            )
                            ->searchable()
                            ->preload()
                            ->required()
                            ->disabledOn('edit'),

                        Select::make('pharmacy_medicine_id')
                            ->label('Medicine')
                            ->relationship(
                                name: 'pharmacyMedicine',
                                titleAttribute: 'id',
                                modifyQueryUsing: fn (Builder $query): Builder =>
                                    $query
                                        ->with('medicine')
                                        ->where(
                                            'pharmacy_id',
                                            auth()->user()?->pharmacy_id ?? 0,
                                        )
                                        ->where('status', 'active'),
                            )
                            ->getOptionLabelFromRecordUsing(
                                fn (PharmacyMedicine $record): string =>
                                    $record->medicine?->brand_name
                                    ?? $record->medicine?->generic_name
                                    ?? "Medicine #{$record->id}",
                            )
                            ->searchable()
                            ->preload()
                            ->required()
                            ->disabledOn('edit'),
                    ]),

                Section::make('Stock alert settings')
                    ->columnSpanFull()
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                    ])
                    ->schema([
                        TextInput::make('minimum_stock_level')
                            ->label('Minimum stock level')
                            ->numeric()
                            ->minValue(0)
                            ->step(0.001)
                            ->default(0)
                            ->required(),

                        TextInput::make('reorder_quantity')
                            ->label('Recommended reorder quantity')
                            ->numeric()
                            ->minValue(0)
                            ->step(0.001)
                            ->default(0)
                            ->required(),

                        TextInput::make('expiry_warning_days')
                            ->label('Expiry warning period')
                            ->helperText(
                                'Create an alert when a batch reaches this number of remaining days.'
                            )
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->maxValue(730)
                            ->default(90)
                            ->suffix('days')
                            ->required(),

                        Toggle::make('alerts_enabled')
                            ->label('Enable inventory alerts')
                            ->default(true)
                            ->inline(false),
                    ]),
            ]);
    }
}