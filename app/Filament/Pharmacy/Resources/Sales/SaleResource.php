<?php

namespace App\Filament\Pharmacy\Resources\Sales;

use App\Filament\Pharmacy\Resources\Sales\Pages\CreateSale;
use App\Filament\Pharmacy\Resources\Sales\Pages\ListSales;
use App\Filament\Pharmacy\Resources\Sales\Pages\ViewSale;
use App\Filament\Pharmacy\Resources\Sales\Schemas\SaleForm;
use App\Filament\Pharmacy\Resources\Sales\Schemas\SaleInfolist;
use App\Filament\Pharmacy\Resources\Sales\Tables\SalesTable;
use App\Models\Sale;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class SaleResource extends Resource
{
    protected static ?string $model = Sale::class;

    protected static string | BackedEnum | null
        $navigationIcon = 'heroicon-o-shopping-cart';

    protected static string | UnitEnum | null
        $navigationGroup = 'Sales';

    protected static ?string $navigationLabel =
        'POS Sales';

    protected static ?string $modelLabel =
        'POS Sale';

    protected static ?string $pluralModelLabel =
        'POS Sales';

    protected static ?string $recordTitleAttribute =
        'sale_number';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return SaleForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return SaleInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SalesTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where(
                'pharmacy_id',
                auth()->user()?->pharmacy_id ?? 0,
            )
            ->with([
                'branch',
                'cashier',
                'items.pharmacyMedicine.medicine',
                'items.batchAllocations.medicineBatch',
                'payments.receivedByUser',
                'voidedByUser',
                'voidRecord.voidedByUser',
            ]);
    }

    public static function canViewAny(): bool
    {
        return static::canViewSales();
    }

    public static function canView(Model $record): bool
    {
        return static::canViewSales()
            && (int) $record->pharmacy_id
                === (int) auth()->user()?->pharmacy_id;
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();

        return Filament::getCurrentPanel()?->getId()
                === 'pharmacy'
            && filled($user?->pharmacy_id)
            && ($user?->can('sales.manage') ?? false);
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    private static function canViewSales(): bool
    {
        $user = auth()->user();

        return Filament::getCurrentPanel()?->getId()
                === 'pharmacy'
            && filled($user?->pharmacy_id)
            && ($user?->can('sales.view') ?? false);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSales::route('/'),
            'create' => CreateSale::route('/create'),
            'view' => ViewSale::route('/{record}'),
        ];
    }
}