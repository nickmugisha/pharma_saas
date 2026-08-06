<?php

namespace App\Filament\Widgets;

use App\Models\ClientProfile;
use Filament\Widgets\ChartWidget;

class ClientGrowthChart extends ChartWidget
{
    protected ?string $heading = 'Client growth signal';

    protected static ?int $sort = 3;

    protected int | string | array $columnSpan = 1;

    protected ?string $maxHeight = '255px';

    protected ?string $pollingInterval = '60s';

    public function getDescription(): ?string
    {
        return 'New marketplace accounts over the last 14 days.';
    }

    protected function getData(): array
    {
        $start = today()->subDays(13);

        $profiles = ClientProfile::query()
            ->whereDate('created_at', '>=', $start)
            ->get(['created_at']);

        $daily = $profiles
            ->groupBy(fn (ClientProfile $profile): string => $profile->created_at->format('Y-m-d'))
            ->map->count();

        $days = collect(range(0, 13))
            ->map(fn (int $offset) => $start->copy()->addDays($offset));

        return [
            'datasets' => [
                [
                    'label' => 'New clients',
                    'data' => $days
                        ->map(fn ($day): int => (int) ($daily[$day->format('Y-m-d')] ?? 0))
                        ->all(),
                    'backgroundColor' => 'rgba(14, 165, 233, 0.72)',
                    'borderColor' => '#0284c7',
                    'borderWidth' => 1,
                    'borderRadius' => 8,
                    'barThickness' => 12,
                ],
            ],
            'labels' => $days->map(fn ($day): string => $day->format('d M'))->all(),
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
                'legend' => ['display' => false],
            ],
            'scales' => [
                'x' => [
                    'grid' => ['display' => false],
                ],
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => ['precision' => 0],
                    'grid' => ['color' => 'rgba(148, 163, 184, 0.14)'],
                ],
            ],
        ];
    }
}
