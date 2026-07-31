<?php

namespace App\Filament\Pharmacy\Resources\StockMovements;

use App\Filament\Pharmacy\Resources\StockMovements\Pages\ListStockMovements;
use App\Filament\Pharmacy\Resources\StockMovements\Schemas\StockMovementForm;
use App\Filament\Pharmacy\Resources\StockMovements\Tables\StockMovementsTable;
use App\Models\StockMovement;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class StockMovementResource extends Resource
{
    protected static ?string $model = StockMovement::class;

    protected static string | BackedEnum | null $navigationIcon =
        'heroicon-o-arrows-right-left';

    protected static string | UnitEnum | null $navigationGroup =
        'Inventory';

    protected static ?string $navigationLabel = 'Stock Movements';

    protected static ?string $recordTitleAttribute = 'uuid';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return StockMovementForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StockMovementsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'pharmacyMedicine.medicine',
                'medicineBatch',
                'branch',
                'createdByUser',
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
            'index' => ListStockMovements::route('/'),
        ];
    }
}