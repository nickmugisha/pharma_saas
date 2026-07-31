<?php

namespace App\Filament\Pharmacy\Resources\PharmacyMedicines;

use App\Filament\Pharmacy\Resources\PharmacyMedicines\Pages\CreatePharmacyMedicine;
use App\Filament\Pharmacy\Resources\PharmacyMedicines\Pages\EditPharmacyMedicine;
use App\Filament\Pharmacy\Resources\PharmacyMedicines\Pages\ListPharmacyMedicines;
use App\Filament\Pharmacy\Resources\PharmacyMedicines\Schemas\PharmacyMedicineForm;
use App\Filament\Pharmacy\Resources\PharmacyMedicines\Tables\PharmacyMedicinesTable;
use App\Models\PharmacyMedicine;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class PharmacyMedicineResource extends Resource
{
    protected static ?string $model = PharmacyMedicine::class;

    protected static string | BackedEnum | null $navigationIcon =
        'heroicon-o-shopping-bag';

    protected static string | UnitEnum | null $navigationGroup =
        'Medicine Management';

    protected static ?string $navigationLabel = 'My Medicines';

    protected static ?string $modelLabel = 'Pharmacy Medicine';

    protected static ?string $pluralModelLabel = 'My Medicines';

    protected static ?string $recordTitleAttribute = 'internal_sku';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return PharmacyMedicineForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PharmacyMedicinesTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'medicine.primaryImage',
                'medicine.dosageForm',
            ])
            ->where(
                'pharmacy_id',
                auth()->user()?->pharmacy_id ?? 0,
            );
    }

    public static function canViewAny(): bool
    {
        return static::canViewMedicines();
    }

    public static function canCreate(): bool
    {
        return static::canManageMedicines();
    }

    public static function canEdit(Model $record): bool
    {
        return static::canManageMedicines()
            && (int) $record->pharmacy_id
                === (int) auth()->user()?->pharmacy_id;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    private static function canViewMedicines(): bool
    {
        $user = auth()->user();

        return Filament::getCurrentPanel()?->getId() === 'pharmacy'
            && filled($user?->pharmacy_id)
            && ($user?->can('medicines.view') ?? false);
    }

    private static function canManageMedicines(): bool
    {
        $user = auth()->user();

        return Filament::getCurrentPanel()?->getId() === 'pharmacy'
            && filled($user?->pharmacy_id)
            && ($user?->can('medicines.manage') ?? false);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPharmacyMedicines::route('/'),
            'create' => CreatePharmacyMedicine::route('/create'),
            'edit' => EditPharmacyMedicine::route('/{record}/edit'),
        ];
    }
}