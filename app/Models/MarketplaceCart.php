<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class MarketplaceCart extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'status', 'converted_at'];

    protected $attributes = ['status' => 'active'];

    protected function casts(): array
    {
        return ['converted_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (MarketplaceCart $cart): void {
            $cart->uuid ??= (string) Str::uuid();
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(MarketplaceCartItem::class);
    }
}
