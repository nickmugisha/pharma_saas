<?php

namespace App\Filament\Pharmacy\Resources\StaffMembers\Tables;

use App\Filament\Pharmacy\Resources\StaffMembers\StaffMemberResource;
use App\Models\PharmacyBranch;
use App\Models\User;
use App\Services\StaffRecruitmentService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StaffMembersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->label('Employee')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('email')
                    ->searchable()
                    ->copyable(),

                TextColumn::make('staff_role')
                    ->label('Role')
                    ->getStateUsing(
                        fn (User $record): string => self::roleLabel($record),
                    )
                    ->badge()
                    ->color('primary'),

                TextColumn::make('branch_name')
                    ->label('Branch')
                    ->getStateUsing(
                        fn (User $record): string => PharmacyBranch::query()
                            ->whereKey($record->pharmacy_branch_id)
                            ->value('name') ?? 'Not assigned',
                    )
                    ->sortable(
                        query: fn (Builder $query, string $direction): Builder =>
                            $query->orderBy('pharmacy_branch_id', $direction),
                    ),

                TextColumn::make('job_title')
                    ->label('Job title')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('phone')
                    ->label('Phone')
                    ->placeholder('—')
                    ->toggleable(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('last_login_at')
                    ->label('Last login')
                    ->dateTime('d M Y H:i')
                    ->placeholder('Never')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('hired_at')
                    ->label('Hired')
                    ->date('d M Y')
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('staff_role')
                    ->label('Role')
                    ->options(fn (): array => self::service()
                        ->assignableRoleOptions(self::actor()))
                    ->query(function (Builder $query, array $data): Builder {
                        $role = $data['value'] ?? null;

                        return $query->when(
                            filled($role),
                            fn (Builder $query): Builder => $query->whereHas(
                                'roles',
                                fn (Builder $roleQuery): Builder => $roleQuery
                                    ->where('name', $role),
                            ),
                        );
                    }),

                SelectFilter::make('pharmacy_branch_id')
                    ->label('Branch')
                    ->options(fn (): array => self::service()
                        ->branchOptions(self::actor())),

                SelectFilter::make('is_active')
                    ->label('Account status')
                    ->options([
                        '1' => 'Active',
                        '0' => 'Inactive',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),

                EditAction::make()
                    ->visible(
                        fn (User $record): bool =>
                            StaffMemberResource::canEdit($record),
                    ),

                Action::make('toggle_active')
                    ->label(
                        fn (User $record): string => $record->is_active
                            ? 'Deactivate'
                            : 'Reactivate',
                    )
                    ->icon(
                        fn (User $record): string => $record->is_active
                            ? 'heroicon-o-lock-closed'
                            : 'heroicon-o-lock-open',
                    )
                    ->color(
                        fn (User $record): string => $record->is_active
                            ? 'danger'
                            : 'success',
                    )
                    ->requiresConfirmation()
                    ->modalHeading(
                        fn (User $record): string => $record->is_active
                            ? 'Deactivate employee account?'
                            : 'Reactivate employee account?',
                    )
                    ->action(function (User $record): void {
                        $newActiveState = ! (bool) $record->is_active;

                        self::service()->setActive(
                            actor: self::actor(),
                            target: $record,
                            active: $newActiveState,
                            reason: 'Status changed from Staff & Access.',
                        );

                        Notification::make()
                            ->title(
                                $newActiveState
                                    ? 'Employee account reactivated'
                                    : 'Employee account deactivated',
                            )
                            ->success()
                            ->send();
                    })
                    ->visible(
                        fn (User $record): bool =>
                            StaffMemberResource::canEdit($record),
                    ),
            ])
            ->emptyStateHeading('No employees in this scope')
            ->emptyStateDescription(
                'Recruit a team member and assign the correct branch and role.'
            )
            ->emptyStateIcon('heroicon-o-user-plus');
    }

    private static function actor(): User
    {
        $user = auth()->user();

        abort_unless($user instanceof User, 403);

        return $user;
    }

    private static function service(): StaffRecruitmentService
    {
        return app(StaffRecruitmentService::class);
    }

    private static function roleLabel(User $record): string
    {
        $role = self::service()->roleName($record);

        return StaffRecruitmentService::ROLE_LABELS[$role]
            ?? str($role ?? 'staff')->headline()->toString();
    }
}
