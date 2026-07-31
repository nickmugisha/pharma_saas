<?php

namespace App\Filament\Resources\Molecules;

use App\Filament\Resources\Molecules\Pages\CreateMolecule;
use App\Filament\Resources\Molecules\Pages\EditMolecule;
use App\Filament\Resources\Molecules\Pages\ListMolecules;
use App\Filament\Resources\Molecules\Schemas\MoleculeForm;
use App\Filament\Resources\Molecules\Tables\MoleculesTable;
use App\Models\Molecule;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class MoleculeResource extends Resource
{
    protected static ?string $model = Molecule::class;

    protected static string | BackedEnum | null $navigationIcon =
        'heroicon-o-beaker';

    protected static string | UnitEnum | null $navigationGroup =
        'Medicine Catalogue';

    protected static ?string $navigationLabel = 'Molecules';

    protected static ?string $modelLabel = 'Molecule';

    protected static ?string $pluralModelLabel = 'Molecules';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return MoleculeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MoleculesTable::configure($table);
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
            'index' => ListMolecules::route('/'),
            'create' => CreateMolecule::route('/create'),
            'edit' => EditMolecule::route('/{record}/edit'),
        ];
    }
}