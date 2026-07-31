<?php

namespace App\Filament\Pharmacy\Resources\MedicineBatches;

use App\Filament\Pharmacy\Resources\MedicineBatches\Pages\ListMedicineBatches;
use App\Filament\Pharmacy\Resources\MedicineBatches\Schemas\MedicineBatchForm;
use App\Filament\Pharmacy\Resources\MedicineBatches\Tables\MedicineBatchesTable;
use App\Models\MedicineBatch;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class MedicineBatchResource extends Resource
{
    protected static ?string $model = MedicineBatch::class;

    protected static string | BackedEnum | null $navigationIcon =
        'heroicon-o-archive-box';

    protected static string | UnitEnum | null $navigationGroup =
        'Inventory';

    protected static ?string $navigationLabel = 'Medicine Batches';

    protected static ?string $recordTitleAttribute = 'batch_number';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return MedicineBatchForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MedicineBatchesTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'pharmacyMedicine.medicine',
                'branch',
                'supplier',
            ])
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
            'index' => ListMedicineBatches::route('/'),
        ];
    }
}