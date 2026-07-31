<?php

namespace App\Filament\Pharmacy\Resources\BranchMedicineSettings;

use App\Filament\Pharmacy\Resources\BranchMedicineSettings\Pages\CreateBranchMedicineSetting;
use App\Filament\Pharmacy\Resources\BranchMedicineSettings\Pages\EditBranchMedicineSetting;
use App\Filament\Pharmacy\Resources\BranchMedicineSettings\Pages\ListBranchMedicineSettings;
use App\Filament\Pharmacy\Resources\BranchMedicineSettings\Schemas\BranchMedicineSettingForm;
use App\Filament\Pharmacy\Resources\BranchMedicineSettings\Tables\BranchMedicineSettingsTable;
use App\Models\BranchMedicineSetting;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class BranchMedicineSettingResource extends Resource
{
    protected static ?string $model = BranchMedicineSetting::class;

    protected static string | BackedEnum | null $navigationIcon =
        'heroicon-o-adjustments-horizontal';

    protected static string | UnitEnum | null $navigationGroup =
        'Inventory';

    protected static ?string $navigationLabel =
        'Stock Alert Settings';

    protected static ?string $modelLabel =
        'Branch Stock Setting';

    protected static ?string $pluralModelLabel =
        'Branch Stock Settings';

    protected static ?string $recordTitleAttribute = 'id';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return BranchMedicineSettingForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BranchMedicineSettingsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'branch',
                'pharmacyMedicine.medicine',
            ])
            ->where(
                'pharmacy_id',
                auth()->user()?->pharmacy_id ?? 0,
            );
    }

    public static function canViewAny(): bool
    {
        return static::canViewStock();
    }

    public static function canCreate(): bool
    {
        return static::canManageStock();
    }

    public static function canEdit(Model $record): bool
    {
        return static::canManageStock()
            && (int) $record->pharmacy_id
                === (int) auth()->user()?->pharmacy_id;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    private static function canViewStock(): bool
    {
        $user = auth()->user();

        return Filament::getCurrentPanel()?->getId() === 'pharmacy'
            && filled($user?->pharmacy_id)
            && ($user?->can('stock.view') ?? false);
    }

    private static function canManageStock(): bool
    {
        $user = auth()->user();

        return Filament::getCurrentPanel()?->getId() === 'pharmacy'
            && filled($user?->pharmacy_id)
            && ($user?->can('stock.manage') ?? false);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBranchMedicineSettings::route('/'),
            'create' => CreateBranchMedicineSetting::route('/create'),
            'edit' => EditBranchMedicineSetting::route('/{record}/edit'),
        ];
    }
}