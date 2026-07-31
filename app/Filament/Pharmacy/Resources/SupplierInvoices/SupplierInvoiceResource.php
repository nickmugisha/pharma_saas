<?php

namespace App\Filament\Pharmacy\Resources\SupplierInvoices;

use App\Filament\Pharmacy\Resources\SupplierInvoices\Pages\CreateSupplierInvoice;
use App\Filament\Pharmacy\Resources\SupplierInvoices\Pages\EditSupplierInvoice;
use App\Filament\Pharmacy\Resources\SupplierInvoices\Pages\ListSupplierInvoices;
use App\Filament\Pharmacy\Resources\SupplierInvoices\Schemas\SupplierInvoiceForm;
use App\Filament\Pharmacy\Resources\SupplierInvoices\Tables\SupplierInvoicesTable;
use App\Models\SupplierInvoice;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class SupplierInvoiceResource extends Resource
{
    protected static ?string $model = SupplierInvoice::class;

    protected static string | BackedEnum | null $navigationIcon =
        'heroicon-o-document-currency-dollar';

    protected static string | UnitEnum | null $navigationGroup =
        'Purchasing';

    protected static ?string $navigationLabel = 'Supplier Invoices';

    protected static ?string $modelLabel = 'Supplier Invoice';

    protected static ?string $pluralModelLabel = 'Supplier Invoices';

    protected static ?string $recordTitleAttribute = 'invoice_number';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return SupplierInvoiceForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SupplierInvoicesTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'supplier',
                'purchaseOrder',
                'branch',
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
            'index' => ListSupplierInvoices::route('/'),
            'create' => CreateSupplierInvoice::route('/create'),
            'edit' => EditSupplierInvoice::route('/{record}/edit'),
        ];
    }
}