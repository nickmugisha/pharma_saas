<?php

namespace App\Filament\Resources\MedicineCategories;

use App\Filament\Resources\MedicineCategories\Pages\CreateMedicineCategory;
use App\Filament\Resources\MedicineCategories\Pages\EditMedicineCategory;
use App\Filament\Resources\MedicineCategories\Pages\ListMedicineCategories;
use App\Filament\Resources\MedicineCategories\Schemas\MedicineCategoryForm;
use App\Filament\Resources\MedicineCategories\Tables\MedicineCategoriesTable;
use App\Models\MedicineCategory;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class MedicineCategoryResource extends Resource
{
    protected static ?string $model = MedicineCategory::class;

    protected static string | BackedEnum | null $navigationIcon =
        'heroicon-o-tag';

    protected static string | UnitEnum | null $navigationGroup =
        'Medicine Catalogue';

    protected static ?string $navigationLabel = 'Categories';

    protected static ?string $modelLabel = 'Medicine Category';

    protected static ?string $pluralModelLabel = 'Medicine Categories';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return MedicineCategoryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MedicineCategoriesTable::configure($table);
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
            'index' => ListMedicineCategories::route('/'),
            'create' => CreateMedicineCategory::route('/create'),
            'edit' => EditMedicineCategory::route('/{record}/edit'),
        ];
    }
}