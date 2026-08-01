<x-filament-panels::page>
    @php
        $summary = $report['summary'] ?? [];
        $filters = $report['filters'] ?? [];

        $dailyTrend = $report['daily_trend'] ?? [];
        $topMedicines = $report['top_medicines'] ?? [];
        $paymentMethods = $report['payment_methods'] ?? [];
        $branchPerformance = $report['branch_performance'] ?? [];

        $money = fn ($value): string =>
            number_format((float) $value, 2).' BIF';

        $quantity = fn ($value): string =>
            number_format((float) $value, 3);

        $trendMaximum = max(
            (float) collect($dailyTrend)->max('revenue'),
            1,
        );

        $periodStart = filled($filters['start_date'] ?? null)
            ? \Illuminate\Support\Carbon::parse(
                $filters['start_date']
            )->format('d M Y')
            : '—';

        $periodEnd = filled($filters['end_date'] ?? null)
            ? \Illuminate\Support\Carbon::parse(
                $filters['end_date']
            )->format('d M Y')
            : '—';

        $metrics = [
            [
                'label' => 'Completed sales',
                'value' => number_format(
                    (int) ($summary['sales_count'] ?? 0)
                ),
                'hint' => 'Completed transactions',
            ],
            [
                'label' => 'Units sold',
                'value' => $quantity(
                    $summary['units_sold'] ?? 0
                ),
                'hint' => 'Medicine units',
            ],
            [
                'label' => 'Sales revenue',
                'value' => $money(
                    $summary['revenue'] ?? 0
                ),
                'hint' => 'Including applicable tax',
            ],
            [
                'label' => 'Cost of goods',
                'value' => $money(
                    $summary['cost_of_goods'] ?? 0
                ),
                'hint' => 'Recorded batch cost',
            ],
            [
                'label' => 'Gross profit',
                'value' => $money(
                    $summary['gross_profit'] ?? 0
                ),
                'hint' => 'Before tax',
            ],
            [
                'label' => 'Gross margin',
                'value' => number_format(
                    (float) (
                        $summary[
                            'gross_margin_percentage'
                        ] ?? 0
                    ),
                    2,
                ).'%',
                'hint' => 'Profit percentage',
            ],
            [
                'label' => 'Average sale',
                'value' => $money(
                    $summary['average_sale'] ?? 0
                ),
                'hint' => 'Average completed receipt',
            ],
            [
                'label' => 'Discounts',
                'value' => $money(
                    $summary['discount_total'] ?? 0
                ),
                'hint' => 'Total discounts granted',
            ],
            [
                'label' => 'Tax collected',
                'value' => $money(
                    $summary['tax_total'] ?? 0
                ),
                'hint' => 'Tax on completed sales',
            ],
            [
                'label' => 'Voided sales',
                'value' => number_format(
                    (int) (
                        $summary[
                            'voided_sales_count'
                        ] ?? 0
                    )
                ),
                'hint' => $money(
                    $summary[
                        'voided_sales_value'
                    ] ?? 0
                ).' reversed',
            ],
        ];
    @endphp

    <style>
        .sales-report-actions {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: .75rem;
            margin-top: 1rem;
        }

        .sales-report-period {
            margin-bottom: 1rem;
            padding: .8rem 1rem;
            border: 1px solid rgb(229 231 235);
            border-radius: .75rem;
            background: rgb(249 250 251);
            font-size: .875rem;
        }

        .dark .sales-report-period {
            border-color: rgb(55 65 81);
            background: rgb(17 24 39);
        }

        .sales-report-metrics {
            display: grid;
            grid-template-columns: repeat(1, minmax(0, 1fr));
            gap: 1rem;
        }

        .sales-report-card {
            padding: 1rem;
            border: 1px solid rgb(229 231 235);
            border-radius: .85rem;
            background: white;
            box-shadow: 0 1px 2px rgb(0 0 0 / .04);
        }

        .dark .sales-report-card {
            border-color: rgb(55 65 81);
            background: rgb(17 24 39);
        }

        .sales-report-card-label {
            margin: 0;
            color: rgb(107 114 128);
            font-size: .78rem;
            font-weight: 600;
            letter-spacing: .04em;
            text-transform: uppercase;
        }

        .dark .sales-report-card-label {
            color: rgb(156 163 175);
        }

        .sales-report-card-value {
            margin: .45rem 0 .2rem;
            font-size: 1.35rem;
            font-weight: 700;
            line-height: 1.25;
        }

        .sales-report-card-hint {
            margin: 0;
            color: rgb(107 114 128);
            font-size: .78rem;
        }

        .dark .sales-report-card-hint {
            color: rgb(156 163 175);
        }

        .sales-report-columns {
            display: grid;
            grid-template-columns: minmax(0, 1fr);
            gap: 1.25rem;
        }

        .sales-report-chart-scroll {
            overflow-x: auto;
            padding-bottom: .5rem;
        }

        .sales-report-chart {
            display: flex;
            align-items: flex-end;
            gap: .65rem;
            min-width: max-content;
            height: 260px;
            padding: 1rem .25rem 0;
        }

        .sales-report-chart-column {
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            width: 58px;
            height: 100%;
            text-align: center;
        }

        .sales-report-chart-value {
            margin-bottom: .35rem;
            font-size: .68rem;
            font-weight: 600;
            white-space: nowrap;
        }

        .sales-report-chart-track {
            display: flex;
            align-items: flex-end;
            width: 100%;
            height: 185px;
            overflow: hidden;
            border-radius: .45rem .45rem 0 0;
            background: rgb(243 244 246);
        }

        .dark .sales-report-chart-track {
            background: rgb(31 41 55);
        }

        .sales-report-chart-bar {
            width: 100%;
            min-height: 6px;
            border-radius: .45rem .45rem 0 0;
            background: rgb(79 70 229);
        }

        .sales-report-chart-label {
            margin-top: .4rem;
            color: rgb(107 114 128);
            font-size: .7rem;
            white-space: nowrap;
        }

        .dark .sales-report-chart-label {
            color: rgb(156 163 175);
        }

        .sales-report-table-wrap {
            width: 100%;
            overflow-x: auto;
        }

        .sales-report-table {
            width: 100%;
            min-width: 620px;
            border-collapse: collapse;
        }

        .sales-report-table th {
            padding: .7rem .8rem;
            border-bottom: 1px solid rgb(229 231 235);
            color: rgb(107 114 128);
            font-size: .73rem;
            font-weight: 600;
            text-align: left;
            text-transform: uppercase;
        }

        .sales-report-table td {
            padding: .8rem;
            border-bottom: 1px solid rgb(243 244 246);
            font-size: .875rem;
        }

        .dark .sales-report-table th {
            border-color: rgb(55 65 81);
            color: rgb(156 163 175);
        }

        .dark .sales-report-table td {
            border-color: rgb(31 41 55);
        }

        .sales-report-table td.numeric,
        .sales-report-table th.numeric {
            text-align: right;
            white-space: nowrap;
        }

        .sales-report-empty {
            padding: 2rem 1rem;
            color: rgb(107 114 128);
            text-align: center;
        }

        .dark .sales-report-empty {
            color: rgb(156 163 175);
        }

        @media (min-width: 640px) {
            .sales-report-metrics {
                grid-template-columns:
                    repeat(2, minmax(0, 1fr));
            }
        }

        @media (min-width: 1024px) {
            .sales-report-metrics {
                grid-template-columns:
                    repeat(5, minmax(0, 1fr));
            }

            .sales-report-columns {
                grid-template-columns:
                    repeat(2, minmax(0, 1fr));
            }
        }
    </style>

    <form wire:submit="applyFilters">
        {{ $this->form }}

        <div class="sales-report-actions">
            <x-filament::button
                type="submit"
                icon="heroicon-o-funnel"
                wire:loading.attr="disabled"
                wire:target="applyFilters"
            >
                Apply filters
            </x-filament::button>

            <x-filament::button
                type="button"
                color="gray"
                icon="heroicon-o-arrow-path"
                wire:click="resetFilters"
                wire:loading.attr="disabled"
                wire:target="resetFilters"
            >
                Reset
            </x-filament::button>

            <span
                wire:loading
                wire:target="applyFilters,resetFilters"
            >
                Updating report…
            </span>
        </div>
    </form>

    <div class="sales-report-period">
        Reporting period:
        <strong>{{ $periodStart }}</strong>
        to
        <strong>{{ $periodEnd }}</strong>

        @if (filled($filters['pharmacy_branch_id'] ?? null))
            — filtered to one branch
        @else
            — all pharmacy branches
        @endif
    </div>

    <div class="sales-report-metrics">
        @foreach ($metrics as $metric)
            <article class="sales-report-card">
                <p class="sales-report-card-label">
                    {{ $metric['label'] }}
                </p>

                <p class="sales-report-card-value">
                    {{ $metric['value'] }}
                </p>

                <p class="sales-report-card-hint">
                    {{ $metric['hint'] }}
                </p>
            </article>
        @endforeach
    </div>

    <x-filament::section icon="heroicon-o-chart-bar">
        <x-slot name="heading">
            Daily sales trend
        </x-slot>

        <x-slot name="description">
            Completed-sale revenue for each active sales day.
        </x-slot>

        @if ($dailyTrend === [])
            <div class="sales-report-empty">
                No completed sales were recorded during this period.
            </div>
        @else
            <div class="sales-report-chart-scroll">
                <div class="sales-report-chart">
                    @foreach ($dailyTrend as $point)
                        @php
                            $barHeight = max(
                                6,
                                min(
                                    100,
                                    (
                                        (float) $point['revenue']
                                        / $trendMaximum
                                    ) * 100,
                                ),
                            );
                        @endphp

                        <div class="sales-report-chart-column">
                            <div class="sales-report-chart-value">
                                {{ number_format(
                                    (float) $point['revenue'],
                                    0
                                ) }}
                            </div>

                            <div class="sales-report-chart-track">
                                <div
                                    class="sales-report-chart-bar"
                                    style="height: {{ $barHeight }}%"
                                    title="{{ $money($point['revenue']) }}"
                                ></div>
                            </div>

                            <div class="sales-report-chart-label">
                                {{ \Illuminate\Support\Carbon::parse(
                                    $point['sale_date']
                                )->format('d M') }}
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </x-filament::section>

    <div class="sales-report-columns">
        <x-filament::section icon="heroicon-o-trophy">
            <x-slot name="heading">
                Top medicines
            </x-slot>

            <x-slot name="description">
                Ranked by completed-sale revenue.
            </x-slot>

            <div class="sales-report-table-wrap">
                <table class="sales-report-table">
                    <thead>
                        <tr>
                            <th>Medicine</th>
                            <th class="numeric">Quantity</th>
                            <th class="numeric">Revenue</th>
                            <th class="numeric">Cost</th>
                            <th class="numeric">Profit</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($topMedicines as $medicine)
                            <tr>
                                <td>
                                    <strong>
                                        {{ $medicine['medicine_name'] }}
                                    </strong>
                                </td>

                                <td class="numeric">
                                    {{ $quantity(
                                        $medicine['quantity']
                                    ) }}
                                </td>

                                <td class="numeric">
                                    {{ $money(
                                        $medicine['revenue']
                                    ) }}
                                </td>

                                <td class="numeric">
                                    {{ $money(
                                        $medicine['cost']
                                    ) }}
                                </td>

                                <td class="numeric">
                                    {{ $money(
                                        $medicine['gross_profit']
                                    ) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5">
                                    <div class="sales-report-empty">
                                        No medicine sales found.
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>

        <x-filament::section icon="heroicon-o-banknotes">
            <x-slot name="heading">
                Payment methods
            </x-slot>

            <x-slot name="description">
                Completed payments tendered by method.
            </x-slot>

            <div class="sales-report-table-wrap">
                <table class="sales-report-table">
                    <thead>
                        <tr>
                            <th>Method</th>
                            <th class="numeric">Payments</th>
                            <th class="numeric">Tendered</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($paymentMethods as $payment)
                            <tr>
                                <td>
                                    {{ str(
                                        $payment['payment_method']
                                    )
                                        ->replace('_', ' ')
                                        ->title() }}
                                </td>

                                <td class="numeric">
                                    {{ number_format(
                                        $payment['payment_count']
                                    ) }}
                                </td>

                                <td class="numeric">
                                    {{ $money(
                                        $payment['payment_total']
                                    ) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3">
                                    <div class="sales-report-empty">
                                        No completed payments found.
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    </div>

    <x-filament::section icon="heroicon-o-building-storefront">
        <x-slot name="heading">
            Branch performance
        </x-slot>

        <x-slot name="description">
            Completed transactions and revenue by branch.
        </x-slot>

        <div class="sales-report-table-wrap">
            <table class="sales-report-table">
                <thead>
                    <tr>
                        <th>Branch</th>
                        <th class="numeric">Completed sales</th>
                        <th class="numeric">Revenue</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($branchPerformance as $branch)
                        <tr>
                            <td>
                                <strong>
                                    {{ $branch['branch_name'] }}
                                </strong>
                            </td>

                            <td class="numeric">
                                {{ number_format(
                                    $branch['sales_count']
                                ) }}
                            </td>

                            <td class="numeric">
                                {{ $money(
                                    $branch['revenue']
                                ) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3">
                                <div class="sales-report-empty">
                                    No branch sales found.
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>