<?php

namespace App\Filament\Pharmacy\Resources\StaffMembers;

use App\Filament\Pharmacy\Resources\StaffMembers\Pages\CreateStaffMember;
use App\Filament\Pharmacy\Resources\StaffMembers\Pages\EditStaffMember;
use App\Filament\Pharmacy\Resources\StaffMembers\Pages\ListStaffMembers;
use App\Filament\Pharmacy\Resources\StaffMembers\Pages\ViewStaffMember;
use App\Filament\Pharmacy\Resources\StaffMembers\Schemas\StaffMemberForm;
use App\Filament\Pharmacy\Resources\StaffMembers\Schemas\StaffMemberInfolist;
use App\Filament\Pharmacy\Resources\StaffMembers\Tables\StaffMembersTable;
use App\Models\User;
use App\Services\StaffRecruitmentService;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class StaffMemberResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string | BackedEnum | null
        $navigationIcon = 'heroicon-o-identification';

    protected static string | UnitEnum | null
        $navigationGroup = 'Pharmacy Settings';

    protected static ?string $navigationLabel = 'Staff & Access';

    protected static ?string $modelLabel = 'Staff member';

    protected static ?string $pluralModelLabel = 'Staff & Access';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return StaffMemberForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return StaffMemberInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StaffMembersTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return parent::getEloquentQuery()->whereRaw('1 = 0');
        }

        return app(StaffRecruitmentService::class)
            ->manageableQuery($user)
            ->with('roles');
    }

    public static function canViewAny(): bool
    {
        return static::canManageStaff();
    }

    public static function canView(Model $record): bool
    {
        return $record instanceof User
            && static::canManageRecord($record);
    }

    public static function canCreate(): bool
    {
        return static::canManageStaff();
    }

    public static function canEdit(Model $record): bool
    {
        return $record instanceof User
            && static::canManageRecord($record);
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $user = auth()->user();

        if (! $user instanceof User || ! static::canManageStaff()) {
            return null;
        }

        return (string) app(StaffRecruitmentService::class)
            ->manageableQuery($user)
            ->where('is_active', true)
            ->count();
    }

    private static function canManageStaff(): bool
    {
        $user = auth()->user();

        return Filament::getCurrentPanel()?->getId() === 'pharmacy'
            && $user instanceof User
            && app(StaffRecruitmentService::class)
                ->canManageStaff($user);
    }

    private static function canManageRecord(User $record): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && app(StaffRecruitmentService::class)
                ->canManageTarget($user, $record);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStaffMembers::route('/'),
            'create' => CreateStaffMember::route('/create'),
            'view' => ViewStaffMember::route('/{record}'),
            'edit' => EditStaffMember::route('/{record}/edit'),
        ];
    }
}
