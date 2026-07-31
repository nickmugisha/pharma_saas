<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'pharmacy_id',
        'created_by_user_id',
        'name',
        'contact_person',
        'email',
        'phone',
        'alternate_phone',
        'registration_number',
        'tax_number',
        'address',
        'city',
        'province',
        'country',
        'payment_terms_days',
        'credit_limit',
        'currency',
        'status',
        'notes',
    ];

    protected $attributes = [
        'country' => 'Burundi',
        'payment_terms_days' => 0,
        'currency' => 'BIF',
        'status' => 'active',
    ];

    protected function casts(): array
    {
        return [
            'payment_terms_days' => 'integer',
            'credit_limit' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Supplier $supplier): void {
            $supplier->uuid ??= (string) Str::uuid();
        });
    }

    public function purchaseOrders(): HasMany
{
    return $this->hasMany(PurchaseOrder::class);
}

public function invoices(): HasMany
{
    return $this->hasMany(SupplierInvoice::class);
}

public function payments(): HasMany
{
    return $this->hasMany(SupplierPayment::class);
}

    public function pharmacy(): BelongsTo
    {
        return $this->belongsTo(Pharmacy::class);
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by_user_id',
        );
    }
}