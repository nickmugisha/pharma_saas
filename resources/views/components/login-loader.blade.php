@php
    $loaderContext = session(
        'login_loader_context',
        'client',
    );

    $loaderConfiguration = match ($loaderContext) {
        'super-admin' => [
            'logo' => asset(
                'images/branding/pharma-saas-logo.png'
            ),
            'alt' => 'Home Pharma SaaS',
            'message' => 'Opening the platform command center',
            'accent' => '#4f46e5',
        ],

        'pharmacy' => [
            'logo' => asset(
                'images/branding/pharma-saas-logo.png'
            ),
            'alt' => 'Home Pharma SaaS',
            'message' => 'Preparing your pharmacy workspace',
            'accent' => '#059669',
        ],

        default => [
            'logo' => asset(
                'images/branding/home-pharma-logo.png'
            ),
            'alt' => 'Home Pharma',
            'message' => 'Opening your Home Pharma account',
            'accent' => '#059669',
        ],
    };
@endphp

@if(session('show_login_loader'))
    <div
        id="successful-login-loader"
        class="successful-login-loader"
        style="
            --login-loader-accent:
                {{ $loaderConfiguration['accent'] }};
        "
        role="status"
        aria-live="polite"
        aria-label="Signing in successfully"
    >
        <div class="successful-login-loader__content">
            <img
                src="{{ $loaderConfiguration['logo'] }}"
                alt="{{ $loaderConfiguration['alt'] }}"
                class="successful-login-loader__logo"
            >

            <div
                class="successful-login-loader__track"
                aria-hidden="true"
            >
                <span></span>
            </div>

            <p>
                {{ $loaderConfiguration['message'] }}
            </p>
        </div>
    </div>

    <script>
        (() => {
            const loader = document.getElementById(
                'successful-login-loader',
            );

            if (!loader) {
                return;
            }

            document.documentElement.classList.add(
                'login-loader-active',
            );

            window.setTimeout(() => {
                loader.classList.add('is-leaving');
            }, 2600);

            window.setTimeout(() => {
                loader.remove();

                document.documentElement.classList.remove(
                    'login-loader-active',
                );
            }, 3000);
        })();
    </script>
@endif