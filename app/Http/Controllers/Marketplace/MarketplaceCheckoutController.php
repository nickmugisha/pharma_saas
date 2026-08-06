<?php

namespace App\Http\Controllers\Marketplace;

use App\Actions\Marketplace\CreateMarketplaceOrders;
use App\Actions\Marketplace\ManageMarketplaceCart;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MarketplaceCheckoutController extends Controller
{
    public function show(
        Request $request,
        ManageMarketplaceCart $cartAction,
    ): View|RedirectResponse {
        $user = $this->client($request);
        $cart = $cartAction->current($user);
        $cart->load([
            'items.pharmacy',
            'items.branch',
            'items.pharmacyMedicine.medicine',
        ]);

        if ($cart->items->isEmpty()) {
            return redirect()->route('marketplace.catalogue.index')
                ->with('error', 'Your cart is empty.');
        }

        $addresses = $user->clientAddresses()->orderByDesc('is_default')->get();
        $prescriptions = $user->clientPrescriptions()
            ->whereNot('status', 'rejected')
            ->where(function ($query): void {
                $query->whereNull('valid_until')->orWhereDate('valid_until', '>=', today());
            })
            ->latest()
            ->get();

        return view('marketplace.checkout.show', compact(
            'user',
            'cart',
            'addresses',
            'prescriptions',
        ));
    }

    public function store(
        Request $request,
        CreateMarketplaceOrders $createOrders,
    ): RedirectResponse {
        $user = $this->client($request);
        $data = $request->validate([
            'client_address_id' => ['nullable', 'integer'],
            'prescriptions' => ['nullable', 'array'],
            'prescriptions.*' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $orders = $createOrders->handle(
            client: $user,
            prescriptionSelections: $data['prescriptions'] ?? [],
            addressId: isset($data['client_address_id'])
                ? (int) $data['client_address_id']
                : null,
            notes: $data['notes'] ?? null,
        );

        return redirect()->route('client.orders.index')
            ->with('success', sprintf(
                '%d order(s) created. Each pharmacy will manage its own order.',
                $orders->count(),
            ));
    }

    private function client(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->is_active && $user->hasRole('client'), 403);
        return $user;
    }
}
