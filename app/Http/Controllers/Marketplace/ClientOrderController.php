<?php

namespace App\Http\Controllers\Marketplace;

use App\Actions\Marketplace\ReleaseMarketplaceOrderStock;
use App\Actions\Marketplace\RecordMarketplaceOrderEvent;
use App\Http\Controllers\Controller;
use App\Models\MarketplaceOrder;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClientOrderController extends Controller
{
    public function index(Request $request): View
    {
        $user = $this->client($request);
        $orders = $user->marketplaceOrders()
            ->with(['pharmacy', 'branch', 'items'])
            ->latest()
            ->paginate(12);

        return view('marketplace.orders.index', compact('orders'));
    }

    public function show(Request $request, MarketplaceOrder $order): View
    {
        $user = $this->client($request);
        abort_unless($order->user_id === $user->id, 404);

        $order->load([
            'pharmacy',
            'branch',
            'wallet',
            'walletPaymentTransaction',
            'walletRefundTransaction',
            'items.clientPrescription',
            'stockReservations.medicineBatch',
            'events.actorUser',
        ]);

        return view('marketplace.orders.show', compact('order'));
    }

    public function cancel(
        Request $request,
        MarketplaceOrder $order,
        ReleaseMarketplaceOrderStock $releaseStock,
    ): RedirectResponse {
        $user = $this->client($request);
        abort_unless($order->user_id === $user->id, 404);

        abort_unless(in_array($order->status, [
            MarketplaceOrder::STATUS_AWAITING_REVIEW,
            MarketplaceOrder::STATUS_AWAITING_PAYMENT,
        ], true), 422);

        $reason = 'Cancelled by the client before payment.';

        if ($order->stockReservations()->where('status', 'held')->exists()) {
            $releaseStock->handle(
                order: $order,
                reason: $reason,
                actor: $user,
            );
        } else {
            $order->forceFill([
                'status' => MarketplaceOrder::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
            ])->save();

            app(RecordMarketplaceOrderEvent::class)->handle(
                order: $order,
                eventType: 'order_cancelled',
                title: 'Order cancelled',
                description: $reason,
                actor: $user,
            );
        }

        return redirect()->route('client.orders.show', $order)
            ->with('success', 'Order cancelled and reserved stock released.');
    }

    private function client(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->is_active && $user->hasRole('client'), 403);
        return $user;
    }
}
