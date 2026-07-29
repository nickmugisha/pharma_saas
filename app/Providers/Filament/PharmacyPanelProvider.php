<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class PharmacyPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('pharmacy')
            ->path('pharmacy')
            ->login()
           ->brandName('Pharma SaaS — Pharmacy')
           ->brandLogo(asset('images/pharma-saas-pharmacy.svg'))
->brandLogoHeight('2.75rem')
->favicon(asset('images/pharma-saas-favicon.svg'))
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
            ->discoverResources(in: app_path('Filament/Pharmacy/Resources'), for: 'App\Filament\Pharmacy\Resources')
            ->discoverPages(in: app_path('Filament/Pharmacy/Pages'), for: 'App\Filament\Pharmacy\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Pharmacy/Widgets'), for: 'App\Filament\Pharmacy\Widgets')
           ->widgets([
    AccountWidget::class,
])
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
            ]);
    }
}
