<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ClientAddress extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'label',
        'recipient_name',
        'phone',
        'address_line_1',
        'address_line_2',
        'city',
        'province',
        'country',
        'delivery_instructions',
        'is_default',
    ];

    protected $attributes = [
        'label' => 'Home',
        'city' => 'Bujumbura',
        'country' => 'Burundi',
        'is_default' => false,
    ];

    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::creating(function (ClientAddress $address): void {
            $address->uuid ??= (string) Str::uuid();
        });

        static::saving(function (ClientAddress $address): void {
            if (! $address->is_default || ! $address->user_id) {
                return;
            }

            $query = static::query()
                ->where('user_id', $address->user_id)
                ->where('is_default', true);

            if ($address->exists) {
                $query->whereKeyNot($address->getKey());
            }

            $query->update(['is_default' => false]);
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
