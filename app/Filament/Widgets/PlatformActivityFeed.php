<?php

namespace App\Filament\Widgets;

use App\Models\LoginHistory;
use App\Models\MarketplaceOrderEvent;
use App\Models\Pharmacy;
use App\Models\WalletFundingRequest;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

class PlatformActivityFeed extends Widget
{
protected string $view = 'filament.widgets.platform-activity-feed';
    protected static ?int $sort = 4;

    protected int | string | array $columnSpan = 'full';

    protected static bool $isLazy = false;

    public function getActivities(): array
    {
        $logins = LoginHistory::query()
            ->with('user')
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn (LoginHistory $history): array => [
                'time' => $history->created_at,
                'title' => match ($history->event) {
                    'login_success' => 'Successful sign-in',
                    'login_failed' => 'Failed sign-in attempt',
                    'logout' => 'User signed out',
                    default => str($history->event)->headline()->toString(),
                },
                'description' => sprintf(
                    '%s · %s panel · %s',
                    $history->user?->name ?? $history->email,
                    str($history->panel ?? 'unknown')->headline(),
                    $history->ip_address ?: 'IP unavailable',
                ),
                'tone' => $history->event === 'login_failed' ? 'danger' : 'info',
                'icon' => $history->event === 'login_failed'
                    ? 'heroicon-o-shield-exclamation'
                    : 'heroicon-o-arrow-right-end-on-rectangle',
            ]);

        $funding = WalletFundingRequest::query()
            ->with('user')
            ->latest('requested_at')
            ->limit(6)
            ->get()
            ->map(fn (WalletFundingRequest $request): array => [
                'time' => $request->reviewed_at ?? $request->requested_at,
                'title' => 'Wallet funding '.str($request->status)->headline(),
                'description' => sprintf(
                    '%s · %s BIF · %s',
                    $request->user?->name ?? 'Client',
                    number_format((float) $request->amount, 0),
                    $request->request_number,
                ),
                'tone' => match ($request->status) {
                    WalletFundingRequest::STATUS_APPROVED => 'success',
                    WalletFundingRequest::STATUS_REJECTED => 'danger',
                    default => 'warning',
                },
                'icon' => 'heroicon-o-wallet',
            ]);

        $orders = MarketplaceOrderEvent::query()
            ->with(['order', 'actorUser'])
            ->latest('occurred_at')
            ->limit(8)
            ->get()
            ->map(fn (MarketplaceOrderEvent $event): array => [
                'time' => $event->occurred_at,
                'title' => $event->title,
                'description' => sprintf(
                    '%s · %s',
                    $event->order?->order_number ?? 'Marketplace order',
                    $event->actorUser?->name ?? 'System',
                ),
                'tone' => 'success',
                'icon' => 'heroicon-o-shopping-bag',
            ]);

        $pharmacies = Pharmacy::query()
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (Pharmacy $pharmacy): array => [
                'time' => $pharmacy->approved_at ?? $pharmacy->created_at,
                'title' => 'Partner pharmacy '.str($pharmacy->status)->headline(),
                'description' => $pharmacy->name.' · '.$pharmacy->city,
                'tone' => $pharmacy->status === 'approved' ? 'success' : 'warning',
                'icon' => 'heroicon-o-building-storefront',
            ]);

        return (new Collection())
            ->concat($logins)
            ->concat($funding)
            ->concat($orders)
            ->concat($pharmacies)
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
