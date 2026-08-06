<?php

namespace App\Filament\Pharmacy\Widgets;

use App\Models\InventoryAlert;
use App\Models\MarketplaceOrder;
use App\Models\MedicineBatch;
use App\Models\Prescription;
use App\Models\Sale;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PharmacyOverviewStats extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected int | string | array $columnSpan = 'full';

    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        $user = auth()->user();
        $pharmacyId = (int) ($user?->pharmacy_id ?? 0);
        $branchId = (int) ($user?->pharmacy_branch_id ?? 0);
        $stats = [];

        if ($user?->can('sales.view')) {
            $todaySales = Sale::query()
                ->where('pharmacy_id', $pharmacyId)
                ->when($branchId > 0, fn ($query) => $query->where('pharmacy_branch_id', $branchId))
                ->where('status', 'completed')
                ->whereDate('sold_at', today())
                ->sum('grand_total');

            $stats[] = Stat::make('Today’s POS sales', number_format((float) $todaySales, 0).' BIF')
                ->description('Completed sales in your assigned scope')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success');
        }

        if ($user?->can('marketplace.orders.view')) {
            $pendingOrders = MarketplaceOrder::query()
                ->where('pharmacy_id', $pharmacyId)
                ->when($branchId > 0, fn ($query) => $query->where('pharmacy_branch_id', $branchId))
                ->whereIn('status', [
                    MarketplaceOrder::STATUS_AWAITING_REVIEW,
                    MarketplaceOrder::STATUS_AWAITING_PAYMENT,
                    MarketplaceOrder::STATUS_CONFIRMED,
                ])
                ->count();

            $stats[] = Stat::make('Online workload', number_format($pendingOrders))
                ->description('Orders waiting for pharmacy action')
                ->descriptionIcon('heroicon-m-shopping-bag')
                ->color($pendingOrders > 0 ? 'warning' : 'success');
        }

        if ($user?->can('stock.view')) {
            $openAlerts = InventoryAlert::query()
                ->where('pharmacy_id', $pharmacyId)
                ->when($branchId > 0, fn ($query) => $query->where('pharmacy_branch_id', $branchId))
                ->whereIn('status', ['open', 'acknowledged'])
                ->count();

            $sellableUnits = MedicineBatch::query()
                ->where('pharmacy_id', $pharmacyId)
                ->when($branchId > 0, fn ($query) => $query->where('pharmacy_branch_id', $branchId))
                ->where('status', 'active')
                ->whereDate('expiry_date', '>', today())
                ->sum('quantity_available');

            $stats[] = Stat::make('Sellable stock', number_format((float) $sellableUnits, 3).' units')
                ->description($openAlerts.' unresolved inventory alert(s)')
                ->descriptionIcon('heroicon-m-cube')
                ->color($openAlerts > 0 ? 'danger' : 'info');
        }

        if ($user?->can('prescriptions.view')) {
            $reviewQueue = Prescription::query()
                ->where('pharmacy_id', $pharmacyId)
                ->when($branchId > 0, fn ($query) => $query->where('pharmacy_branch_id', $branchId))
                ->whereIn('status', [
                    Prescription::STATUS_SUBMITTED,
                    Prescription::STATUS_UNDER_REVIEW,
                    Prescription::STATUS_APPROVED,
                    Prescription::STATUS_PARTIALLY_DISPENSED,
                ])
                ->count();

            $stats[] = Stat::make('Clinical queue', number_format($reviewQueue))
                ->description('Prescriptions requiring review or fulfilment')
                ->descriptionIcon('heroicon-m-clipboard-document-check')
                ->color($reviewQueue > 0 ? 'warning' : 'success');
        }

        return $stats;
    }
}
