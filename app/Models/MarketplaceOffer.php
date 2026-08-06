<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class MarketplaceOffer extends Model
{
    use HasFactory;

    protected $fillable = [
        'pharmacy_id',
        'pharmacy_branch_id',
        'pharmacy_medicine_id',
        'online_price',
        'currency',
        'pickup_enabled',
        'delivery_enabled',
        'delivery_fee',
        'max_order_quantity',
        'preparation_minutes',
        'marketplace_description',
        'status',
    ];

    protected $attributes = [
        'currency' => 'BIF',
        'pickup_enabled' => true,
        'delivery_enabled' => false,
        'delivery_fee' => 0,
        'preparation_minutes' => 30,
        'status' => 'active',
    ];

    protected function casts(): array
    {
        return [
            'online_price' => 'decimal:2',
            'pickup_enabled' => 'boolean',
            'delivery_enabled' => 'boolean',
            'delivery_fee' => 'decimal:2',
            'max_order_quantity' => 'decimal:3',
            'preparation_minutes' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (MarketplaceOffer $offer): void {
            $offer->uuid ??= (string) Str::uuid();
        });
    }

    public function pharmacy(): BelongsTo
    {
        return $this->belongsTo(Pharmacy::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(PharmacyBranch::class, 'pharmacy_branch_id');
    }

    public function pharmacyMedicine(): BelongsTo
    {
        return $this->belongsTo(PharmacyMedicine::class);
    }
}
