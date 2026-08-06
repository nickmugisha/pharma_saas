<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

class MarketplaceStockReservation extends Model
{
    use HasFactory;

    public const STATUS_HELD = 'held';
    public const STATUS_RELEASED = 'released';
    public const STATUS_CONVERTED = 'converted';

    protected $fillable = [
        'marketplace_order_id',
        'marketplace_order_item_id',
        'pharmacy_id',
        'pharmacy_branch_id',
        'pharmacy_medicine_id',
        'medicine_batch_id',
        'quantity',
        'status',
        'expires_at',
        'released_at',
        'release_reason',
    ];

    protected $attributes = ['status' => self::STATUS_HELD];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'expires_at' => 'datetime',
            'released_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (MarketplaceStockReservation $reservation): void {
            $reservation->uuid ??= (string) Str::uuid();
        });

        static::deleting(function (): never {
            throw new LogicException('Marketplace stock reservations cannot be deleted.');
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(MarketplaceOrder::class, 'marketplace_order_id');
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(MarketplaceOrderItem::class, 'marketplace_order_item_id');
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

    public function medicineBatch(): BelongsTo
    {
        return $this->belongsTo(MedicineBatch::class);
    }
}
