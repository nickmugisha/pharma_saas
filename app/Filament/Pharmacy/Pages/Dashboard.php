<?php

namespace App\Filament\Pharmacy\Pages;

use App\Filament\Pharmacy\Widgets\TeamRoleDistributionChart;

use App\Filament\Pharmacy\Widgets\StaffOverviewStats;

use App\Filament\Pharmacy\Widgets\StaffActivityFeed;

use App\Filament\Pharmacy\Widgets\BranchTeamDistributionChart;

use App\Filament\Pharmacy\Widgets\PharmacyActivityFeed;
use App\Filament\Pharmacy\Widgets\PharmacyOverviewStats;
use App\Filament\Pharmacy\Widgets\PharmacySalesChart;
use App\Filament\Pharmacy\Widgets\PharmacyWorkloadChart;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'Pharmacy operations';

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
            PharmacyOverviewStats::class,
            PharmacySalesChart::class,
            PharmacyWorkloadChart::class,
            PharmacyActivityFeed::class,
            StaffOverviewStats::class,
            TeamRoleDistributionChart::class,
            BranchTeamDistributionChart::class,
            StaffActivityFeed::class,
        ];
    }
}
