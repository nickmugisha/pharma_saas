<?php

namespace App\Filament\Pharmacy\Resources\StaffMembers\Schemas;

use App\Models\PharmacyBranch;
use App\Models\User;
use App\Services\StaffRecruitmentService;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class StaffMemberInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Staff account')
                    ->description(
                        'Identity, role, branch assignment and account status.'
                    )
                    ->columnSpanFull()
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                        'xl' => 3,
                    ])
                    ->schema([
                        TextEntry::make('name')
                            ->label('Full name')
                            ->weight('bold'),

                        TextEntry::make('email')
                            ->copyable(),

                        TextEntry::make('phone')
                            ->label('Phone number')
                            ->copyable()
                            ->placeholder('Not provided'),

                        TextEntry::make('staff_role')
                            ->label('Role')
                            ->getStateUsing(
                                fn (User $record): string => self::roleLabel($record),
                            )
                            ->badge()
                            ->color('primary'),

                        TextEntry::make('branch_name')
                            ->label('Assigned branch')
                            ->getStateUsing(
                                fn (User $record): string => PharmacyBranch::query()
                                    ->whereKey($record->pharmacy_branch_id)
                                    ->value('name') ?? 'Not assigned',
                            ),

                        TextEntry::make('job_title')
                            ->label('Job title')
                            ->placeholder('Not provided'),

                        IconEntry::make('is_active')
                            ->label('Active account')
                            ->boolean(),

                        TextEntry::make('hired_at')
                            ->label('Hire date')
                            ->date('d M Y')
                            ->placeholder('Not recorded'),

                        TextEntry::make('last_login_at')
                            ->label('Last login')
                            ->dateTime('d M Y H:i')
                            ->placeholder('Never signed in'),

                        TextEntry::make('created_at')
                            ->label('Account created')
                            ->dateTime('d M Y H:i'),
                    ]),

                Section::make('Security boundaries')
                    ->description(
                        'This employee can access only the pharmacy and branch assigned above, subject to their role permissions.'
                    )
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('security_summary')
                            ->hiddenLabel()
                            ->getStateUsing(
                                fn (User $record): string => sprintf(
                                    '%s is assigned to one pharmacy, one branch and the %s role. Platform roles and pharmacy-owner privileges cannot be granted here.',
                                    $record->name,
                                    self::roleLabel($record),
                                ),
                            ),
                    ]),
            ]);
    }

    private static function roleLabel(User $record): string
    {
        $role = app(StaffRecruitmentService::class)->roleName($record);

        return StaffRecruitmentService::ROLE_LABELS[$role]
            ?? str($role ?? 'staff')->headline()->toString();
    }
}
