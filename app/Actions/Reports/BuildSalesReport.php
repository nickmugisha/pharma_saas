<?php

namespace App\Actions\Reports;

use App\Models\PharmacyBranch;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class BuildSalesReport
{
    public function handle(
        User $user,
        array $filters = [],
    ): array {
        abort_unless(
            $user->can('reports.view'),
            403,
        );

        $pharmacyId = (int) $user->pharmacy_id;

        abort_unless($pharmacyId > 0, 403);

        $startDate = CarbonImmutable::parse(
            $filters['start_date']
                ?? now()->subDays(29)->toDateString(),
        )->startOfDay();

        $endDate = CarbonImmutable::parse(
            $filters['end_date']
                ?? now()->toDateString(),
        )->endOfDay();

        if ($startDate->greaterThan($endDate)) {
            throw ValidationException::withMessages([
                'start_date' =>
                    'The start date must be before the end date.',
            ]);
        }

        if ($startDate->diffInDays($endDate) > 366) {
            throw ValidationException::withMessages([
                'end_date' =>
                    'A report period cannot exceed 366 days.',
            ]);
        }

        $branchId = filled(
            $filters['pharmacy_branch_id'] ?? null,
        )
            ? (int) $filters['pharmacy_branch_id']
            : null;

        if ($branchId !== null) {
            PharmacyBranch::query()
                ->whereKey($branchId)
                ->where('pharmacy_id', $pharmacyId)
                ->firstOrFail();
        }

        $completedSales = Sale::query()
            ->where('pharmacy_id', $pharmacyId)
            ->where('status', 'completed')
            ->whereBetween(
                'sold_at',
                [$startDate, $endDate],
            )
            ->when(
                $branchId,
                fn (Builder $query): Builder =>
                    $query->where(
                        'pharmacy_branch_id',
                        $branchId,
                    ),
            );

        $salesSummary = (clone $completedSales)
            ->selectRaw(
                '
                    COUNT(*) AS sales_count,
                    COALESCE(SUM(subtotal), 0) AS subtotal,
                    COALESCE(SUM(discount_total), 0) AS discount_total,
                    COALESCE(SUM(tax_total), 0) AS tax_total,
                    COALESCE(SUM(grand_total), 0) AS revenue,
                    COALESCE(SUM(change_amount), 0) AS change_total
                '
            )
            ->first();

        $itemTotals = SaleItem::query()
            ->join(
                'sales',
                'sales.id',
                '=',
                'sale_items.sale_id',
            )
            ->where('sales.pharmacy_id', $pharmacyId)
            ->where('sales.status', 'completed')
            ->whereBetween(
                'sales.sold_at',
                [$startDate, $endDate],
            )
            ->when(
                $branchId,
                fn ($query) =>
                    $query->where(
                        'sales.pharmacy_branch_id',
                        $branchId,
                    ),
            )
            ->selectRaw(
                '
                    COALESCE(SUM(sale_items.quantity), 0) AS units_sold,
                    COALESCE(SUM(sale_items.cost_total), 0) AS cost_of_goods
                '
            )
            ->first();

        $salesCount = (int) $salesSummary->sales_count;
        $subtotal = round(
            (float) $salesSummary->subtotal,
            2,
        );
        $discountTotal = round(
            (float) $salesSummary->discount_total,
            2,
        );
        $taxTotal = round(
            (float) $salesSummary->tax_total,
            2,
        );
        $revenue = round(
            (float) $salesSummary->revenue,
            2,
        );
        $changeTotal = round(
            (float) $salesSummary->change_total,
            2,
        );
        $costOfGoods = round(
            (float) $itemTotals->cost_of_goods,
            2,
        );
        $unitsSold = round(
            (float) $itemTotals->units_sold,
            3,
        );

        $netSalesBeforeTax = round(
            $subtotal - $discountTotal,
            2,
        );

        $grossProfit = round(
            $netSalesBeforeTax - $costOfGoods,
            2,
        );

        $grossMargin = $netSalesBeforeTax > 0
            ? round(
                ($grossProfit / $netSalesBeforeTax) * 100,
                2,
            )
            : 0.0;

        $voidedSummary = Sale::query()
            ->where('pharmacy_id', $pharmacyId)
            ->where('status', 'voided')
            ->whereBetween(
                'sold_at',
                [$startDate, $endDate],
            )
            ->when(
                $branchId,
                fn (Builder $query): Builder =>
                    $query->where(
                        'pharmacy_branch_id',
                        $branchId,
                    ),
            )
            ->selectRaw(
                '
                    COUNT(*) AS voided_count,
                    COALESCE(SUM(grand_total), 0) AS voided_value
                '
            )
            ->first();

        $paymentMethods = SalePayment::query()
            ->join(
                'sales',
                'sales.id',
                '=',
                'sale_payments.sale_id',
            )
            ->where(
                'sale_payments.pharmacy_id',
                $pharmacyId,
            )
            ->where(
                'sale_payments.status',
                'completed',
            )
            ->where('sales.status', 'completed')
            ->whereBetween(
                'sales.sold_at',
                [$startDate, $endDate],
            )
            ->when(
                $branchId,
                fn ($query) =>
                    $query->where(
                        'sales.pharmacy_branch_id',
                        $branchId,
                    ),
            )
            ->groupBy('sale_payments.payment_method')
            ->orderByDesc('payment_total')
            ->selectRaw(
                '
                    sale_payments.payment_method,
                    COUNT(*) AS payment_count,
                    COALESCE(SUM(sale_payments.amount), 0) AS payment_total
                '
            )
            ->get()
            ->map(fn ($payment): array => [
                'payment_method' =>
                    $payment->payment_method,

                'payment_count' =>
                    (int) $payment->payment_count,

                'payment_total' =>
                    round(
                        (float) $payment->payment_total,
                        2,
                    ),
            ])
            ->values()
            ->all();

        $dailyTrend = Sale::query()
            ->where('pharmacy_id', $pharmacyId)
            ->where('status', 'completed')
            ->whereBetween(
                'sold_at',
                [$startDate, $endDate],
            )
            ->when(
                $branchId,
                fn (Builder $query): Builder =>
                    $query->where(
                        'pharmacy_branch_id',
                        $branchId,
                    ),
            )
            ->groupByRaw('DATE(sold_at)')
            ->orderByRaw('DATE(sold_at)')
            ->selectRaw(
                '
                    DATE(sold_at) AS sale_date,
                    COUNT(*) AS sales_count,
                    COALESCE(SUM(grand_total), 0) AS revenue
                '
            )
            ->get()
            ->map(fn (Sale $sale): array => [
                'sale_date' =>
                    $sale->getAttribute('sale_date'),

                'sales_count' =>
                    (int) $sale->getAttribute(
                        'sales_count'
                    ),

                'revenue' =>
                    round(
                        (float) $sale->getAttribute(
                            'revenue'
                        ),
                        2,
                    ),
            ])
            ->values()
            ->all();

        $topMedicines = SaleItem::query()
            ->join(
                'sales',
                'sales.id',
                '=',
                'sale_items.sale_id',
            )
            ->where('sales.pharmacy_id', $pharmacyId)
            ->where('sales.status', 'completed')
            ->whereBetween(
                'sales.sold_at',
                [$startDate, $endDate],
            )
            ->when(
                $branchId,
                fn ($query) =>
                    $query->where(
                        'sales.pharmacy_branch_id',
                        $branchId,
                    ),
            )
            ->groupBy(
                'sale_items.pharmacy_medicine_id',
                'sale_items.medicine_name',
            )
            ->orderByDesc('revenue')
            ->limit(10)
            ->selectRaw(
                '
                    sale_items.pharmacy_medicine_id,
                    sale_items.medicine_name,
                    COALESCE(SUM(sale_items.quantity), 0) AS quantity,
                    COALESCE(SUM(sale_items.line_total), 0) AS revenue,
                    COALESCE(SUM(sale_items.cost_total), 0) AS cost
                '
            )
            ->get()
            ->map(function ($item): array {
                $itemRevenue = round(
                    (float) $item->revenue,
                    2,
                );

                $itemCost = round(
                    (float) $item->cost,
                    2,
                );

                return [
                    'pharmacy_medicine_id' =>
                        (int) $item->pharmacy_medicine_id,

                    'medicine_name' =>
                        $item->medicine_name,

                    'quantity' =>
                        round(
                            (float) $item->quantity,
                            3,
                        ),

                    'revenue' =>
                        $itemRevenue,

                    'cost' =>
                        $itemCost,

                    'gross_profit' =>
                        round(
                            $itemRevenue - $itemCost,
                            2,
                        ),
                ];
            })
            ->values()
            ->all();

        $branchPerformance = Sale::query()
            ->join(
                'pharmacy_branches',
                'pharmacy_branches.id',
                '=',
                'sales.pharmacy_branch_id',
            )
            ->where('sales.pharmacy_id', $pharmacyId)
            ->where('sales.status', 'completed')
            ->whereBetween(
                'sales.sold_at',
                [$startDate, $endDate],
            )
            ->when(
                $branchId,
                fn ($query) =>
                    $query->where(
                        'sales.pharmacy_branch_id',
                        $branchId,
                    ),
            )
            ->groupBy(
                'sales.pharmacy_branch_id',
                'pharmacy_branches.name',
            )
            ->orderByDesc('revenue')
            ->selectRaw(
                '
                    sales.pharmacy_branch_id,
                    pharmacy_branches.name AS branch_name,
                    COUNT(*) AS sales_count,
                    COALESCE(SUM(sales.grand_total), 0) AS revenue
                '
            )
            ->get()
            ->map(fn ($branch): array => [
                'pharmacy_branch_id' =>
                    (int) $branch->pharmacy_branch_id,

                'branch_name' =>
                    $branch->branch_name,

                'sales_count' =>
                    (int) $branch->sales_count,

                'revenue' =>
                    round(
                        (float) $branch->revenue,
                        2,
                    ),
            ])
            ->values()
            ->all();

        return [
            'filters' => [
                'start_date' =>
                    $startDate->toDateString(),

                'end_date' =>
                    $endDate->toDateString(),

                'pharmacy_branch_id' =>
                    $branchId,
            ],

            'summary' => [
                'sales_count' =>
                    $salesCount,

                'units_sold' =>
                    $unitsSold,

                'subtotal' =>
                    $subtotal,

                'discount_total' =>
                    $discountTotal,

                'net_sales_before_tax' =>
                    $netSalesBeforeTax,

                'tax_total' =>
                    $taxTotal,

                'revenue' =>
                    $revenue,

                'cost_of_goods' =>
                    $costOfGoods,

                'gross_profit' =>
                    $grossProfit,

                'gross_margin_percentage' =>
                    $grossMargin,

                'average_sale' =>
                    $salesCount > 0
                        ? round(
                            $revenue / $salesCount,
                            2,
                        )
                        : 0.0,

                'cash_change_total' =>
                    $changeTotal,

                'voided_sales_count' =>
                    (int) $voidedSummary
                        ->voided_count,

                'voided_sales_value' =>
                    round(
                        (float) $voidedSummary
                            ->voided_value,
                        2,
                    ),
            ],

            'payment_methods' =>
                $paymentMethods,

            'daily_trend' =>
                $dailyTrend,

            'top_medicines' =>
                $topMedicines,

            'branch_performance' =>
                $branchPerformance,
        ];
    }
}