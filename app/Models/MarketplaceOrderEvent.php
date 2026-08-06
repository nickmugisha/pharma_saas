<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

class MarketplaceOrderEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'marketplace_order_id',
        'actor_user_id',
        'event_type',
        'title',
        'description',
        'metadata',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (MarketplaceOrderEvent $event): void {
            $event->uuid ??= (string) Str::uuid();
            $event->occurred_at ??= now();
        });

        static::updating(function (): never {
            throw new LogicException('Marketplace order events are immutable.');
        });

        static::deleting(function (): never {
            throw new LogicException('Marketplace order events cannot be deleted.');
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(MarketplaceOrder::class, 'marketplace_order_id');
    }

    public function actorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
