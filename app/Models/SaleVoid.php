<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class SaleVoid extends Model
{
    use HasFactory;

    protected $fillable = [
        'pharmacy_id',
        'sale_id',
        'voided_by_user_id',
        'void_number',
        'reason',
        'restored_quantity',
        'reversed_payment_amount',
        'voided_at',
    ];

    protected $attributes = [
        'restored_quantity' => 0,
        'reversed_payment_amount' => 0,
    ];

    protected function casts(): array
    {
        return [
            'restored_quantity' => 'decimal:3',
            'reversed_payment_amount' => 'decimal:2',
            'voided_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (SaleVoid $void): void {
            $void->uuid ??= (string) Str::uuid();

            $reference = strtoupper(
                substr(
                    str_replace('-', '', $void->uuid),
                    0,
                    8,
                ),
            );

            $void->void_number ??= sprintf(
                'VOID-%s-%s',
                now()->format('Ymd'),
                $reference,
            );

            $void->voided_at ??= now();
        });
    }

    public function pharmacy(): BelongsTo
    {
        return $this->belongsTo(Pharmacy::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function voidedByUser(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'voided_by_user_id',
        );
    }
} 