<?php

namespace App\Filament\Resources\Pharmacies;

use App\Filament\Resources\Pharmacies\Pages\CreatePharmacy;
use App\Filament\Resources\Pharmacies\Pages\EditPharmacy;
use App\Filament\Resources\Pharmacies\Pages\ListPharmacies;
use App\Filament\Resources\Pharmacies\Schemas\PharmacyForm;
use App\Filament\Resources\Pharmacies\Tables\PharmaciesTable;
use App\Models\Pharmacy;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class PharmacyResource extends Resource
{
    protected static ?string $model = Pharmacy::class;

    protected static string | BackedEnum | null $navigationIcon =
        'heroicon-o-building-storefront';

    protected static string | UnitEnum | null $navigationGroup =
        'Partner Pharmacies';

    protected static ?string $navigationLabel = 'Partner Pharmacies';

    protected static ?string $modelLabel = 'Partner Pharmacy';

    protected static ?string $pluralModelLabel = 'Partner Pharmacies';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return PharmacyForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PharmaciesTable::configure($table);
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('pharmacies.view') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('pharmacies.manage') ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->can('pharmacies.manage') ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPharmacies::route('/'),
            'create' => CreatePharmacy::route('/create'),
            'edit' => EditPharmacy::route('/{record}/edit'),
        ];
    }
}