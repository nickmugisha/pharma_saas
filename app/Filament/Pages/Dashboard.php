<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\ClientGrowthChart;
use App\Filament\Widgets\MarketplaceRevenueChart;
use App\Filament\Widgets\PlatformActivityFeed;
use App\Filament\Widgets\PlatformOverviewStats;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'Platform command center';

    public function getColumns(): int | array
    {
        return [
            'md' => 2,
            'xl' => 2,
        ];
    }

    public function getWidgets(): array
    {
        return [
            PlatformOverviewStats::class,
            MarketplaceRevenueChart::class,
            ClientGrowthChart::class,
            PlatformActivityFeed::class,
        ];
    }
}
