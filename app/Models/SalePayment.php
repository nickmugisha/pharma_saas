<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class SalePayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'pharmacy_id',
        'sale_id',
        'received_by_user_id',
        'voided_by_user_id',
        'payment_number',
        'paid_at',
        'amount',
        'payment_method',
        'reference',
        'status',
        'voided_at',
        'void_reason',
        'notes',
    ];

    protected $attributes = [
        'status' => 'completed',
    ];

    protected function casts(): array
    {
        return [
            'paid_at' => 'datetime',
            'amount' => 'decimal:2',
            'voided_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (SalePayment $payment): void {
            $payment->uuid ??= (string) Str::uuid();

            $payment->payment_number ??= sprintf(
                'SPAY-%s-%s',
                now()->format('Ymd'),
                Str::upper(Str::random(6)),
            );

            $payment->paid_at ??= now();
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

    public function receivedByUser(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'received_by_user_id',
        );
    }

    public function voidedByUser(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'voided_by_user_id',
        );
    }
}