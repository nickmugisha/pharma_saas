<?php

namespace App\Filament\Pharmacy\Widgets;

use App\Models\MarketplaceOrder;
use App\Models\Sale;
use Filament\Widgets\ChartWidget;

class PharmacySalesChart extends ChartWidget
{
    protected ?string $heading = 'Revenue flow';

    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = 1;

    protected ?string $maxHeight = '255px';

    protected ?string $pollingInterval = '60s';

    public static function canView(): bool
    {
        $user = auth()->user();

        return ($user?->can('sales.view') ?? false)
            || ($user?->can('marketplace.orders.view') ?? false);
    }

    public function getDescription(): ?string
    {
        return 'POS and paid marketplace revenue for the last 14 days.';
    }

    protected function getData(): array
    {
        $user = auth()->user();
        $pharmacyId = (int) ($user?->pharmacy_id ?? 0);
        $branchId = (int) ($user?->pharmacy_branch_id ?? 0);
        $start = today()->subDays(13);

        $posSales = Sale::query()
            ->where('pharmacy_id', $pharmacyId)
            ->when($branchId > 0, fn ($query) => $query->where('pharmacy_branch_id', $branchId))
            ->where('status', 'completed')
            ->whereDate('sold_at', '>=', $start)
            ->get(['sold_at', 'grand_total'])
            ->groupBy(fn (Sale $sale): string => $sale->sold_at?->format('Y-m-d') ?? '')
            ->map(fn ($group): float => round((float) $group->sum('grand_total'), 2));

        $onlineSales = MarketplaceOrder::query()
            ->where('pharmacy_id', $pharmacyId)
            ->when($branchId > 0, fn ($query) => $query->where('pharmacy_branch_id', $branchId))
            ->where('payment_status', MarketplaceOrder::PAYMENT_PAID)
            ->whereDate('paid_at', '>=', $start)
            ->get(['paid_at', 'grand_total'])
            ->groupBy(fn (MarketplaceOrder $order): string => $order->paid_at?->format('Y-m-d') ?? '')
            ->map(fn ($group): float => round((float) $group->sum('grand_total'), 2));

        $days = collect(range(0, 13))
            ->map(fn (int $offset) => $start->copy()->addDays($offset));

        return [
            'datasets' => [
                [
                    'label' => 'POS',
                    'data' => $days->map(fn ($day): float => (float) ($posSales[$day->format('Y-m-d')] ?? 0))->all(),
                    'borderColor' => '#059669',
                    'backgroundColor' => 'rgba(5, 150, 105, 0.12)',
                    'fill' => true,
                    'tension' => 0.42,
                    'pointRadius' => 1.5,
                    'borderWidth' => 2,
                ],
                [
                    'label' => 'Marketplace',
                    'data' => $days->map(fn ($day): float => (float) ($onlineSales[$day->format('Y-m-d')] ?? 0))->all(),
                    'borderColor' => '#0ea5e9',
                    'backgroundColor' => 'rgba(14, 165, 233, 0.08)',
                    'fill' => true,
                    'tension' => 0.42,
                    'pointRadius' => 1.5,
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
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
                    'labels' => ['usePointStyle' => true, 'boxWidth' => 8],
                ],
            ],
            'scales' => [
                'x' => ['grid' => ['display' => false]],
                'y' => [
                    'beginAtZero' => true,
                    'grid' => ['color' => 'rgba(148, 163, 184, 0.14)'],
                ],
            ],
        ];
    }
}
