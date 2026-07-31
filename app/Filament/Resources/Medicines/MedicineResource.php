<?php

namespace App\Filament\Resources\Medicines;

use App\Filament\Resources\Medicines\Pages\CreateMedicine;
use App\Filament\Resources\Medicines\Pages\EditMedicine;
use App\Filament\Resources\Medicines\Pages\ListMedicines;
use App\Filament\Resources\Medicines\Schemas\MedicineForm;
use App\Filament\Resources\Medicines\Tables\MedicinesTable;
use App\Models\Medicine;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class MedicineResource extends Resource
{
    protected static ?string $model = Medicine::class;

    protected static string | BackedEnum | null $navigationIcon =
        'heroicon-o-cube';

    protected static string | UnitEnum | null $navigationGroup =
        'Medicine Catalogue';

    protected static ?string $navigationLabel = 'Medicines';

    protected static ?string $modelLabel = 'Medicine';

    protected static ?string $pluralModelLabel = 'Medicines';

    protected static ?string $recordTitleAttribute = 'brand_name';

    protected static ?int $navigationSort = 0;

    public static function form(Schema $schema): Schema
    {
        return MedicineForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MedicinesTable::configure($table);
    }

    public static function canViewAny(): bool
    {
        return static::canViewCatalogue();
    }

    public static function canCreate(): bool
    {
        return static::canManageCatalogue();
    }

    public static function canEdit(Model $record): bool
    {
        return static::canManageCatalogue();
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    private static function canViewCatalogue(): bool
    {
        return Filament::getCurrentPanel()?->getId() === 'super-admin'
            && (auth()->user()?->can('medicines.view') ?? false);
    }

    private static function canManageCatalogue(): bool
    {
        return Filament::getCurrentPanel()?->getId() === 'super-admin'
            && (auth()->user()?->can('medicines.manage') ?? false);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMedicines::route('/'),
            'create' => CreateMedicine::route('/create'),
            'edit' => EditMedicine::route('/{record}/edit'),
        ];
    }
}