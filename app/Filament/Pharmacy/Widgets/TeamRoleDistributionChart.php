<?php

namespace App\Filament\Pharmacy\Widgets;

use App\Models\User;
use App\Services\StaffRecruitmentService;
use Filament\Widgets\ChartWidget;

class TeamRoleDistributionChart extends ChartWidget
{
    protected ?string $heading = 'Team role distribution';

    protected static ?int $sort = 6;

    protected int | string | array $columnSpan = 1;

    protected ?string $maxHeight = '285px';

    protected ?string $pollingInterval = '45s';

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && app(StaffRecruitmentService::class)
                ->canManageStaff($user);
    }

    public function getDescription(): ?string
    {
        $user = auth()->user();

        return $user?->hasRole('pharmacy_owner')
            ? 'Live role coverage across your pharmacy.'
            : 'Live role coverage inside your assigned branch.';
    }

    protected function getData(): array
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        $service = app(StaffRecruitmentService::class);
        $roles = $service->assignableRoleNames($user);
        $counts = collect($roles)->mapWithKeys(function (string $role) use (
            $service,
            $user,
        ): array {
            return [
                $role => $service->manageableQuery($user)
                    ->whereHas(
                        'roles',
                        fn ($query) => $query->where('name', $role),
                    )
                    ->count(),
            ];
        });

        return [
            'datasets' => [[
                'label' => 'Employees',
                'data' => $counts->values()->all(),
                'backgroundColor' => [
                    'rgba(5, 150, 105, 0.82)',
                    'rgba(14, 165, 233, 0.82)',
                    'rgba(124, 58, 237, 0.82)',
                    'rgba(245, 158, 11, 0.82)',
                    'rgba(236, 72, 153, 0.82)',
                    'rgba(15, 118, 110, 0.82)',
                    'rgba(100, 116, 139, 0.82)',
                ],
                'borderWidth' => 0,
                'borderRadius' => 8,
            ]],
            'labels' => $counts->keys()
                ->map(fn (string $role): string =>
                    StaffRecruitmentService::ROLE_LABELS[$role]
                        ?? str($role)->headline()->toString())
                ->all(),
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
            'indexAxis' => 'y',
            'plugins' => [
                'legend' => ['display' => false],
                'tooltip' => ['enabled' => true],
            ],
            'scales' => [
                'x' => [
                    'beginAtZero' => true,
                    'ticks' => ['precision' => 0],
                    'grid' => ['color' => 'rgba(148, 163, 184, 0.14)'],
                ],
                'y' => ['grid' => ['display' => false]],
            ],
        ];
    }
}
