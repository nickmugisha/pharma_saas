<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class PharmacyMedicine extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'pharmacy_id',
        'medicine_id',
        'created_by_user_id',
        'internal_sku',
        'selling_price',
        'online_price',
        'currency',
        'pharmacy_description',
        'is_available',
        'is_visible_online',
        'status',
    ];

    protected $attributes = [
        'currency' => 'BIF',
        'is_available' => true,
        'is_visible_online' => false,
        'status' => 'active',
    ];

    protected function casts(): array
    {
        return [
            'selling_price' => 'decimal:2',
            'online_price' => 'decimal:2',
            'is_available' => 'boolean',
            'is_visible_online' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (PharmacyMedicine $listing): void {
            $listing->uuid ??= (string) Str::uuid();
        });
    }

    public function pharmacy(): BelongsTo
    {
        return $this->belongsTo(Pharmacy::class);
    }

    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class);
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by_user_id',
        );
    }
}