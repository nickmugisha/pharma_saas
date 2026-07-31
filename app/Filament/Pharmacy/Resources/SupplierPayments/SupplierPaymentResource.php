<?php

namespace App\Filament\Pharmacy\Resources\SupplierPayments;

use App\Filament\Pharmacy\Resources\SupplierPayments\Pages\CreateSupplierPayment;
use App\Filament\Pharmacy\Resources\SupplierPayments\Pages\EditSupplierPayment;
use App\Filament\Pharmacy\Resources\SupplierPayments\Pages\ListSupplierPayments;
use App\Filament\Pharmacy\Resources\SupplierPayments\Schemas\SupplierPaymentForm;
use App\Filament\Pharmacy\Resources\SupplierPayments\Tables\SupplierPaymentsTable;
use App\Models\SupplierPayment;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class SupplierPaymentResource extends Resource
{
    protected static ?string $model = SupplierPayment::class;

    protected static string | BackedEnum | null $navigationIcon =
        'heroicon-o-banknotes';

    protected static string | UnitEnum | null $navigationGroup =
        'Purchasing';

    protected static ?string $navigationLabel = 'Supplier Payments';

    protected static ?string $modelLabel = 'Supplier Payment';

    protected static ?string $pluralModelLabel = 'Supplier Payments';

    protected static ?string $recordTitleAttribute = 'payment_number';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return SupplierPaymentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SupplierPaymentsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'invoice',
                'supplier',
            ])
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
            'index' => ListSupplierPayments::route('/'),
            'create' => CreateSupplierPayment::route('/create'),
            'edit' => EditSupplierPayment::route('/{record}/edit'),
        ];
    }
}