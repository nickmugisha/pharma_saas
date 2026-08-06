<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;


class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
   public function boot(): void
{
  Event::listen(
    Login::class,
    function (Login $event): void {
        $context = match (true) {
            request()->is('super-admin')
                || request()->is('super-admin/*')
                    => 'super-admin',

            request()->is('pharmacy')
                || request()->is('pharmacy/*')
                    => 'pharmacy',

            default => 'client',
        };

        session()->flash(
            'show_login_loader',
            true,
        );

        session()->flash(
            'login_loader_context',
            $context,
        );
    },
);
}
}
