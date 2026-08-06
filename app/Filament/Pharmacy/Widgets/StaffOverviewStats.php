<?php

namespace App\Filament\Pharmacy\Widgets;

use App\Filament\Pharmacy\Resources\StaffMembers\StaffMemberResource;
use App\Models\User;
use App\Services\StaffRecruitmentService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StaffOverviewStats extends StatsOverviewWidget
{
    protected static ?int $sort = 5;

    protected int | string | array $columnSpan = 'full';

    protected static bool $isLazy = false;

    protected ?string $pollingInterval = '30s';

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && app(StaffRecruitmentService::class)
                ->canManageStaff($user);
    }

    protected function getStats(): array
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        $service = app(StaffRecruitmentService::class);
        $base = $service->manageableQuery($user);
        $teamSize = (clone $base)->count();
        $active = (clone $base)->where('is_active', true)->count();
        $inactive = max($teamSize - $active, 0);
        $newThisMonth = (clone $base)
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();
        $branchCoverage = (clone $base)
            ->whereNotNull('pharmacy_branch_id')
            ->distinct()
            ->count('pharmacy_branch_id');

        $scopeDescription = $service->isOwner($user)
            ? 'Across your pharmacy branches'
            : 'Inside your assigned branch only';

        return [
            Stat::make('Team members', number_format($teamSize))
                ->description($scopeDescription)
                ->descriptionIcon('heroicon-m-user-group')
                ->color('primary')
                ->chart([$teamSize, $active, $teamSize])
                ->url(StaffMemberResource::getUrl()),

            Stat::make('Active accounts', number_format($active))
                ->description($inactive.' inactive account(s)')
                ->descriptionIcon('heroicon-m-shield-check')
                ->color($inactive > 0 ? 'warning' : 'success')
                ->chart([$active, max($active - 1, 0), $active]),

            Stat::make('New this month', number_format($newThisMonth))
                ->description('Recruitment activity this month')
                ->descriptionIcon('heroicon-m-user-plus')
                ->color('success')
                ->chart([0, $newThisMonth, $newThisMonth]),

            Stat::make('Branch coverage', number_format($branchCoverage))
                ->description(
                    $service->isOwner($user)
                        ? 'Branches with assigned staff'
                        : 'Your current branch',
                )
                ->descriptionIcon('heroicon-m-building-storefront')
                ->color('info'),
        ];
    }
}
