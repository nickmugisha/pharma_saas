<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Sale extends Model
{
    use HasFactory;

    protected $fillable = [
        'pharmacy_id',
        'pharmacy_branch_id',
        'cashier_user_id',
        'voided_by_user_id',
        'sale_number',
        'receipt_number',
        'channel',
        'sold_at',
        'completed_at',
        'voided_at',
        'status',
        'payment_status',
        'currency',
        'subtotal',
        'discount_total',
        'tax_total',
        'grand_total',
        'paid_amount',
        'change_amount',
        'customer_name',
        'customer_phone',
        'source_type',
        'source_id',
        'notes',
        'void_reason',
    ];

    protected $attributes = [
        'channel' => 'pos',
        'status' => 'draft',
        'payment_status' => 'unpaid',
        'currency' => 'BIF',
        'subtotal' => 0,
        'discount_total' => 0,
        'tax_total' => 0,
        'grand_total' => 0,
        'paid_amount' => 0,
        'change_amount' => 0,
    ];

    protected function casts(): array
    {
        return [
            'sold_at' => 'datetime',
            'completed_at' => 'datetime',
            'voided_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'change_amount' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Sale $sale): void {
            $sale->uuid ??= (string) Str::uuid();

            $sale->sale_number ??= sprintf(
                'POS-%s-%s',
                now()->format('Ymd'),
                Str::upper(Str::random(6)),
            );

            $sale->sold_at ??= now();
        });
    }

    public function pharmacy(): BelongsTo
    {
        return $this->belongsTo(Pharmacy::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(
            PharmacyBranch::class,
            'pharmacy_branch_id',
        );
    }

    public function voidRecord(): HasOne
{
    return $this->hasOne(SaleVoid::class);
}

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'cashier_user_id',
        );
    }

    public function voidedByUser(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'voided_by_user_id',
        );
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SalePayment::class);
    }
}