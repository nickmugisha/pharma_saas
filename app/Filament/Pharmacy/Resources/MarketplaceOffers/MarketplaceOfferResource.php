<?php

namespace App\Filament\Pharmacy\Resources\MarketplaceOffers;

use App\Filament\Pharmacy\Resources\MarketplaceOffers\Pages\CreateMarketplaceOffer;
use App\Filament\Pharmacy\Resources\MarketplaceOffers\Pages\EditMarketplaceOffer;
use App\Filament\Pharmacy\Resources\MarketplaceOffers\Pages\ListMarketplaceOffers;
use App\Filament\Pharmacy\Resources\MarketplaceOffers\Schemas\MarketplaceOfferForm;
use App\Filament\Pharmacy\Resources\MarketplaceOffers\Tables\MarketplaceOffersTable;
use App\Models\MarketplaceOffer;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class MarketplaceOfferResource extends Resource
{
    protected static ?string $model = MarketplaceOffer::class;

    protected static string | BackedEnum | null $navigationIcon =
        'heroicon-o-globe-alt';

    protected static string | UnitEnum | null $navigationGroup = 'Sales';

    protected static ?string $navigationLabel = 'Marketplace Offers';

    protected static ?string $modelLabel = 'Marketplace Offer';

    protected static ?string $pluralModelLabel = 'Marketplace Offers';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return MarketplaceOfferForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MarketplaceOffersTable::configure($table);
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
                'branch',
                'pharmacyMedicine.medicine.dosageForm',
            ]);
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return Filament::getCurrentPanel()?->getId() === 'pharmacy'
            && filled($user?->pharmacy_id)
            && ($user?->can('marketplace.offers.view') ?? false);
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('marketplace.offers.manage') ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        $user = auth()->user();

        return ($user?->can('marketplace.offers.manage') ?? false)
            && (int) $record->pharmacy_id === (int) $user?->pharmacy_id
            && (
                $user?->hasRole('pharmacy_owner')
                || (int) $record->pharmacy_branch_id
                    === (int) $user?->pharmacy_branch_id
            );
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMarketplaceOffers::route('/'),
            'create' => CreateMarketplaceOffer::route('/create'),
            'edit' => EditMarketplaceOffer::route('/{record}/edit'),
        ];
    }
}
