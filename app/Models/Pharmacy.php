<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pharmacy extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'legal_name',
        'registration_number',
        'license_number',
        'tax_number',
        'email',
        'phone',
        'alternate_phone',
        'address',
        'city',
        'province',
        'country',
        'status',
        'suspension_reason',
        'notes',
    ];

    protected $attributes = [
        'status' => 'pending',
        'country' => 'Burundi',
    ];

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
            'suspended_at' => 'datetime',
        ];
    }

    public function medicineListings(): HasMany
{
    return $this->hasMany(PharmacyMedicine::class);
}

public function supplierInvoices(): HasMany
{
    return $this->hasMany(SupplierInvoice::class);
}

public function supplierPayments(): HasMany
{
    return $this->hasMany(SupplierPayment::class);
}

public function purchaseOrders(): HasMany
{
    return $this->hasMany(PurchaseOrder::class);
}
public function inventoryAlerts(): HasMany
{
    return $this->hasMany(InventoryAlert::class);
}


public function sales(): HasMany
{
    return $this->hasMany(Sale::class);
}

public function salePayments(): HasMany
{
    return $this->hasMany(SalePayment::class);
}

public function customers(): HasMany
{
    return $this->hasMany(Customer::class);
}

public function customerActivities(): HasMany
{
    return $this->hasMany(CustomerActivity::class);
}


public function suppliers(): HasMany
{
    return $this->hasMany(Supplier::class);
}

    protected static function booted(): void
    {
        static::creating(function (Pharmacy $pharmacy): void {
            $pharmacy->uuid ??= (string) Str::uuid();
        });

        static::saving(function (Pharmacy $pharmacy): void {
            if (! $pharmacy->isDirty('status')) {
                return;
            }

            if ($pharmacy->status === 'approved') {
                $pharmacy->approved_at ??= now();
                $pharmacy->suspended_at = null;
                $pharmacy->suspension_reason = null;
            }

            if ($pharmacy->status === 'suspended') {
                $pharmacy->suspended_at ??= now();
            }
        });
    }
}