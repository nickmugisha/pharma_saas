<?php

namespace App\Console\Commands;

use App\Actions\Marketplace\ReleaseMarketplaceOrderStock;
use App\Models\MarketplaceOrder;
use Illuminate\Console\Command;

class ReleaseExpiredMarketplaceReservations extends Command
{
    protected $signature = 'marketplace:release-expired-reservations';

    protected $description = 'Release expired marketplace stock holds and mark orders expired.';

    public function handle(ReleaseMarketplaceOrderStock $release): int
    {
        $count = 0;

        MarketplaceOrder::query()
            ->where('status', MarketplaceOrder::STATUS_AWAITING_PAYMENT)
            ->whereNotNull('reservation_expires_at')
            ->where('reservation_expires_at', '<=', now())
            ->orderBy('id')
            ->chunkById(100, function ($orders) use ($release, &$count): void {
                foreach ($orders as $order) {
                    $release->handle(
                        order: $order,
                        reason: 'Wallet payment was not completed before the reservation expired.',
                        finalStatus: MarketplaceOrder::STATUS_EXPIRED,
                    );
                    $count++;
                }
            });

        $this->info("Released {$count} expired marketplace order reservation(s).");

        return self::SUCCESS;
    }
}
