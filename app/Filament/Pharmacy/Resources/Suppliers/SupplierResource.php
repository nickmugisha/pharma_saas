<?php

namespace App\Filament\Pharmacy\Resources\Suppliers;

use App\Filament\Pharmacy\Resources\Suppliers\Pages\CreateSupplier;
use App\Filament\Pharmacy\Resources\Suppliers\Pages\EditSupplier;
use App\Filament\Pharmacy\Resources\Suppliers\Pages\ListSuppliers;
use App\Filament\Pharmacy\Resources\Suppliers\Schemas\SupplierForm;
use App\Filament\Pharmacy\Resources\Suppliers\Tables\SuppliersTable;
use App\Models\Supplier;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class SupplierResource extends Resource
{
    protected static ?string $model = Supplier::class;

    protected static string | BackedEnum | null $navigationIcon =
        'heroicon-o-truck';

    protected static string | UnitEnum | null $navigationGroup =
        'Purchasing';

    protected static ?string $navigationLabel = 'Suppliers';

    protected static ?string $modelLabel = 'Supplier';

    protected static ?string $pluralModelLabel = 'Suppliers';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return SupplierForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SuppliersTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where(
                'pharmacy_id',
                auth()->user()?->pharmacy_id ?? 0,
            );
    }

    public static function canViewAny(): bool
    {
        return static::canViewSuppliers();
    }

    public static function canCreate(): bool
    {
        return static::canManageSuppliers();
    }

    public static function canEdit(Model $record): bool
    {
        return static::canManageSuppliers()
            && (int) $record->pharmacy_id
                === (int) auth()->user()?->pharmacy_id;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    private static function canViewSuppliers(): bool
    {
        $user = auth()->user();

        return Filament::getCurrentPanel()?->getId() === 'pharmacy'
            && filled($user?->pharmacy_id)
            && ($user?->can('purchases.view') ?? false);
    }

    private static function canManageSuppliers(): bool
    {
        $user = auth()->user();

        return Filament::getCurrentPanel()?->getId() === 'pharmacy'
            && filled($user?->pharmacy_id)
            && ($user?->can('purchases.manage') ?? false);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSuppliers::route('/'),
            'create' => CreateSupplier::route('/create'),
            'edit' => EditSupplier::route('/{record}/edit'),
        ];
    }
}