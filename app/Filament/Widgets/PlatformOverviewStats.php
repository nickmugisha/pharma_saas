<?php

namespace App\Filament\Widgets;

use App\Models\ClientProfile;
use App\Models\MarketplaceOrder;
use App\Models\Pharmacy;
use App\Models\WalletFundingRequest;
use App\Models\WalletTransaction;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PlatformOverviewStats extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected int | string | array $columnSpan = 'full';

    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        $activePharmacies = Pharmacy::query()
            ->where('status', 'approved')
            ->count();

        $clients = ClientProfile::query()->count();

        $paidOrders = MarketplaceOrder::query()
            ->where('payment_status', MarketplaceOrder::PAYMENT_PAID)
            ->sum('grand_total');

        $walletCredits = WalletTransaction::query()
            ->where('direction', WalletTransaction::DIRECTION_CREDIT)
            ->sum('amount');

        $walletDebits = WalletTransaction::query()
            ->where('direction', WalletTransaction::DIRECTION_DEBIT)
            ->sum('amount');

        $pendingFunding = WalletFundingRequest::query()
            ->where('status', WalletFundingRequest::STATUS_PENDING)
            ->count();

        return [
            Stat::make('Approved pharmacies', number_format($activePharmacies))
                ->description('Active marketplace partners')
                ->descriptionIcon('heroicon-m-building-storefront')
                ->color('primary')
                ->chart($this->trend(Pharmacy::class, 'approved_at')),

            Stat::make('Registered clients', number_format($clients))
                ->description('Platform-wide client accounts')
                ->descriptionIcon('heroicon-m-users')
                ->color('info')
                ->chart($this->trend(ClientProfile::class, 'created_at')),

            Stat::make('Marketplace GMV', number_format((float) $paidOrders, 0).' BIF')
                ->description('Paid online orders')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),

            Stat::make(
                'Wallet value',
                number_format((float) $walletCredits - (float) $walletDebits, 0).' BIF',
            )
                ->description($pendingFunding.' funding request(s) pending')
                ->descriptionIcon('heroicon-m-wallet')
                ->color($pendingFunding > 0 ? 'warning' : 'success'),
        ];
    }

    private function trend(string $model, string $column): array
    {
        $start = today()->subDays(6);

        $counts = $model::query()
            ->whereDate($column, '>=', $start)
            ->get([$column])
            ->groupBy(fn ($record): string => optional($record->{$column})->format('Y-m-d') ?? '')
            ->map->count();

        return collect(range(0, 6))
            ->map(fn (int $offset): int => (int) ($counts[$start->copy()->addDays($offset)->format('Y-m-d')] ?? 0))
            ->all();
    }
}
