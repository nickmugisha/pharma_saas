@extends('layouts.marketplace', ['title' => 'Checkout — PharmaMarket'])
@section('content')
<section class="marketplace-container py-10">
    <div class="section-heading"><div><span class="eyebrow">Secure checkout</span><h1 class="page-title">Create pharmacy reservations</h1></div><a href="{{ route('marketplace.cart.index') }}">← Back to cart</a></div>

    <div class="checkout-layout mt-8">
        <form method="POST" action="{{ route('marketplace.checkout.store') }}" class="space-y-6">@csrf
            @if($cart->items->contains('fulfillment_method','delivery'))
                <section class="checkout-panel"><h2>Delivery address</h2><p>Select one address for delivery orders created in this checkout.</p>
                    <div class="address-options mt-5">
                        @forelse($addresses as $address)
                            <label class="address-option"><input type="radio" name="client_address_id" value="{{ $address->id }}" @checked($address->is_default)><span><strong>{{ $address->label }} — {{ $address->recipient_name }}</strong><small>{{ $address->address_line_1 }}, {{ $address->city }} • {{ $address->phone }}</small></span></label>
                        @empty
                            <div class="prescription-notice">Add a delivery address from <a href="{{ route('client.dashboard') }}">your account</a> before continuing.</div>
                        @endforelse
                    </div>
                </section>
            @endif

            <section class="checkout-panel"><h2>Prescription requirements</h2><p>Choose a secure document for each restricted medicine. Pharmacy approval occurs before stock reservation.</p>
                <div class="space-y-4 mt-5">
                    @foreach($cart->items as $item)
                        @if(in_array($item->online_sale_mode,['prescription_required','pharmacist_review'],true))
                            <div class="prescription-select-row"><div><strong>{{ $item->pharmacyMedicine?->medicine?->brand_name }}</strong><small>{{ $item->pharmacy?->name }} • {{ str($item->online_sale_mode)->headline() }}</small></div><select name="prescriptions[{{ $item->id }}]" @required($item->online_sale_mode==='prescription_required')><option value="">{{ $item->online_sale_mode==='prescription_required' ? 'Select prescription' : 'Optional prescription' }}</option>@foreach($prescriptions as $prescription)<option value="{{ $prescription->id }}">{{ $prescription->prescription_number }} — {{ $prescription->original_name }}</option>@endforeach</select></div>
                        @endif
                    @endforeach
                </div>
                <details class="upload-details mt-5"><summary>Upload a new prescription</summary><p>Open your <a href="{{ route('client.dashboard') }}">client dashboard</a> to upload a private PDF or image, then return to checkout.</p></details>
            </section>

            <section class="checkout-panel"><h2>Order note</h2><textarea name="notes" rows="3" placeholder="Optional note for the pharmacy">{{ old('notes') }}</textarea></section>

            <section class="checkout-panel checkout-warning"><h2>Wallet payment</h2><p>Your wallet <strong>{{ $user->wallet?->wallet_number }}</strong> has an available balance of <strong>{{ number_format((float)$user->wallet?->available_balance,2) }} BIF</strong>. Checkout first creates pharmacy-specific orders and temporary stock holds. You then confirm each eligible order payment from its order page.</p><a href="{{ route('client.wallet.index') }}">Open wallet and request funding →</a></section>

            <button class="marketplace-button marketplace-button-primary w-full py-4 text-base" type="submit">Create reservation orders</button>
        </form>

        <aside class="checkout-summary">
            <h2>Checkout summary</h2>
            @foreach($cart->items->groupBy(fn($item)=>$item->pharmacy_id.':'.$item->pharmacy_branch_id.':'.$item->fulfillment_method) as $group)
                @php $first=$group->first(); @endphp
                <div class="checkout-pharmacy-group"><strong>{{ $first->pharmacy?->name }}</strong><small>{{ $first->branch?->name }} • {{ ucfirst($first->fulfillment_method) }}</small>@foreach($group as $item)<div><span>{{ $item->pharmacyMedicine?->medicine?->brand_name }} × {{ $item->quantity }}</span><strong>{{ number_format((float)$item->unit_price_snapshot*(float)$item->quantity,0) }} BIF</strong></div>@endforeach</div>
            @endforeach
            <p class="summary-note">Mixed-pharmacy carts are automatically separated into independent orders so each pharmacy sees only its own items.</p>
        </aside>
    </div>
</section>
@endsection
