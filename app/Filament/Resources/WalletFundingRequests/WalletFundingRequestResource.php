<?php

namespace App\Filament\Resources\WalletFundingRequests;

use App\Filament\Resources\WalletFundingRequests\Pages\ListWalletFundingRequests;
use App\Filament\Resources\WalletFundingRequests\Pages\ViewWalletFundingRequest;
use App\Filament\Resources\WalletFundingRequests\Schemas\WalletFundingRequestInfolist;
use App\Filament\Resources\WalletFundingRequests\Tables\WalletFundingRequestsTable;
use App\Models\WalletFundingRequest;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class WalletFundingRequestResource extends Resource
{
    protected static ?string $model = WalletFundingRequest::class;

    protected static string | BackedEnum | null $navigationIcon =
        'heroicon-o-arrow-down-on-square-stack';

    protected static string | UnitEnum | null $navigationGroup =
        'Subscriptions & Finance';

    protected static ?string $navigationLabel = 'Wallet Funding';

    protected static ?string $modelLabel = 'Wallet Funding Request';

    protected static ?string $pluralModelLabel = 'Wallet Funding Requests';

    protected static ?string $recordTitleAttribute = 'request_number';

    protected static ?int $navigationSort = 4;

    public static function infolist(Schema $schema): Schema
    {
        return WalletFundingRequestInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WalletFundingRequestsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with([
            'user',
            'wallet',
            'reviewedByUser',
            'walletTransaction',
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
            'index' => ListWalletFundingRequests::route('/'),
            'view' => ViewWalletFundingRequest::route('/{record}'),
        ];
    }
}
