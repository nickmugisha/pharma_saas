<?php

use App\Http\Controllers\Marketplace\ClientAddressController;
use App\Http\Controllers\Marketplace\ClientAuthController;
use App\Http\Controllers\Marketplace\ClientDashboardController;
use App\Http\Controllers\Marketplace\ClientEmailVerificationController;
use App\Http\Controllers\Marketplace\ClientOrderController;
use App\Http\Controllers\Marketplace\ClientPrescriptionController;
use App\Http\Controllers\Marketplace\ClientWalletController;
use App\Http\Controllers\Marketplace\MarketplaceCartController;
use App\Http\Controllers\Marketplace\MarketplaceCheckoutController;
use App\Http\Controllers\Marketplace\StorefrontController;
use App\Http\Controllers\Pharmacy\DownloadPrescriptionAttachmentController;
use Illuminate\Support\Facades\Route;

Route::get('/', [StorefrontController::class, 'home'])
    ->name('marketplace.home');

Route::get('/shop', [StorefrontController::class, 'index'])
    ->name('marketplace.catalogue.index');

Route::get('/shop/{medicine:slug}', [StorefrontController::class, 'show'])
    ->name('marketplace.catalogue.show');

Route::get('/login', fn () => redirect()->route('client.login'))
    ->name('login');

Route::middleware('guest')->group(function (): void {
    Route::get('/client/login', [ClientAuthController::class, 'showLogin'])
        ->name('client.login');
    Route::post('/client/login', [ClientAuthController::class, 'login'])
        ->name('client.login.store');
    Route::get('/client/register', [ClientAuthController::class, 'showRegister'])
        ->name('client.register');
    Route::post('/client/register', [ClientAuthController::class, 'register'])
        ->name('client.register.store');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/client/logout', [ClientAuthController::class, 'logout'])
        ->name('client.logout');

    Route::get('/client/verify-email', [ClientEmailVerificationController::class, 'notice'])
        ->name('verification.notice');
    Route::get('/client/verify-email/{id}/{hash}', [ClientEmailVerificationController::class, 'verify'])
        ->middleware('signed')
        ->name('verification.verify');
    Route::post('/client/email/verification-notification', [ClientEmailVerificationController::class, 'resend'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::get(
        '/client/prescriptions/{prescription}/download',
        [ClientPrescriptionController::class, 'download'],
    )->name('client.prescriptions.download');

    Route::get(
        '/pharmacy/prescription-attachments/{attachment}/download',
        DownloadPrescriptionAttachmentController::class,
    )->name('pharmacy.prescription-attachments.download');
});

Route::middleware(['auth', 'verified'])->prefix('client')->group(function (): void {
    Route::get('/dashboard', [ClientDashboardController::class, 'index'])
        ->name('client.dashboard');
    Route::patch('/profile', [ClientDashboardController::class, 'update'])
        ->name('client.profile.update');

    Route::post('/addresses', [ClientAddressController::class, 'store'])
        ->name('client.addresses.store');
    Route::patch('/addresses/{address}', [ClientAddressController::class, 'update'])
        ->name('client.addresses.update');
    Route::delete('/addresses/{address}', [ClientAddressController::class, 'destroy'])
        ->name('client.addresses.destroy');

    Route::post('/prescriptions', [ClientPrescriptionController::class, 'store'])
        ->name('client.prescriptions.store');

    Route::get('/wallet', [ClientWalletController::class, 'index'])
        ->name('client.wallet.index');
    Route::post('/wallet/funding-requests', [ClientWalletController::class, 'requestFunding'])
        ->name('client.wallet.funding.store');

    Route::get('/cart', [MarketplaceCartController::class, 'index'])
        ->name('marketplace.cart.index');
    Route::post('/cart', [MarketplaceCartController::class, 'store'])
        ->name('marketplace.cart.store');
    Route::patch('/cart/{item}', [MarketplaceCartController::class, 'update'])
        ->name('marketplace.cart.update');
    Route::delete('/cart/{item}', [MarketplaceCartController::class, 'destroy'])
        ->name('marketplace.cart.destroy');

    Route::get('/checkout', [MarketplaceCheckoutController::class, 'show'])
        ->name('marketplace.checkout.show');
    Route::post('/checkout', [MarketplaceCheckoutController::class, 'store'])
        ->name('marketplace.checkout.store');

    Route::get('/orders', [ClientOrderController::class, 'index'])
        ->name('client.orders.index');
    Route::get('/orders/{order}', [ClientOrderController::class, 'show'])
        ->name('client.orders.show');
    Route::post('/orders/{order}/cancel', [ClientOrderController::class, 'cancel'])
        ->name('client.orders.cancel');
    Route::post('/orders/{order}/pay', [ClientWalletController::class, 'payOrder'])
        ->name('client.orders.pay');
});
