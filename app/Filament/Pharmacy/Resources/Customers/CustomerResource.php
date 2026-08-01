<?php

namespace App\Filament\Pharmacy\Resources\Customers;

use App\Filament\Pharmacy\Resources\Customers\Pages\CreateCustomer;
use App\Filament\Pharmacy\Resources\Customers\Pages\EditCustomer;
use App\Filament\Pharmacy\Resources\Customers\Pages\ListCustomers;
use App\Filament\Pharmacy\Resources\Customers\Pages\ViewCustomer;
use App\Filament\Pharmacy\Resources\Customers\Schemas\CustomerForm;
use App\Filament\Pharmacy\Resources\Customers\Schemas\CustomerInfolist;
use App\Filament\Pharmacy\Resources\Customers\Tables\CustomersTable;
use App\Models\Customer;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static string | BackedEnum | null
        $navigationIcon = 'heroicon-o-user-group';

    protected static string | UnitEnum | null
        $navigationGroup = 'Customers';

    protected static ?string $navigationLabel =
        'Customers';

    protected static ?string $modelLabel =
        'Customer';

    protected static ?string $pluralModelLabel =
        'Customers';

    protected static ?string $recordTitleAttribute =
        'name';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return CustomerForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CustomerInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CustomersTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where(
                'pharmacy_id',
                auth()->user()?->pharmacy_id ?? 0,
            )
            ->with([
                'registeredBranch',
                'patientProfile',
            ])
            ->withCount([
                'sales',
                'activities',
            ]);
    }

    public static function canViewAny(): bool
    {
        return static::canViewCustomers();
    }

    public static function canView(Model $record): bool
    {
        return static::canViewCustomers()
            && static::belongsToCurrentPharmacy($record);
    }

    public static function canCreate(): bool
    {
        return static::canManageCustomers();
    }

    public static function canEdit(Model $record): bool
    {
        return static::canManageCustomers()
            && static::belongsToCurrentPharmacy($record);
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    private static function canViewCustomers(): bool
    {
        $user = auth()->user();

        return Filament::getCurrentPanel()?->getId()
                === 'pharmacy'
            && filled($user?->pharmacy_id)
            && ($user?->can('customers.view') ?? false);
    }

    private static function canManageCustomers(): bool
    {
        $user = auth()->user();

        return Filament::getCurrentPanel()?->getId()
                === 'pharmacy'
            && filled($user?->pharmacy_id)
            && ($user?->can('customers.manage') ?? false);
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
            'index' => ListCustomers::route('/'),
            'create' => CreateCustomer::route('/create'),
            'view' => ViewCustomer::route('/{record}'),
            'edit' => EditCustomer::route('/{record}/edit'),
        ];
    }
}