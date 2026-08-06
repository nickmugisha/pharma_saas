<?php

namespace App\Filament\Pharmacy\Widgets;

use App\Models\InventoryAlert;
use App\Models\MarketplaceOrder;
use App\Models\Prescription;
use Filament\Widgets\ChartWidget;

class PharmacyWorkloadChart extends ChartWidget
{
    protected ?string $heading = 'Operational workload';

    protected static ?int $sort = 3;

    protected int | string | array $columnSpan = 1;

    protected ?string $maxHeight = '255px';

    protected ?string $pollingInterval = '60s';

    public static function canView(): bool
    {
        $user = auth()->user();

        return ($user?->can('stock.view') ?? false)
            || ($user?->can('prescriptions.view') ?? false)
            || ($user?->can('marketplace.orders.view') ?? false);
    }

    public function getDescription(): ?string
    {
        return 'Items that need attention in the current pharmacy scope.';
    }

    protected function getData(): array
    {
        $user = auth()->user();
        $pharmacyId = (int) ($user?->pharmacy_id ?? 0);
        $branchId = (int) ($user?->pharmacy_branch_id ?? 0);

        $orders = MarketplaceOrder::query()
            ->where('pharmacy_id', $pharmacyId)
            ->when($branchId > 0, fn ($query) => $query->where('pharmacy_branch_id', $branchId))
            ->whereIn('status', [
                MarketplaceOrder::STATUS_AWAITING_REVIEW,
                MarketplaceOrder::STATUS_AWAITING_PAYMENT,
                MarketplaceOrder::STATUS_CONFIRMED,
            ])
            ->count();

        $prescriptions = Prescription::query()
            ->where('pharmacy_id', $pharmacyId)
            ->when($branchId > 0, fn ($query) => $query->where('pharmacy_branch_id', $branchId))
            ->whereIn('status', [
                Prescription::STATUS_SUBMITTED,
                Prescription::STATUS_UNDER_REVIEW,
                Prescription::STATUS_APPROVED,
                Prescription::STATUS_PARTIALLY_DISPENSED,
            ])
            ->count();

        $alerts = InventoryAlert::query()
            ->where('pharmacy_id', $pharmacyId)
            ->when($branchId > 0, fn ($query) => $query->where('pharmacy_branch_id', $branchId))
            ->whereIn('status', ['open', 'acknowledged'])
            ->count();

        return [
            'datasets' => [
                [
                    'label' => 'Open workload',
                    'data' => [$orders, $prescriptions, $alerts],
                    'backgroundColor' => [
                        'rgba(14, 165, 233, 0.78)',
                        'rgba(245, 158, 11, 0.78)',
                        'rgba(239, 68, 68, 0.78)',
                    ],
                    'borderWidth' => 0,
                    'hoverOffset' => 8,
                ],
            ],
            'labels' => ['Online orders', 'Prescriptions', 'Inventory alerts'],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return [
            'maintainAspectRatio' => false,
            'cutout' => '72%',
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
                    'labels' => ['usePointStyle' => true, 'boxWidth' => 8],
                ],
            ],
        ];
    }
}
