<?php

namespace App\Filament\Pharmacy\Widgets;

use App\Models\MarketplaceOrderEvent;
use App\Models\PrescriptionActivity;
use App\Models\Sale;
use App\Models\StockMovement;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

class PharmacyActivityFeed extends Widget
{
    protected string $view = 'filament.pharmacy.widgets.pharmacy-activity-feed';

    protected static ?int $sort = 4;

    protected int | string | array $columnSpan = 'full';

    protected static bool $isLazy = false;

    public function getContextSummary(): array
    {
        $user = auth()->user();

        return [
            'name' => $user?->name ?? 'Pharmacy user',
            'role' => $user?->roles->pluck('name')->map(fn (string $role): string => str($role)->headline()->toString())->join(', ') ?: 'Pharmacy user',
            'pharmacy' => $user?->pharmacy?->name ?? 'Assigned pharmacy',
            'branch' => $user?->pharmacyBranch?->name ?? 'All pharmacy branches',
        ];
    }

    public function getActivities(): array
    {
        $user = auth()->user();
        $pharmacyId = (int) ($user?->pharmacy_id ?? 0);
        $branchId = (int) ($user?->pharmacy_branch_id ?? 0);
        $activities = new Collection();

        if ($user?->can('sales.view')) {
            $sales = Sale::query()
                ->with('cashier')
                ->where('pharmacy_id', $pharmacyId)
                ->when($branchId > 0, fn ($query) => $query->where('pharmacy_branch_id', $branchId))
                ->latest('sold_at')
                ->limit(8)
                ->get()
                ->map(fn (Sale $sale): array => [
                    'time' => $sale->voided_at ?? $sale->completed_at ?? $sale->sold_at,
                    'title' => $sale->status === 'voided' ? 'POS sale voided' : 'POS sale completed',
                    'description' => sprintf(
                        '%s · %s BIF · %s',
                        $sale->sale_number,
                        number_format((float) $sale->grand_total, 0),
                        $sale->cashier?->name ?? 'Pharmacy user',
                    ),
                    'tone' => $sale->status === 'voided' ? 'danger' : 'success',
                    'icon' => $sale->status === 'voided' ? 'heroicon-o-arrow-uturn-left' : 'heroicon-o-receipt-percent',
                ]);

            $activities = $activities->concat($sales);
        }

        if ($user?->can('marketplace.orders.view')) {
            $orders = MarketplaceOrderEvent::query()
                ->with(['order', 'actorUser'])
                ->whereHas('order', function ($query) use ($pharmacyId, $branchId): void {
                    $query->where('pharmacy_id', $pharmacyId)
                        ->when($branchId > 0, fn ($query) => $query->where('pharmacy_branch_id', $branchId));
                })
                ->latest('occurred_at')
                ->limit(10)
                ->get()
                ->map(fn (MarketplaceOrderEvent $event): array => [
                    'time' => $event->occurred_at,
                    'title' => $event->title,
                    'description' => sprintf(
                        '%s · %s',
                        $event->order?->order_number ?? 'Online order',
                        $event->actorUser?->name ?? 'System',
                    ),
                    'tone' => 'info',
                    'icon' => 'heroicon-o-shopping-bag',
                ]);

            $activities = $activities->concat($orders);
        }

        if ($user?->can('stock.view')) {
            $movements = StockMovement::query()
                ->with(['pharmacyMedicine.medicine', 'createdByUser'])
                ->where('pharmacy_id', $pharmacyId)
                ->when($branchId > 0, fn ($query) => $query->where('pharmacy_branch_id', $branchId))
                ->latest('occurred_at')
                ->limit(8)
                ->get()
                ->map(fn (StockMovement $movement): array => [
                    'time' => $movement->occurred_at,
                    'title' => 'Stock '.str($movement->movement_type)->headline(),
                    'description' => sprintf(
                        '%s · %s %s unit(s)',
                        $movement->pharmacyMedicine?->medicine?->brand_name ?? 'Medicine',
                        $movement->direction === 'in' ? '+' : '-',
                        number_format((float) $movement->quantity, 3),
                    ),
                    'tone' => $movement->direction === 'in' ? 'success' : 'warning',
                    'icon' => 'heroicon-o-arrows-right-left',
                ]);

            $activities = $activities->concat($movements);
        }

        if ($user?->can('prescriptions.view')) {
            $prescriptions = PrescriptionActivity::query()
                ->with(['prescription', 'actorUser'])
                ->where('pharmacy_id', $pharmacyId)
                ->when($branchId > 0, fn ($query) => $query->where('pharmacy_branch_id', $branchId))
                ->latest('occurred_at')
                ->limit(8)
                ->get()
                ->map(fn (PrescriptionActivity $activity): array => [
                    'time' => $activity->occurred_at,
                    'title' => $activity->title,
                    'description' => sprintf(
                        '%s · %s',
                        $activity->prescription?->prescription_number ?? 'Prescription',
                        $activity->actorUser?->name ?? 'System',
                    ),
                    'tone' => 'warning',
                    'icon' => 'heroicon-o-clipboard-document-check',
                ]);

            $activities = $activities->concat($prescriptions);
        }

        return $activities
            ->filter(fn (array $activity): bool => filled($activity['time']))
            ->sortByDesc('time')
            ->take(14)
            ->values()
            ->map(function (array $activity): array {
                $activity['relative_time'] = $activity['time']->diffForHumans();
                return $activity;
            })
            ->all();
    }
}
