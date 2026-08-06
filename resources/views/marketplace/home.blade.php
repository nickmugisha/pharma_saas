@extends('layouts.marketplace', [
    'title' => 'Home Pharma — Medicines and wellness delivered',
    'description' => 'Shop medicines and wellness essentials from verified pharmacies across Burundi.',
])

@section('content')
<section class="home-storefront-hero">
    <div
        class="marketplace-container home-storefront-hero__grid"
    >
        <div
            class="home-storefront-hero__content"
            data-reveal="left"
        >
            <span class="home-storefront-kicker">
                Burundi's trusted online pharmacy
            </span>

            <h1>
                Your health essentials,
                <span>ready when you need them.</span>
            </h1>

            <p>
                Shop medicines, wellness products and everyday
                healthcare essentials from verified pharmacies.
                Choose pickup or delivery and pay securely with
                your Home Pharma wallet.
            </p>

            <form
                action="{{ route('marketplace.catalogue.index') }}"
                method="GET"
                class="home-storefront-search"
            >
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path
                        d="m21 21-4.35-4.35m2.35-5.65a8 8 0 1 1-16 0 8 8 0 0 1 16 0Z"
                    />
                </svg>

                <input
                    type="search"
                    name="search"
                    placeholder="Search medicines and health products"
                >

                <button type="submit">
                    Shop now
                </button>
            </form>

            <div class="home-storefront-actions">
                <a
                    href="{{ route('marketplace.catalogue.index') }}"
                    class="marketplace-button marketplace-button-primary"
                >
                    Browse all products
                </a>

                @guest
                    <a
                        href="{{ route('client.register') }}"
                        class="home-storefront-secondary-button"
                    >
                        Create my account
                    </a>
                @else
                    @if(auth()->user()->hasRole('client'))
                        <a
                            href="{{ route('client.dashboard') }}"
                            class="home-storefront-secondary-button"
                        >
                            Open my account
                        </a>
                    @endif
                @endguest
            </div>

            <div class="home-storefront-trust">
                <span>
                    <strong>Verified</strong>
                    pharmacies
                </span>

                <span>
                    <strong>Secure</strong>
                    wallet payments
                </span>

                <span>
                    <strong>Protected</strong>
                    prescriptions
                </span>
            </div>
        </div>

        <div
            class="home-storefront-showcase"
            data-reveal="right"
            data-delay="150"
        >
            <div class="home-storefront-glow"></div>

            <div class="home-storefront-main-card">
                <span class="home-storefront-card-label">
                    Available today
                </span>

                <div class="home-storefront-card-image">
                    @if($featured->first()?->marketplace_image_url)
                        <img
                            src="{{ $featured->first()->marketplace_image_url }}"
                            alt="{{ $featured->first()->brand_name }}"
                        >
                    @else
                        <div class="product-placeholder">
                            <span>+</span>
                            <small>Medicine</small>
                        </div>
                    @endif
                </div>

                <div>
                    <small>
                        {{ $featured->first()?->category?->name
                            ?? 'Healthcare essentials' }}
                    </small>

                    <h2>
                        {{ $featured->first()?->brand_name
                            ?? 'Health essentials' }}
                    </h2>

                    <p>
                        {{ $featured->first()?->generic_name
                            ?? 'Trusted pharmacy products' }}
                    </p>
                </div>

                <a
                    href="{{ route('marketplace.catalogue.index') }}"
                >
                    Start shopping →
                </a>
            </div>

            <div class="home-storefront-floating-card card-one">
                <span>Fast fulfilment</span>
                <strong>Pickup or delivery</strong>
            </div>

            <div class="home-storefront-floating-card card-two">
                <span>Safe checkout</span>
                <strong>Wallet protected</strong>
            </div>
        </div>
    </div>
</section>

<section class="home-category-section">
    <div class="marketplace-container">
        <div class="home-category-header">
            <div>
                <span class="eyebrow">Shop by category</span>
                <h2>What are you looking for today?</h2>
            </div>

            <a href="{{ route('marketplace.catalogue.index') }}">
                See everything →
            </a>
        </div>

        <div class="home-category-pills">
            <a
                href="{{ route('marketplace.catalogue.index') }}"
                class="home-category-pill is-active"
            >
                All products
            </a>

            @foreach($categories as $category)
                <a
                    href="{{ route('marketplace.catalogue.index', [
                        'category' => $category->id,
                    ]) }}"
                    class="home-category-pill"
                >
                    {{ $category->name }}

                    <span>
                        {{ $category->marketplace_products_count }}
                    </span>
                </a>
            @endforeach
        </div>
    </div>
</section>

<section class="home-featured-products">
    <div class="marketplace-container">
        <div
            class="section-heading"
            data-reveal
        >
            <div>
                <span class="eyebrow">
                    Recommended for you
                </span>

                <h2>
                    Medicines and wellness essentials
                </h2>

                <p class="home-section-description">
                    Popular products currently available from
                    verified Home Pharma partners.
                </p>
            </div>

            <a href="{{ route('marketplace.catalogue.index') }}">
                Shop all products →
            </a>
        </div>

        <div class="product-grid">
            @forelse($featured as $medicine)
                <div
                    data-reveal="scale"
                    data-delay="{{ $loop->index * 90 }}"
                >
                    @include(
                        'marketplace.partials.product-card',
                        ['medicine' => $medicine]
                    )
                </div>
            @empty
                <div class="empty-panel col-span-full">
                    Products will appear as soon as verified
                    pharmacies publish available stock.
                </div>
            @endforelse
        </div>
    </div>
</section>

<section class="home-shopping-benefits">
    <div class="marketplace-container">
        <div class="home-benefits-grid">
            <article data-reveal>
                <span>01</span>

                <div>
                    <h3>Shop freely</h3>
                    <p>
                        Explore products without creating an
                        account. Register only when you are ready
                        to order.
                    </p>
                </div>
            </article>

            <article
                data-reveal
                data-delay="100"
            >
                <span>02</span>

                <div>
                    <h3>Pay securely</h3>
                    <p>
                        Use your protected internal wallet and
                        keep a complete history of every payment
                        and refund.
                    </p>
                </div>
            </article>

            <article
                data-reveal
                data-delay="200"
            >
                <span>03</span>

                <div>
                    <h3>Receive safely</h3>
                    <p>
                        Choose pharmacy pickup or delivery while
                        prescription medicines remain protected
                        by professional review.
                    </p>
                </div>
            </article>
        </div>
    </div>
</section>

<section class="marketplace-container py-16">
    <div
        class="home-prescription-banner"
        data-reveal="scale"
    >
        <div>
            <span class="eyebrow eyebrow-light">
                Professional care
            </span>

            <h2>
                Prescription medicine,
                handled responsibly.
            </h2>

            <p>
                Upload your prescription securely, select the
                medicine you need and let the chosen pharmacy
                complete the required professional review.
            </p>

            @auth
                @if(auth()->user()->hasRole('client'))
                    <a
                        href="{{ route('client.dashboard') }}"
                        class="home-prescription-link"
                    >
                        Open prescription vault →
                    </a>
                @endif
            @else
                <a
                    href="{{ route('client.register') }}"
                    class="home-prescription-link"
                >
                    Create a secure account →
                </a>
            @endauth
        </div>

        <div class="home-prescription-visual">
            <span>Rx</span>

            <div>
                <strong>Private upload</strong>
                <small>Reviewed by pharmacy professionals</small>
            </div>
        </div>
    </div>
</section>
@endsection