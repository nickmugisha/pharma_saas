<?php

namespace App\Providers\Filament;

use App\Filament\Pharmacy\Pages\Dashboard as PharmacyDashboard;
use App\Http\Middleware\EnsurePharmacySetupComplete;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Filament\View\PanelsRenderHook;

class PharmacyPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('pharmacy')
            ->path('pharmacy')
            ->login()
            ->passwordReset()
            ->emailVerification()
            ->emailChangeVerification()
            ->multiFactorAuthentication([
                AppAuthentication::make()
                    ->recoverable()
                    ->brandName('Home Pharma SaaS — Pharmacy')
            ])
            ->profile(isSimple: false)
            ->revealablePasswords(false)
           ->brandName('Home Pharma SaaS — Pharmacy')
->brandLogo(
    asset('images/branding/pharma-saas-logo.png')
)
->brandLogoHeight('4.25rem')
->favicon(
    asset('images/branding/favicon.png')
)

->renderHook(
    PanelsRenderHook::BODY_START,
    fn (): string =>
        view('components.login-loader')->render(),
)
            ->colors([
                'primary' => Color::Emerald,
            ])
            ->navigationGroups([
                'Stock & Purchases',
                'Sales',
                'Prescriptions',
                'Patients',
                'Deliveries',
                'Finance',
                'Reports',
                'Pharmacy Settings',
            ])
            ->discoverResources(
                in: app_path('Filament/Pharmacy/Resources'),
                for: 'App\Filament\Pharmacy\Resources',
            )
            ->discoverPages(
                in: app_path('Filament/Pharmacy/Pages'),
                for: 'App\Filament\Pharmacy\Pages',
            )
            ->pages([
                PharmacyDashboard::class,
            ])
            ->discoverWidgets(
                in: app_path('Filament/Pharmacy/Widgets'),
                for: 'App\Filament\Pharmacy\Widgets',
            )
            ->widgets([])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                EnsurePharmacySetupComplete::class,
            ]);
    }
}
