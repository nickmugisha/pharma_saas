<?php

namespace App\Filament\Pharmacy\Resources\PurchaseReceipts;

use App\Filament\Pharmacy\Resources\PurchaseReceipts\Pages\ListPurchaseReceipts;
use App\Filament\Pharmacy\Resources\PurchaseReceipts\Schemas\PurchaseReceiptForm;
use App\Filament\Pharmacy\Resources\PurchaseReceipts\Tables\PurchaseReceiptsTable;
use App\Models\PurchaseReceipt;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class PurchaseReceiptResource extends Resource
{
    protected static ?string $model = PurchaseReceipt::class;

    protected static string | BackedEnum | null $navigationIcon =
        'heroicon-o-clipboard-document-check';

    protected static string | UnitEnum | null $navigationGroup =
        'Inventory';

    protected static ?string $navigationLabel = 'Purchase Receipts';

    protected static ?string $recordTitleAttribute = 'receipt_number';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return PurchaseReceiptForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PurchaseReceiptsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'purchaseOrder',
                'supplier',
                'branch',
                'receivedByUser',
            ])
            ->withCount('items')
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
            'index' => ListPurchaseReceipts::route('/'),
        ];
    }
}