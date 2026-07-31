<?php

namespace App\Filament\Pharmacy\Resources\InventoryAlerts;

use App\Filament\Pharmacy\Resources\InventoryAlerts\Pages\ListInventoryAlerts;
use App\Filament\Pharmacy\Resources\InventoryAlerts\Pages\ViewInventoryAlert;
use App\Filament\Pharmacy\Resources\InventoryAlerts\Schemas\InventoryAlertInfolist;
use App\Filament\Pharmacy\Resources\InventoryAlerts\Tables\InventoryAlertsTable;
use App\Models\InventoryAlert;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class InventoryAlertResource extends Resource
{
    protected static ?string $model = InventoryAlert::class;

    protected static string | BackedEnum | null $navigationIcon =
        'heroicon-o-bell-alert';

    protected static string | UnitEnum | null $navigationGroup =
        'Inventory';

    protected static ?string $navigationLabel =
        'Inventory Alerts';

    protected static ?string $modelLabel =
        'Inventory Alert';

    protected static ?string $pluralModelLabel =
        'Inventory Alerts';

    protected static ?string $recordTitleAttribute = 'uuid';

    protected static ?int $navigationSort = 5;

    public static function infolist(Schema $schema): Schema
    {
        return InventoryAlertInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InventoryAlertsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'branch',
                'pharmacyMedicine.medicine',
                'medicineBatch',
                'acknowledgedByUser',
                'resolvedByUser',
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

    public static function canView(Model $record): bool
    {
        return static::canViewStock()
            && (int) $record->pharmacy_id
                === (int) auth()->user()?->pharmacy_id;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
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

    public static function getPages(): array
    {
        return [
            'index' => ListInventoryAlerts::route('/'),
            'view' => ViewInventoryAlert::route('/{record}'),
        ];
    }
}