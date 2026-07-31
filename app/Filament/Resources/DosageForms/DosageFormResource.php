<?php

namespace App\Filament\Resources\DosageForms;

use App\Filament\Resources\DosageForms\Pages\CreateDosageForm;
use App\Filament\Resources\DosageForms\Pages\EditDosageForm;
use App\Filament\Resources\DosageForms\Pages\ListDosageForms;
use App\Filament\Resources\DosageForms\Schemas\DosageFormForm;
use App\Filament\Resources\DosageForms\Tables\DosageFormsTable;
use App\Models\DosageForm;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class DosageFormResource extends Resource
{
    protected static ?string $model = DosageForm::class;

    protected static string | BackedEnum | null $navigationIcon =
        'heroicon-o-squares-2x2';

    protected static string | UnitEnum | null $navigationGroup =
        'Medicine Catalogue';

    protected static ?string $navigationLabel = 'Dosage Forms';

    protected static ?string $modelLabel = 'Dosage Form';

    protected static ?string $pluralModelLabel = 'Dosage Forms';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return DosageFormForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DosageFormsTable::configure($table);
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
            'index' => ListDosageForms::route('/'),
            'create' => CreateDosageForm::route('/create'),
            'edit' => EditDosageForm::route('/{record}/edit'),
        ];
    }
}