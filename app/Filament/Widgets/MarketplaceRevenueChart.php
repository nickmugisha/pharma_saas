<?php

namespace App\Filament\Widgets;

use App\Models\MarketplaceOrder;
use Filament\Widgets\ChartWidget;

class MarketplaceRevenueChart extends ChartWidget
{
    protected ?string $heading = 'Marketplace revenue pulse';

    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = 1;

    protected ?string $maxHeight = '255px';

    protected ?string $pollingInterval = '60s';

    public function getDescription(): ?string
    {
        return 'Paid order value over the last 14 days.';
    }

    protected function getData(): array
    {
        $start = today()->subDays(13);

        $orders = MarketplaceOrder::query()
            ->where('payment_status', MarketplaceOrder::PAYMENT_PAID)
            ->whereDate('paid_at', '>=', $start)
            ->get(['paid_at', 'grand_total']);

        $daily = $orders
            ->groupBy(fn (MarketplaceOrder $order): string => $order->paid_at?->format('Y-m-d') ?? '')
            ->map(fn ($group): float => round((float) $group->sum('grand_total'), 2));

        $days = collect(range(0, 13))
            ->map(fn (int $offset) => $start->copy()->addDays($offset));

        return [
            'datasets' => [
                [
                    'label' => 'Paid order value',
                    'data' => $days
                        ->map(fn ($day): float => (float) ($daily[$day->format('Y-m-d')] ?? 0))
                        ->all(),
                    'borderColor' => '#4f46e5',
                    'backgroundColor' => 'rgba(79, 70, 229, 0.14)',
                    'fill' => true,
                    'tension' => 0.42,
                    'pointRadius' => 2,
                    'pointHoverRadius' => 5,
                    'borderWidth' => 2,
                ],
            ],
            'labels' => $days->map(fn ($day): string => $day->format('d M'))->all(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
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
                    'grid' => ['color' => 'rgba(148, 163, 184, 0.14)'],
                ],
            ],
        ];
    }
}
