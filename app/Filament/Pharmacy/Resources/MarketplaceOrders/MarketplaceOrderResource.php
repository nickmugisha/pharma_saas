<?php

namespace App\Filament\Pharmacy\Resources\MarketplaceOrders;

use App\Filament\Pharmacy\Resources\MarketplaceOrders\Pages\ListMarketplaceOrders;
use App\Filament\Pharmacy\Resources\MarketplaceOrders\Pages\ViewMarketplaceOrder;
use App\Filament\Pharmacy\Resources\MarketplaceOrders\Schemas\MarketplaceOrderInfolist;
use App\Filament\Pharmacy\Resources\MarketplaceOrders\Tables\MarketplaceOrdersTable;
use App\Models\MarketplaceOrder;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class MarketplaceOrderResource extends Resource
{
    protected static ?string $model = MarketplaceOrder::class;

    protected static string | BackedEnum | null $navigationIcon =
        'heroicon-o-shopping-bag';

    protected static string | UnitEnum | null $navigationGroup = 'Sales';

    protected static ?string $navigationLabel = 'Online Orders';

    protected static ?string $modelLabel = 'Online Order';

    protected static ?string $pluralModelLabel = 'Online Orders';

    protected static ?string $recordTitleAttribute = 'order_number';

    protected static ?int $navigationSort = 2;

    public static function infolist(Schema $schema): Schema
    {
        return MarketplaceOrderInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MarketplaceOrdersTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();

        return parent::getEloquentQuery()
            ->where('pharmacy_id', $user?->pharmacy_id ?? 0)
            ->when(
                $user && ! $user->hasRole('pharmacy_owner'),
                fn (Builder $query): Builder => $query->where(
                    'pharmacy_branch_id',
                    $user->pharmacy_branch_id ?? 0,
                ),
            )
            ->with([
                'user.clientProfile',
                'wallet',
                'walletPaymentTransaction',
                'walletRefundTransaction',
                'pharmacy',
                'branch',
                'items.clientPrescription',
                'items.reviewedByUser',
                'stockReservations.medicineBatch',
                'events.actorUser',
            ]);
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return Filament::getCurrentPanel()?->getId() === 'pharmacy'
            && filled($user?->pharmacy_id)
            && ($user?->can('marketplace.orders.view') ?? false);
    }

    public static function canView(Model $record): bool
    {
        $user = auth()->user();

        return static::canViewAny()
            && (int) $record->pharmacy_id === (int) $user?->pharmacy_id
            && (
                $user?->hasRole('pharmacy_owner')
                || (int) $record->pharmacy_branch_id === (int) $user?->pharmacy_branch_id
            );
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

    public static function getPages(): array
    {
        return [
            'index' => ListMarketplaceOrders::route('/'),
            'view' => ViewMarketplaceOrder::route('/{record}'),
        ];
    }
}
