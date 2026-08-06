<?php

namespace App\Http\Controllers\Marketplace;

use App\Actions\Marketplace\PayMarketplaceOrder;
use App\Actions\Wallet\RequestWalletFunding;
use App\Http\Controllers\Controller;
use App\Models\MarketplaceOrder;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClientWalletController extends Controller
{
    public function index(Request $request): View
    {
        $user = $this->client($request);
        $wallet = $user->wallet()->with([
            'transactions.createdByUser',
            'fundingRequests.reviewedByUser',
        ])->firstOrFail();

        $transactions = $wallet->transactions()
            ->with('createdByUser')
            ->paginate(15, ['*'], 'transactions_page');

        $fundingRequests = $wallet->fundingRequests()
            ->with('reviewedByUser')
            ->paginate(10, ['*'], 'funding_page');

        return view('marketplace.wallet.index', compact(
            'user',
            'wallet',
            'transactions',
            'fundingRequests',
        ));
    }

    public function requestFunding(
        Request $request,
        RequestWalletFunding $funding,
    ): RedirectResponse {
        $user = $this->client($request);
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1000', 'max:5000000'],
            'funding_method' => [
                'required',
                'in:cash_deposit,mobile_money,bank_transfer,demo_credit',
            ],
            'external_reference' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $funding->handle(
            client: $user,
            amount: (float) $data['amount'],
            fundingMethod: $data['funding_method'],
            externalReference: $data['external_reference'] ?? null,
            notes: $data['notes'] ?? null,
        );

        return back()->with(
            'success',
            'Funding request submitted for finance review.',
        );
    }

    public function payOrder(
        Request $request,
        MarketplaceOrder $order,
        PayMarketplaceOrder $payment,
    ): RedirectResponse {
        $user = $this->client($request);
        abort_unless((int) $order->user_id === (int) $user->id, 404);

        $payment->handle($user, $order);

        return redirect()
            ->route('client.orders.show', $order)
            ->with('success', 'Wallet payment completed. Your order is confirmed.');
    }

    private function client(Request $request): User
    {
        $user = $request->user();

        abort_unless(
            $user instanceof User
            && $user->is_active
            && $user->hasRole('client'),
            403,
        );

        return $user;
    }
}
