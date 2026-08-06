<?php

namespace App\Filament\Pharmacy\Resources\StaffMembers\Schemas;

use App\Models\User;
use App\Services\StaffRecruitmentService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class StaffMemberForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Employee identity')
                    ->description(
                        'Create a professional account linked to the correct pharmacy and branch.'
                    )
                    ->columnSpanFull()
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                    ])
                    ->schema([
                        TextInput::make('name')
                            ->label('Full name')
                            ->required()
                            ->maxLength(191)
                            ->autofocus(),

                        TextInput::make('email')
                            ->email()
                            ->required()
                            ->maxLength(191)
                            ->unique(
                                table: 'users',
                                column: 'email',
                                ignoreRecord: true,
                            ),

                        TextInput::make('phone')
                            ->label('Phone number')
                            ->tel()
                            ->maxLength(50)
                            ->placeholder('+257 ...'),

                        TextInput::make('job_title')
                            ->label('Job title')
                            ->maxLength(120)
                            ->placeholder('Example: Senior cashier'),

                        DatePicker::make('hired_at')
                            ->label('Hire date')
                            ->native(false)
                            ->displayFormat('d M Y')
                            ->maxDate(today())
                            ->default(today()),
                    ]),

                Section::make('Role and branch access')
                    ->description(
                        'The available choices are automatically limited by your own authority.'
                    )
                    ->columnSpanFull()
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                    ])
                    ->schema([
                        Select::make('staff_role')
                            ->label('Operational role')
                            ->options(fn (): array => self::service()
                                ->assignableRoleOptions(self::actor()))
                            ->required()
                            ->searchable()
                            ->preload(),

                        Select::make('pharmacy_branch_id')
                            ->label('Assigned branch')
                            ->options(fn (): array => self::service()
                                ->branchOptions(self::actor()))
                            ->default(fn (): ?int => self::actor()
                                ->pharmacy_branch_id)
                            ->required()
                            ->searchable()
                            ->preload(),

                        Toggle::make('is_active')
                            ->label('Active account')
                            ->helperText(
                                'Inactive employees cannot sign in to the pharmacy panel.'
                            )
                            ->default(true)
                            ->required(),

                        Textarea::make('reason')
                            ->label('Recruitment or change note')
                            ->rows(3)
                            ->maxLength(1000)
                            ->placeholder(
                                'Optional internal reason for the audit trail.'
                            ),
                    ]),

                Section::make('Secure access')
                    ->description(
                        'Set a temporary password of at least 12 characters. The employee can later change it from their profile.'
                    )
                    ->columnSpanFull()
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                    ])
                    ->schema([
                        TextInput::make('password')
                            ->password()
                            ->revealable()
                            ->minLength(12)
                            ->required(
                                fn (?User $record): bool => $record === null,
                            )
                            ->dehydrated(
                                fn (?string $state): bool => filled($state),
                            )
                            ->helperText(
                                'Leave blank while editing to keep the current password.'
                            ),

                        TextInput::make('password_confirmation')
                            ->label('Confirm password')
                            ->password()
                            ->revealable()
                            ->same('password')
                            ->required(
                                fn (?User $record): bool => $record === null,
                            )
                            ->dehydrated(
                                fn (?string $state): bool => filled($state),
                            ),
                    ]),
            ]);
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
}
