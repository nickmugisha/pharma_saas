@extends('layouts.marketplace', [
    'title' => $medicine->brand_name.' — Compare offers',
    'description' => $medicine->marketplace_summary ?? $medicine->description,
])

@section('content')
<section class="marketplace-container py-10">
    <nav class="breadcrumb">
        <a href="{{ route('marketplace.home') }}">Home</a><span>›</span>
        <a href="{{ route('marketplace.catalogue.index') }}">Shop</a><span>›</span>
        <span>{{ $medicine->brand_name }}</span>
    </nav>

    <div class="product-detail-grid">
        <div class="product-detail-media">
            @if($medicine->marketplace_image_url)
                <img src="{{ $medicine->marketplace_image_url }}" alt="{{ $medicine->brand_name }}">
            @else
                <div class="product-placeholder product-placeholder-large"><span>+</span><small>{{ $medicine->dosageForm?->name ?? 'Medicine' }}</small></div>
            @endif
        </div>
        <div>
            @include('marketplace.partials.mode-badge', ['mode' => $medicine->online_sale_mode])
            <p class="product-category mt-5">{{ $medicine->category?->name ?? 'Medicine' }}</p>
            <h1 class="detail-title">{{ $medicine->brand_name }}</h1>
            <p class="detail-subtitle">{{ collect([$medicine->generic_name, $medicine->strength, $medicine->dosageForm?->name, $medicine->package_size])->filter()->implode(' • ') }}</p>
            <p class="detail-description">{{ $medicine->marketplace_summary ?? $medicine->description ?? 'Compare available pharmacy offers below.' }}</p>

            <div class="detail-facts">
                <div><span>Available offers</span><strong>{{ $offers->count() }}</strong></div>
                <div><span>Starting price</span><strong>{{ number_format((float)$offers->min('price'), 0) }} BIF</strong></div>
                <div><span>Total stock</span><strong>{{ number_format((float)$offers->sum('available_quantity'), 0) }} units</strong></div>
            </div>

            @if($medicine->online_sale_mode === 'prescription_required')
                <div class="prescription-notice">
                    <strong>Prescription required</strong>
                    <p>You may browse and compare offers, but checkout requires an uploaded prescription and approval by the selected pharmacy.</p>
                </div>
            @elseif($medicine->online_sale_mode === 'pharmacist_review')
                <div class="prescription-notice prescription-notice-blue">
                    <strong>Pharmacist review required</strong>
                    <p>The selected pharmacy must approve this order before stock is reserved for wallet payment.</p>
                </div>
            @elseif($medicine->online_sale_mode === 'in_store_only')
                <div class="prescription-notice prescription-notice-dark">
                    <strong>Available in store only</strong>
                    <p>This medicine is visible for price and availability comparison, but cannot be reserved online.</p>
                </div>
            @endif
        </div>
    </div>
</section>

<section class="bg-slate-50 py-12">
    <div class="marketplace-container">
        <div class="section-heading">
            <div><span class="eyebrow">Choose your pharmacy</span><h2>Available offers</h2></div>
            <p class="max-w-lg text-sm text-slate-500">Price, stock and fulfilment are specific to each pharmacy branch.</p>
        </div>

        <div class="offer-list">
            @foreach($offers as $offer)
                <article class="offer-card">
                    <div class="offer-pharmacy">
                        <span class="offer-logo">+</span>
                        <div>
                            <h3>{{ $offer['pharmacy_name'] }}</h3>
                            <p>{{ $offer['branch_name'] }}{{ $offer['branch_city'] ? ' • '.$offer['branch_city'] : '' }}</p>
                            <div class="offer-services">
                                @if($offer['pickup_enabled'])<span>Pickup</span>@endif
                                @if($offer['delivery_enabled'])<span>Delivery +{{ number_format($offer['delivery_fee'], 0) }} BIF</span>@endif
                                <span>{{ $offer['preparation_minutes'] }} min prep</span>
                            </div>
                        </div>
                    </div>
                    <div class="offer-stock"><strong>{{ number_format($offer['available_quantity'], 3) }}</strong><span>units available</span></div>
                    <div class="offer-price"><strong>{{ number_format($offer['price'], 0) }} BIF</strong><span>per unit/package</span></div>
                   <div class="offer-action">
    @if($medicine->online_sale_mode === 'in_store_only')
        <button
            class="marketplace-button marketplace-button-disabled"
            disabled
        >
            Visit pharmacy
        </button>
    @else
        @auth
            @if(auth()->user()->hasRole('client'))
                <form
                    action="{{ route('marketplace.cart.store') }}"
                    method="POST"
                    class="offer-add-form"
                >
                    @csrf

                    <input
                        type="hidden"
                        name="pharmacy_medicine_id"
                        value="{{ $offer['pharmacy_medicine_id'] }}"
                    >

                    <input
                        type="hidden"
                        name="pharmacy_branch_id"
                        value="{{ $offer['branch_id'] }}"
                    >

                    <input
                        type="number"
                        name="quantity"
                        value="1"
                        min="0.001"
                        max="{{ $offer['max_order_quantity'] }}"
                        step="0.001"
                        aria-label="Quantity"
                    >

                    <select
                        name="fulfillment_method"
                        aria-label="Fulfilment method"
                    >
                        @if($offer['pickup_enabled'])
                            <option value="pickup">
                                Pickup
                            </option>
                        @endif

                        @if($offer['delivery_enabled'])
                            <option value="delivery">
                                Delivery
                            </option>
                        @endif
                    </select>

                    <button
                        class="marketplace-button marketplace-button-primary"
                        type="submit"
                    >
                        @if($medicine->online_sale_mode === 'prescription_required')
                            Add to cart — prescription required
                        @elseif($medicine->online_sale_mode === 'pharmacist_review')
                            Add to cart — review required
                        @else
                            Add to cart
                        @endif
                    </button>
                </form>
            @else
                <a
                    href="{{ url('/pharmacy') }}"
                    class="marketplace-button marketplace-button-soft"
                >
                    Staff account
                </a>
            @endif
        @else
            <a
                href="{{ route('client.login', ['redirect' => url()->current()]) }}"
                class="marketplace-button marketplace-button-primary"
            >
                Sign in to reserve
            </a>
        @endauth
    @endif
</div>
                </article>
            @endforeach
        </div>
    </div>
</section>

<section class="marketplace-container py-14">
    <div class="medicine-info-grid">
        <div><h3>Description</h3><p>{{ $medicine->description ?? 'No detailed description has been published.' }}</p></div>
        <div><h3>Indications</h3><p>{{ $medicine->indications ?? 'Consult a qualified pharmacist or clinician.' }}</p></div>
        <div><h3>Storage</h3><p>{{ $medicine->storage_instructions ?? 'Follow the instructions supplied by the pharmacy.' }}</p></div>
        <div><h3>Manufacturer</h3><p>{{ $medicine->manufacturer?->name ?? 'Not specified' }}</p></div>
    </div>
</section>
@endsection
