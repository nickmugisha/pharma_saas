<?php

namespace App\Filament\Pharmacy\Resources\PurchaseOrders;

use App\Filament\Pharmacy\Resources\PurchaseOrders\Pages\CreatePurchaseOrder;
use App\Filament\Pharmacy\Resources\PurchaseOrders\Pages\EditPurchaseOrder;
use App\Filament\Pharmacy\Resources\PurchaseOrders\Pages\ListPurchaseOrders;
use App\Filament\Pharmacy\Resources\PurchaseOrders\Schemas\PurchaseOrderForm;
use App\Filament\Pharmacy\Resources\PurchaseOrders\Tables\PurchaseOrdersTable;
use App\Models\PurchaseOrder;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class PurchaseOrderResource extends Resource
{
    protected static ?string $model = PurchaseOrder::class;

    protected static string | BackedEnum | null $navigationIcon =
        'heroicon-o-clipboard-document-list';

    protected static string | UnitEnum | null $navigationGroup =
        'Purchasing';

    protected static ?string $navigationLabel = 'Purchase Orders';

    protected static ?string $modelLabel = 'Purchase Order';

    protected static ?string $pluralModelLabel = 'Purchase Orders';

    protected static ?string $recordTitleAttribute = 'order_number';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return PurchaseOrderForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PurchaseOrdersTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'supplier',
                'branch',
            ])
            ->withCount('items')
            ->where(
                'pharmacy_id',
                auth()->user()?->pharmacy_id ?? 0,
            );
    }

    public static function canViewAny(): bool
    {
        return static::canViewPurchases();
    }

    public static function canCreate(): bool
    {
        return static::canManagePurchases();
    }

    public static function canEdit(Model $record): bool
    {
        return static::canManagePurchases()
            && (int) $record->pharmacy_id
                === (int) auth()->user()?->pharmacy_id;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    private static function canViewPurchases(): bool
    {
        $user = auth()->user();

        return Filament::getCurrentPanel()?->getId() === 'pharmacy'
            && filled($user?->pharmacy_id)
            && ($user?->can('purchases.view') ?? false);
    }

    private static function canManagePurchases(): bool
    {
        $user = auth()->user();

        return Filament::getCurrentPanel()?->getId() === 'pharmacy'
            && filled($user?->pharmacy_id)
            && ($user?->can('purchases.manage') ?? false);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPurchaseOrders::route('/'),
            'create' => CreatePurchaseOrder::route('/create'),
            'edit' => EditPurchaseOrder::route('/{record}/edit'),
        ];
    }
}