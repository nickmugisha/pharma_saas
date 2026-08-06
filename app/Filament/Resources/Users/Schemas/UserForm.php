<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\Pharmacy;
use App\Models\PharmacyBranch;
use App\Models\User;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Spatie\Permission\Models\Role;

class UserForm
{
    private const PHARMACY_ROLES = [
        'pharmacy_owner',
        'branch_manager',
        'pharmacist',
        'pharmacy_assistant',
        'stock_manager',
        'cashier',
        'accountant',
        'delivery_coordinator',
    ];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Account information')
                    ->description('Identity and authentication details.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('email')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),

                        TextInput::make('password')
                            ->label('Password')
                            ->password()
                            ->autocomplete('new-password')
                            ->afterStateHydrated(
                                fn (TextInput $component) => $component->state(null),
                            )
                            ->minLength(12)
                            ->required(
                                fn (string $operation): bool =>
                                    $operation === 'create',
                            )
                            ->dehydrated(
                                fn (?string $state): bool => filled($state),
                            )
                            ->helperText(
                                'Required when creating an account. Leave empty while editing to preserve the current password.',
                            ),

                        DateTimePicker::make('email_verified_at')
                            ->label('Email verified at')
                            ->seconds(false),
                    ]),

                Section::make('Access and security')
                    ->description(
                        'Assign the role, pharmacy context and account status.',
                    )
                    ->columns(2)
                    ->schema([
                        Select::make('roles')
                            ->label('Role')
                            ->disabled(
                                fn (?User $record): bool =>
                                    $record?->is(auth()->user()) ?? false,
                            )
                            ->relationship(
                                name: 'roles',
                                titleAttribute: 'name',
                            )
                            ->multiple()
                            ->maxItems(1)
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(
                                function (mixed $state, Set $set): void {
                                    if (! self::isPharmacyRole($state)) {
                                        $set('pharmacy_id', null);
                                        $set('pharmacy_branch_id', null);

                                        return;
                                    }

                                    if (
                                        self::selectedRoleName($state)
                                        !== 'pharmacy_owner'
                                    ) {
                                        $set('pharmacy_branch_id', null);
                                    }
                                },
                            )
                            ->helperText(
                                'Each account receives one primary role.',
                            ),

                        Toggle::make('is_active')
                            ->label('Active account')
                            ->default(true)
                            ->disabled(
                                fn (?User $record): bool =>
                                    $record?->is(auth()->user()) ?? false,
                            ),

                        Select::make('pharmacy_id')
                            ->label('Assigned pharmacy')
                            ->options(
                                fn (): array => Pharmacy::query()
                                    ->where('status', 'approved')
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                                    ->all(),
                            )
                            ->searchable()
                            ->preload()
                            ->live()
                            ->visible(
                                fn (Get $get): bool =>
                                    self::isPharmacyRole($get('roles')),
                            )
                            ->required(
                                fn (Get $get): bool =>
                                    self::isPharmacyRole($get('roles')),
                            )
                            ->afterStateUpdated(
                                fn (Set $set): mixed =>
                                    $set('pharmacy_branch_id', null),
                            )
                            ->helperText(
                                'Required for every pharmacy owner and staff account.',
                            ),

                        Select::make('pharmacy_branch_id')
                            ->label('Assigned branch')
                            ->options(
                                function (Get $get): array {
                                    $pharmacyId = (int) ($get('pharmacy_id') ?? 0);

                                    if ($pharmacyId <= 0) {
                                        return [];
                                    }

                                    return PharmacyBranch::query()
                                        ->where('pharmacy_id', $pharmacyId)
                                        ->where('status', 'active')
                                        ->orderByDesc('is_main')
                                        ->orderBy('name')
                                        ->pluck('name', 'id')
                                        ->all();
                                },
                            )
                            ->searchable()
                            ->preload()
                            ->visible(
                                fn (Get $get): bool =>
                                    self::isPharmacyRole($get('roles')),
                            )
                            ->required(
                                fn (Get $get): bool =>
                                    self::requiresBranch($get('roles')),
                            )
                            ->helperText(
                                'Optional only for a new pharmacy owner. The owner will be assigned automatically after creating the first branch.',
                            ),

                        Textarea::make('blocked_reason')
                            ->label('Blocking reason')
                            ->rows(3)
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    private static function selectedRoleName(mixed $state): ?string
    {
        $roleIds = collect(is_array($state) ? $state : [$state])
            ->filter()
            ->map(fn (mixed $id): int => (int) $id)
            ->values();

        if ($roleIds->isEmpty()) {
            return null;
        }

        return Role::query()
            ->whereIn('id', $roleIds)
            ->value('name');
    }

    private static function isPharmacyRole(mixed $state): bool
    {
        return in_array(
            self::selectedRoleName($state),
            self::PHARMACY_ROLES,
            true,
        );
    }

    private static function requiresBranch(mixed $state): bool
    {
        $role = self::selectedRoleName($state);

        return in_array($role, self::PHARMACY_ROLES, true)
            && $role !== 'pharmacy_owner';
    }
}
