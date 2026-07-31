<?php

namespace App\Filament\Resources\Manufacturers;

use App\Filament\Resources\Manufacturers\Pages\CreateManufacturer;
use App\Filament\Resources\Manufacturers\Pages\EditManufacturer;
use App\Filament\Resources\Manufacturers\Pages\ListManufacturers;
use App\Filament\Resources\Manufacturers\Schemas\ManufacturerForm;
use App\Filament\Resources\Manufacturers\Tables\ManufacturersTable;
use App\Models\Manufacturer;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class ManufacturerResource extends Resource
{
    protected static ?string $model = Manufacturer::class;

    protected static string | BackedEnum | null $navigationIcon =
        'heroicon-o-building-office';

    protected static string | UnitEnum | null $navigationGroup =
        'Medicine Catalogue';

    protected static ?string $navigationLabel = 'Manufacturers';

    protected static ?string $modelLabel = 'Manufacturer';

    protected static ?string $pluralModelLabel = 'Manufacturers';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return ManufacturerForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ManufacturersTable::configure($table);
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
            'index' => ListManufacturers::route('/'),
            'create' => CreateManufacturer::route('/create'),
            'edit' => EditManufacturer::route('/{record}/edit'),
        ];
    }
}