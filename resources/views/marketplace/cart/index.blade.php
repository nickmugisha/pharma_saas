@extends('layouts.marketplace', ['title' => 'My cart — PharmaMarket'])
@section('content')
<section class="marketplace-container py-10">
    <div class="section-heading"><div><span class="eyebrow">Your cart</span><h1 class="page-title">Review pharmacy offers</h1></div><a href="{{ route('marketplace.catalogue.index') }}">Continue shopping →</a></div>

    @if($cart->items->isEmpty())
        <div class="empty-panel mt-8"><strong>Your cart is empty.</strong><p>Browse the public catalogue and choose a pharmacy offer.</p><a href="{{ route('marketplace.catalogue.index') }}" class="marketplace-button marketplace-button-primary mt-5">Browse medicines</a></div>
    @else
        <div class="cart-layout mt-8">
            <div class="space-y-5">
                @foreach($cart->items->groupBy(fn($item) => $item->pharmacy_id.':'.$item->pharmacy_branch_id) as $group)
                    @php $first=$group->first(); @endphp
                    <section class="cart-group">
                        <div class="cart-group-header"><div><strong>{{ $first->pharmacy?->name }}</strong><p>{{ $first->branch?->name }}</p></div><span>{{ $group->count() }} item(s)</span></div>
                        @foreach($group as $item)
                            @php $offer=$item->current_offer; $medicine=$item->pharmacyMedicine?->medicine; @endphp
                            <article class="cart-item">
                                <div class="cart-thumb">@if($medicine?->marketplace_image_url)<img src="{{ $medicine->marketplace_image_url }}" alt="">@else<span>+</span>@endif</div>
                                <div class="cart-item-info"><strong>{{ $medicine?->brand_name }}</strong><p>{{ collect([$medicine?->strength,$medicine?->dosageForm?->name])->filter()->implode(' • ') }}</p>@include('marketplace.partials.mode-badge',['mode'=>$item->online_sale_mode])</div>
                                <form method="POST" action="{{ route('marketplace.cart.update',$item) }}" class="cart-update-form">@csrf @method('PATCH')
                                    <label>Quantity<input type="number" name="quantity" value="{{ $item->quantity }}" min="0.001" max="{{ $offer['max_order_quantity'] ?? $item->quantity }}" step="0.001"></label>
                                    <label>Fulfilment<select name="fulfillment_method">
                                        @if(($offer['pickup_enabled'] ?? $item->fulfillment_method==='pickup'))<option value="pickup" @selected($item->fulfillment_method==='pickup')>Pickup</option>@endif
                                        @if($offer['delivery_enabled'] ?? false)<option value="delivery" @selected($item->fulfillment_method==='delivery')>Delivery</option>@endif
                                    </select></label>
                                    <button type="submit" class="small-action">Update</button>
                                </form>
                                <div class="cart-price"><strong>{{ number_format((float)($offer['price'] ?? $item->unit_price_snapshot) * (float)$item->quantity,0) }} BIF</strong><small>{{ number_format((float)($offer['price'] ?? $item->unit_price_snapshot),0) }} each</small><form method="POST" action="{{ route('marketplace.cart.destroy',$item) }}">@csrf @method('DELETE')<button class="danger-link" type="submit">Remove</button></form></div>
                            </article>
                        @endforeach
                    </section>
                @endforeach
            </div>

            <aside class="cart-summary">
                <h2>Cart summary</h2>
                @php $subtotal=$cart->items->sum(fn($item)=>(float)($item->current_offer['price'] ?? $item->unit_price_snapshot)*(float)$item->quantity); @endphp
                <div class="summary-row"><span>Products</span><strong>{{ number_format($subtotal,0) }} BIF</strong></div>
                <div class="summary-row"><span>Delivery</span><strong>Calculated at checkout</strong></div>
                <div class="summary-total"><span>Estimated total</span><strong>{{ number_format($subtotal,0) }} BIF</strong></div>
                <p class="summary-note">Stock is not reserved while products are only in your cart. Checkout revalidates prices and availability.</p>
                <a href="{{ route('marketplace.checkout.show') }}" class="marketplace-button marketplace-button-primary w-full">Continue to checkout</a>
            </aside>
        </div>
    @endif
</section>
@endsection
