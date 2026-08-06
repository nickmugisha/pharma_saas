<?php

namespace App\Filament\Pharmacy\Widgets;

use App\Models\PharmacyBranch;
use App\Models\User;
use App\Services\StaffRecruitmentService;
use Filament\Widgets\ChartWidget;

class BranchTeamDistributionChart extends ChartWidget
{
    protected ?string $heading = 'Staff by branch';

    protected static ?int $sort = 7;

    protected int | string | array $columnSpan = 1;

    protected ?string $maxHeight = '285px';

    protected ?string $pollingInterval = '45s';

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && $user->hasRole('pharmacy_owner')
            && app(StaffRecruitmentService::class)
                ->canManageStaff($user);
    }

    public function getDescription(): ?string
    {
        return 'Active and inactive employee allocation across branches.';
    }

    protected function getData(): array
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        $service = app(StaffRecruitmentService::class);
        $branches = PharmacyBranch::query()
            ->where('pharmacy_id', $user->pharmacy_id)
            ->orderByDesc('is_main')
            ->orderBy('name')
            ->get(['id', 'name']);

        return [
            'datasets' => [
                [
                    'label' => 'Active',
                    'data' => $branches->map(
                        fn (PharmacyBranch $branch): int =>
                            $service->manageableQuery($user)
                                ->where('pharmacy_branch_id', $branch->id)
                                ->where('is_active', true)
                                ->count(),
                    )->all(),
                    'backgroundColor' => 'rgba(5, 150, 105, 0.82)',
                    'borderRadius' => 8,
                ],
                [
                    'label' => 'Inactive',
                    'data' => $branches->map(
                        fn (PharmacyBranch $branch): int =>
                            $service->manageableQuery($user)
                                ->where('pharmacy_branch_id', $branch->id)
                                ->where('is_active', false)
                                ->count(),
                    )->all(),
                    'backgroundColor' => 'rgba(245, 158, 11, 0.72)',
                    'borderRadius' => 8,
                ],
            ],
            'labels' => $branches->pluck('name')->all(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
                    'labels' => ['usePointStyle' => true, 'boxWidth' => 8],
                ],
            ],
            'scales' => [
                'x' => [
                    'stacked' => true,
                    'grid' => ['display' => false],
                ],
                'y' => [
                    'stacked' => true,
                    'beginAtZero' => true,
                    'ticks' => ['precision' => 0],
                    'grid' => ['color' => 'rgba(148, 163, 184, 0.14)'],
                ],
            ],
        ];
    }
}
