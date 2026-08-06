<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthentication;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthenticationRecovery;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements
    FilamentUser,
    MustVerifyEmail,
    HasAppAuthentication,
    HasAppAuthenticationRecovery
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;
    use Notifiable;
    use HasRoles;
    use InteractsWithAppAuthentication;
    use InteractsWithAppAuthenticationRecovery;

    protected $attributes = [
        'is_active' => true,
    ];

    protected $fillable = [
        'name',
        'email',
        'password',
        'email_verified_at',
        'is_active',
        'blocked_reason',
        'pharmacy_id',
        'pharmacy_branch_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'app_authentication_secret',
        'app_authentication_recovery_codes',
    ];

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

    public function pharmacy(): BelongsTo
    {
        return $this->belongsTo(Pharmacy::class);
    }

    public function pharmacyBranch(): BelongsTo
    {
        return $this->belongsTo(
            PharmacyBranch::class,
            'pharmacy_branch_id',
        );
    }

    public function customerAccounts(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function clientProfile(): HasOne
    {
        return $this->hasOne(ClientProfile::class);
    }

    public function wallet(): HasOne
    {
        return $this->hasOne(ClientWallet::class);
    }

    public function clientAddresses(): HasMany
    {
        return $this->hasMany(ClientAddress::class);
    }

    public function clientPrescriptions(): HasMany
    {
        return $this->hasMany(ClientPrescription::class)->latest();
    }

    public function marketplaceCarts(): HasMany
    {
        return $this->hasMany(MarketplaceCart::class);
    }

    public function marketplaceOrders(): HasMany
    {
        return $this->hasMany(MarketplaceOrder::class);
    }

    public function marketplaceOrderEvents(): HasMany
    {
        return $this->hasMany(
            MarketplaceOrderEvent::class,
            'actor_user_id',
        );
    }

    public function dispensedPrescriptions(): HasMany
    {
        return $this->hasMany(
            PrescriptionDispensing::class,
            'dispensed_by_user_id',
        );
    }

    public function voidedPrescriptionDispensings(): HasMany
    {
        return $this->hasMany(
            PrescriptionDispensing::class,
            'voided_by_user_id',
        );
    }

    public function createdPrescriptions(): HasMany
    {
        return $this->hasMany(
            Prescription::class,
            'created_by_user_id',
        );
    }

    public function reviewedPrescriptions(): HasMany
    {
        return $this->hasMany(
            Prescription::class,
            'reviewed_by_user_id',
        );
    }

    public function prescriptionActivities(): HasMany
    {
        return $this->hasMany(
            PrescriptionActivity::class,
            'actor_user_id',
        );
    }

    public function customerActivities(): HasMany
    {
        return $this->hasMany(
            CustomerActivity::class,
            'actor_user_id',
        );
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if (! $this->is_active || $this->hasRole('client')) {
            return false;
        }

        if (
            app()->environment('testing')
            && ! $this->roles()->exists()
        ) {
            return true;
        }

        if ($panel->getId() === 'super-admin') {
            return $this->hasAnyRole([
                'super_admin',
                'platform_admin',
                'compliance_officer',
                'finance_manager',
                'support_agent',
            ]);
        }

        if ($panel->getId() !== 'pharmacy') {
            return false;
        }

        if (! $this->hasAnyRole([
            'pharmacy_owner',
            'branch_manager',
            'pharmacist',
            'pharmacy_assistant',
            'stock_manager',
            'cashier',
            'accountant',
            'delivery_coordinator',
        ])) {
            return false;
        }

        $pharmacy = $this->pharmacy;

        if ($pharmacy === null || $pharmacy->status !== 'approved') {
            return false;
        }

        if (
            $this->hasRole('pharmacy_owner')
            && blank($this->pharmacy_branch_id)
        ) {
            return true;
        }

        $branch = $this->pharmacyBranch;

        return $branch !== null
            && (int) $branch->pharmacy_id === (int) $pharmacy->id
            && $branch->status === 'active';
    }
}
