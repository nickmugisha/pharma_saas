<?php

namespace App\Http\Controllers\Marketplace;

use App\Actions\Marketplace\ManageMarketplaceCart;
use App\Http\Controllers\Controller;
use App\Models\MarketplaceCartItem;
use App\Models\User;
use App\Services\MarketplaceCatalogue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MarketplaceCartController extends Controller
{
    public function __construct(
        private readonly ManageMarketplaceCart $cartAction,
        private readonly MarketplaceCatalogue $catalogue,
    ) {
    }

    public function index(Request $request): View
    {
        $user = $this->client($request);
        $cart = $this->cartAction->current($user);
        $cart->load([
            'items.pharmacy',
            'items.branch',
            'items.pharmacyMedicine.medicine.primaryImage',
        ]);

        $cart->items->each(function (MarketplaceCartItem $item): void {
            $item->setAttribute(
                'current_offer',
                $this->catalogue->findOffer(
                    $item->pharmacy_medicine_id,
                    $item->pharmacy_branch_id,
                ),
            );
        });

        return view('marketplace.cart.index', compact('cart'));
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $this->client($request);
        $data = $request->validate([
            'pharmacy_medicine_id' => ['required', 'integer'],
            'pharmacy_branch_id' => ['required', 'integer'],
            'quantity' => ['required', 'numeric', 'min:0.001'],
            'fulfillment_method' => ['required', 'in:pickup,delivery'],
        ]);

        $this->cartAction->add(
            user: $user,
            pharmacyMedicineId: (int) $data['pharmacy_medicine_id'],
            branchId: (int) $data['pharmacy_branch_id'],
            quantity: (float) $data['quantity'],
            fulfillmentMethod: $data['fulfillment_method'],
        );

        return redirect()->route('marketplace.cart.index')
            ->with('success', 'Medicine added to your cart.');
    }

    public function update(
        Request $request,
        MarketplaceCartItem $item,
    ): RedirectResponse {
        $user = $this->client($request);
        $data = $request->validate([
            'quantity' => ['required', 'numeric', 'min:0.001'],
            'fulfillment_method' => ['required', 'in:pickup,delivery'],
        ]);

        $this->cartAction->update(
            $user,
            $item,
            (float) $data['quantity'],
            $data['fulfillment_method'],
        );

        return back()->with('success', 'Cart updated.');
    }

    public function destroy(
        Request $request,
        MarketplaceCartItem $item,
    ): RedirectResponse {
        $this->cartAction->remove($this->client($request), $item);
        return back()->with('success', 'Item removed from your cart.');
    }

    private function client(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->is_active && $user->hasRole('client'), 403);
        return $user;
    }
}
