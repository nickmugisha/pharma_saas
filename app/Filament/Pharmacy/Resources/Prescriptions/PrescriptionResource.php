<?php

namespace App\Filament\Pharmacy\Resources\Prescriptions;

use App\Filament\Pharmacy\Resources\Prescriptions\Pages\CreatePrescription;
use App\Filament\Pharmacy\Resources\Prescriptions\Pages\EditPrescription;
use App\Filament\Pharmacy\Resources\Prescriptions\Pages\ListPrescriptions;
use App\Filament\Pharmacy\Resources\Prescriptions\Pages\ViewPrescription;
use App\Filament\Pharmacy\Resources\Prescriptions\Schemas\PrescriptionForm;
use App\Filament\Pharmacy\Resources\Prescriptions\Schemas\PrescriptionInfolist;
use App\Filament\Pharmacy\Resources\Prescriptions\Tables\PrescriptionsTable;
use App\Models\Prescription;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class PrescriptionResource extends Resource
{
    protected static ?string $model = Prescription::class;

    protected static string | BackedEnum | null
        $navigationIcon = 'heroicon-o-document-text';

    protected static string | UnitEnum | null
        $navigationGroup = 'Clinical Care';

    protected static ?string $navigationLabel =
        'Prescriptions';

    protected static ?string $modelLabel =
        'Prescription';

    protected static ?string $pluralModelLabel =
        'Prescriptions';

    protected static ?string $recordTitleAttribute =
        'prescription_number';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return PrescriptionForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PrescriptionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PrescriptionsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where(
                'pharmacy_id',
                auth()->user()?->pharmacy_id ?? 0,
            )
            ->with([
                'customer',
                'branch',
                'createdByUser',
                'reviewedByUser',
            ])
            ->withCount([
                'items',
                'attachments',
                'activities',
            ]);
    }

    public static function canViewAny(): bool
    {
        return static::canViewPrescriptions();
    }

    public static function canView(Model $record): bool
    {
        return static::canViewPrescriptions()
            && static::belongsToCurrentPharmacy($record);
    }

    public static function canCreate(): bool
    {
        return static::canManagePrescriptions();
    }

    public static function canEdit(Model $record): bool
    {
        return static::canManagePrescriptions()
            && static::belongsToCurrentPharmacy($record)
            && $record->status
                === Prescription::STATUS_DRAFT;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    private static function canViewPrescriptions(): bool
    {
        $user = auth()->user();

        return Filament::getCurrentPanel()?->getId()
                === 'pharmacy'
            && filled($user?->pharmacy_id)
            && ($user?->can('prescriptions.view') ?? false);
    }

    private static function canManagePrescriptions(): bool
    {
        $user = auth()->user();

        return Filament::getCurrentPanel()?->getId()
                === 'pharmacy'
            && filled($user?->pharmacy_id)
            && ($user?->can('prescriptions.manage') ?? false);
    }

    private static function belongsToCurrentPharmacy(
        Model $record,
    ): bool {
        return (int) $record->pharmacy_id
            === (int) auth()->user()?->pharmacy_id;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPrescriptions::route('/'),
            'create' => CreatePrescription::route('/create'),
            'view' => ViewPrescription::route('/{record}'),
            'edit' => EditPrescription::route('/{record}/edit'),
        ];
    }
}