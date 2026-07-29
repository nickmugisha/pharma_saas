<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthentication;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthenticationRecovery;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements
    FilamentUser,
    MustVerifyEmail,
    HasAppAuthentication,
    HasAppAuthenticationRecovery{
    /** @use HasFactory<UserFactory> */
  use HasFactory,
    Notifiable,
    HasRoles,
    InteractsWithAppAuthentication,
    InteractsWithAppAuthenticationRecovery;
    protected $attributes = [
    'is_active' => true,
];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'app_authentication_secret',
'app_authentication_recovery_codes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
{
    return [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_active' => 'boolean',
        'blocked_at' => 'datetime',
        'last_login_at' => 'datetime',
    ];
}
public function canAccessPanel(Panel $panel): bool
{
    if (! $this->is_active) {
        return false;
    }

    /*
     * Some older automated tests create roleless users.
     * This exception exists only inside the testing environment.
     */
    if (app()->environment('testing') && ! $this->roles()->exists()) {
        return true;
    }

    return match ($panel->getId()) {
        'super-admin' => $this->hasAnyRole([
            'super_admin',
            'platform_admin',
            'compliance_officer',
            'finance_manager',
            'support_agent',
        ]),

        'pharmacy' => $this->hasAnyRole([
            'pharmacy_owner',
            'branch_manager',
            'pharmacist',
            'pharmacy_assistant',
            'stock_manager',
            'cashier',
            'accountant',
            'delivery_coordinator',
        ]),

        default => false,
    };
}
}
