<?php

namespace App\Filament\Resources\ClientWallets;

use App\Filament\Resources\ClientWallets\Pages\ListClientWallets;
use App\Filament\Resources\ClientWallets\Pages\ViewClientWallet;
use App\Filament\Resources\ClientWallets\Schemas\ClientWalletInfolist;
use App\Filament\Resources\ClientWallets\Tables\ClientWalletsTable;
use App\Models\ClientWallet;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class ClientWalletResource extends Resource
{
    protected static ?string $model = ClientWallet::class;

    protected static string | BackedEnum | null $navigationIcon =
        'heroicon-o-wallet';

    protected static string | UnitEnum | null $navigationGroup =
        'Subscriptions & Finance';

    protected static ?string $navigationLabel = 'Client Accounts & Wallets';

    protected static ?string $modelLabel = 'Client Wallet';

    protected static ?string $pluralModelLabel = 'Client Accounts & Wallets';

    protected static ?string $recordTitleAttribute = 'wallet_number';

    protected static ?int $navigationSort = 3;

    public static function infolist(Schema $schema): Schema
    {
        return ClientWalletInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ClientWalletsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'user.clientProfile',
            ])
            ->withCount([
                'transactions',
                'fundingRequests',
            ]);
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('wallets.view') ?? false;
    }

    public static function canView(Model $record): bool
    {
        return static::canViewAny();
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
            'index' => ListClientWallets::route('/'),
            'view' => ViewClientWallet::route('/{record}'),
        ];
    }
}
