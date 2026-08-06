<?php

namespace App\Actions\Marketplace;

use App\Models\MarketplaceOrder;
use App\Models\MarketplaceOrderEvent;
use App\Models\User;

class RecordMarketplaceOrderEvent
{
    public function handle(
        MarketplaceOrder $order,
        string $eventType,
        string $title,
        ?string $description = null,
        array $metadata = [],
        ?User $actor = null,
    ): MarketplaceOrderEvent {
        return MarketplaceOrderEvent::create([
            'marketplace_order_id' => $order->id,
            'actor_user_id' => $actor?->id,
            'event_type' => $eventType,
            'title' => $title,
            'description' => $description,
            'metadata' => $metadata === [] ? null : $metadata,
            'occurred_at' => now(),
        ]);
    }
}
