<?php

namespace App\Console\Commands;

use App\Actions\Stock\SyncInventoryAlerts;
use Illuminate\Console\Command;

class SyncInventoryAlertsCommand extends Command
{
    protected $signature =
        'inventory:sync-alerts {--pharmacy= : Pharmacy ID}';

    protected $description =
        'Synchronize low-stock and expiry inventory alerts';

    public function handle(
        SyncInventoryAlerts $syncInventoryAlerts,
    ): int {
        $pharmacyId = filled($this->option('pharmacy'))
            ? (int) $this->option('pharmacy')
            : null;

        $count = $syncInventoryAlerts->handle(
            pharmacyId: $pharmacyId,
        );

        $this->info(
            "{$count} inventory setting(s) synchronized.",
        );

        return self::SUCCESS;
    }
}