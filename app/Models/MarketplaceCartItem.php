<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class MarketplaceCartItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'marketplace_cart_id',
        'pharmacy_id',
        'pharmacy_branch_id',
        'pharmacy_medicine_id',
        'marketplace_offer_id',
        'quantity',
        'unit_price_snapshot',
        'currency',
        'fulfillment_method',
        'online_sale_mode',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_price_snapshot' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (MarketplaceCartItem $item): void {
            $item->uuid ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(MarketplaceCart::class, 'marketplace_cart_id');
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

    public function offer(): BelongsTo
    {
        return $this->belongsTo(MarketplaceOffer::class, 'marketplace_offer_id');
    }
}
