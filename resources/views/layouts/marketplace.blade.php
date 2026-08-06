<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <meta
        name="csrf-token"
        content="{{ csrf_token() }}"
    >

    <title>{{ $title ?? 'Home Pharma' }}</title>

    <meta
        name="description"
        content="{{ $description ?? 'Compare trusted pharmacy offers, reserve medicines safely and manage prescriptions online.' }}"
    >

    <link
        rel="icon"
        type="image/png"
        href="{{ asset('images/branding/favicon.png') }}"
    >

    <link
        rel="preconnect"
        href="https://fonts.bunny.net"
    >

    <link
        href="https://fonts.bunny.net/css?family=manrope:400,500,600,700,800&display=swap"
        rel="stylesheet"
    >

    @vite([
        'resources/css/app.css',
        'resources/js/app.js',
    ])
</head>

<body class="marketplace-body">
    <x-login-loader />


    @php
        $authenticatedUser = auth()->user();

        $isClient = $authenticatedUser
            && $authenticatedUser->hasRole('client');

        $navCartCount = 0;

        if ($isClient) {
            $activeCart = $authenticatedUser
                ->marketplaceCarts()
                ->where('status', 'active')
                ->withCount('items')
                ->first();

            $navCartCount = (int) (
                $activeCart?->items_count ?? 0
            );
        }
    @endphp

    <header class="marketplace-header">
        <div class="marketplace-topbar">
            <div
                class="marketplace-container flex items-center justify-between gap-4 text-sm"
            >
                <p>
                    Trusted pharmacy offers across Burundi
                </p>

                <div class="hidden gap-5 sm:flex">
                    <span>Secure prescriptions</span>
                    <span>Transparent prices</span>
                </div>
            </div>
        </div>

        <div class="marketplace-container py-4">
            <div class="flex items-center justify-between gap-4">
                <a
                    href="{{ route('marketplace.home') }}"
                    class="marketplace-brand"
                    aria-label="Home Pharma home"
                >
                    <img
                        src="{{ asset('images/branding/home-pharma-logo.png') }}"
                        alt="Home Pharma"
                        class="marketplace-brand-logo"
                    >
                </a>

                <form
                    action="{{ route('marketplace.catalogue.index') }}"
                    method="GET"
                    class="header-search hidden lg:flex"
                >
                    <svg
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                    >
                        <path
                            d="m21 21-4.35-4.35m2.35-5.65a8 8 0 1 1-16 0 8 8 0 0 1 16 0Z"
                        />
                    </svg>

                    <input
                        type="search"
                        name="search"
                        value="{{ request('search') }}"
                        placeholder="Search medicine, molecule or category"
                    >

                    <button type="submit">
                        Search
                    </button>
                </form>

                <nav
                    class="flex items-center gap-2 sm:gap-3"
                    aria-label="Main navigation"
                >
                    <a
                        href="{{ route('marketplace.catalogue.index') }}"
                        class="header-icon-link"
                        title="Browse shop"
                    >
                        <svg
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >
                            <path
                                d="M4 6h16M4 12h16M4 18h10"
                            />
                        </svg>

                        <span class="hidden sm:inline">
                            Shop
                        </span>
                    </a>

                    @auth
                        @if($isClient)
                            <a
                                href="{{ route('marketplace.cart.index') }}"
                                class="header-icon-link relative"
                                title="Shopping cart"
                            >
                                <svg
                                    viewBox="0 0 24 24"
                                    aria-hidden="true"
                                >
                                    <path
                                        d="M3 4h2l2.2 10.2a2 2 0 0 0 2 1.6h7.8a2 2 0 0 0 2-1.6L20 7H6M10 20h.01M17 20h.01"
                                    />
                                </svg>

                                <span class="hidden sm:inline">
                                    Cart
                                </span>

                                @if($navCartCount > 0)
                                    <span
                                        class="cart-count"
                                        aria-label="{{ $navCartCount }} cart items"
                                    >
                                        {{ $navCartCount }}
                                    </span>
                                @endif
                            </a>

                            <a
                                href="{{ route('client.orders.index') }}"
                                class="header-icon-link hidden xl:inline-flex"
                            >
                                Orders
                            </a>

                            <a
                                href="{{ route('client.wallet.index') }}"
                                class="header-icon-link hidden xl:inline-flex"
                            >
                                Wallet
                            </a>

                            <a
                                href="{{ route('client.dashboard') }}"
                                class="account-pill"
                                title="My account"
                            >
                                <span class="account-avatar">
                                    {{ strtoupper(
                                        substr(
                                            $authenticatedUser->name,
                                            0,
                                            1
                                        )
                                    ) }}
                                </span>

                                <span class="hidden md:block">
                                    My account
                                </span>
                            </a>

                            <form
                                method="POST"
                                action="{{ route('client.logout') }}"
                                class="m-0"
                            >
                                @csrf

                                <button
                                    type="submit"
                                    class="header-icon-link"
                                    title="Sign out"
                                    style="
                                        border: 0;
                                        background: transparent;
                                        cursor: pointer;
                                    "
                                >
                                    <svg
                                        viewBox="0 0 24 24"
                                        aria-hidden="true"
                                    >
                                        <path
                                            d="M10 5H6a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h4M14 8l4 4-4 4M18 12H9"
                                        />
                                    </svg>

                                    <span class="hidden md:inline">
                                        Sign out
                                    </span>
                                </button>
                            </form>
                        @else
                            <a
                                href="{{ url('/pharmacy') }}"
                                class="account-pill"
                            >
                                Staff portal
                            </a>
                        @endif
                    @else
                        <a
                            href="{{ route('client.login') }}"
                            class="header-icon-link"
                        >
                            Sign in
                        </a>

                        <a
                            href="{{ route('client.register') }}"
                            class="marketplace-button marketplace-button-primary hidden sm:inline-flex"
                        >
                            Create account
                        </a>
                    @endauth
                </nav>
            </div>

            <form
                action="{{ route('marketplace.catalogue.index') }}"
                method="GET"
                class="header-search mt-4 flex lg:hidden"
            >
                <svg
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                >
                    <path
                        d="m21 21-4.35-4.35m2.35-5.65a8 8 0 1 1-16 0 8 8 0 0 1 16 0Z"
                    />
                </svg>

                <input
                    type="search"
                    name="search"
                    value="{{ request('search') }}"
                    placeholder="Search medicines"
                >

                <button type="submit">
                    Search
                </button>
            </form>

            @auth
                @if($isClient)
                    <nav
                        class="mt-3 flex flex-wrap gap-2 xl:hidden"
                        aria-label="Client navigation"
                    >
                        <a
                            href="{{ route('client.orders.index') }}"
                            class="header-icon-link"
                        >
                            Orders
                        </a>

                        <a
                            href="{{ route('client.wallet.index') }}"
                            class="header-icon-link"
                        >
                            Wallet
                        </a>
                    </nav>
                @endif
            @endauth
        </div>
    </header>

    @if(session('success'))
        <div class="marketplace-container pt-5">
            <div class="flash-message flash-success">
                {{ session('success') }}
            </div>
        </div>
    @endif

    @if(session('error'))
        <div class="marketplace-container pt-5">
            <div class="flash-message flash-error">
                {{ session('error') }}
            </div>
        </div>
    @endif

    @if($errors->any())
        <div class="marketplace-container pt-5">
            <div class="flash-message flash-error">
                <strong>
                    Please check the following:
                </strong>

                <ul class="mt-2 list-disc pl-5">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <main>
        @yield('content')
    </main>

    <footer class="marketplace-footer">
    <div
        class="marketplace-container grid gap-10 py-14 md:grid-cols-4"
    >
        <div
            class="md:col-span-2"
            data-reveal="left"
        >
            <a
                href="{{ route('marketplace.home') }}"
                class="footer-brand-lockup"
                aria-label="Home Pharma home"
            >
                <img
                    src="{{ asset('images/branding/home-pharma-mark.png') }}"
                    alt=""
                    class="footer-brand-mark"
                >

                <span>
                    <strong>Home Pharma</strong>
                    <small>Care, compared clearly</small>
                </span>
            </a>

            <p class="mt-5 max-w-xl text-slate-300">
                Browse verified pharmacy availability,
                compare offer-specific prices and reserve
                medicines with the right prescription safeguards.
            </p>
        </div>

        <div
            data-reveal
            data-delay="100"
        >
            <h3>Marketplace</h3>

            <a href="{{ route('marketplace.catalogue.index') }}">
                Browse medicines
            </a>

            @auth
                @if($isClient)
                    <a href="{{ route('client.orders.index') }}">
                        My orders
                    </a>

                    <a href="{{ route('client.wallet.index') }}">
                        My wallet
                    </a>

                    <a href="{{ route('client.dashboard') }}">
                        My account
                    </a>
                @else
                    <a href="{{ url('/pharmacy') }}">
                        Pharmacy portal
                    </a>
                @endif
            @else
                <a href="{{ route('client.login') }}">
                    Client sign in
                </a>

                <a href="{{ route('client.register') }}">
                    Create account
                </a>
            @endauth
        </div>

        <div
            data-reveal="right"
            data-delay="190"
        >
            <h3>Important</h3>

            <p>
                Prescription medicines require pharmacy review.
            </p>

            <p>
                Availability is based on non-expired branch stock.
            </p>

            <p>
                Prices and fulfilment options may vary by pharmacy.
            </p>
        </div>
    </div>

    <div
        class="border-t border-white/10 py-5 text-center text-sm text-slate-400"
    >
        © {{ date('Y') }} Home Pharma.
        Marketplace prices and fulfilment vary by pharmacy.
    </div>


    
</footer>

</body>
</html>