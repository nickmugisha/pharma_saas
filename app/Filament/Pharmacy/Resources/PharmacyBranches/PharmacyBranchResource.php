<?php

namespace App\Filament\Pharmacy\Resources\PharmacyBranches;

use App\Filament\Pharmacy\Resources\PharmacyBranches\Pages\CreatePharmacyBranch;
use App\Filament\Pharmacy\Resources\PharmacyBranches\Pages\EditPharmacyBranch;
use App\Filament\Pharmacy\Resources\PharmacyBranches\Pages\ListPharmacyBranches;
use App\Filament\Pharmacy\Resources\PharmacyBranches\Schemas\PharmacyBranchForm;
use App\Filament\Pharmacy\Resources\PharmacyBranches\Tables\PharmacyBranchesTable;
use App\Models\PharmacyBranch;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class PharmacyBranchResource extends Resource
{
    protected static ?string $model = PharmacyBranch::class;

    protected static string | BackedEnum | null $navigationIcon =
        'heroicon-o-building-office-2';

    protected static string | UnitEnum | null $navigationGroup =
        'Pharmacy Settings';

    protected static ?string $navigationLabel = 'Branches';

    protected static ?string $modelLabel = 'Branch';

    protected static ?string $pluralModelLabel = 'Branches';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return PharmacyBranchForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PharmacyBranchesTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        $pharmacyId = auth()->user()?->pharmacy_id;

        return parent::getEloquentQuery()
            ->where('pharmacy_id', $pharmacyId ?? 0);
    }

    public static function canViewAny(): bool
    {
        return static::canManageBranches();
    }

    public static function canCreate(): bool
    {
        return static::canManageBranches();
    }

    public static function canEdit(Model $record): bool
    {
        return static::canManageBranches()
            && (int) $record->pharmacy_id
                === (int) auth()->user()?->pharmacy_id;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    private static function canManageBranches(): bool
    {
        $user = auth()->user();

        return Filament::getCurrentPanel()?->getId() === 'pharmacy'
            && filled($user?->pharmacy_id)
            && ($user?->can('branches.manage') ?? false);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPharmacyBranches::route('/'),
            'create' => CreatePharmacyBranch::route('/create'),
            'edit' => EditPharmacyBranch::route('/{record}/edit'),
        ];
    }
}