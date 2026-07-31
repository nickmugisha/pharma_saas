<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class SupplierPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'pharmacy_id',
        'supplier_invoice_id',
        'supplier_id',
        'created_by_user_id',
        'voided_by_user_id',
        'payment_number',
        'payment_date',
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
            'payment_date' => 'date',
            'amount' => 'decimal:2',
            'voided_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (SupplierPayment $payment): void {
            $payment->uuid ??= (string) Str::uuid();
            $payment->payment_date ??= today();

            $payment->payment_number ??= sprintf(
                'PAY-%s-%s',
                now()->format('Ymd'),
                Str::upper(Str::random(6)),
            );
        });

        static::saved(function (SupplierPayment $payment): void {
            $payment->invoice?->recalculatePayments();
        });

        static::deleted(function (SupplierPayment $payment): void {
            $payment->invoice?->recalculatePayments();
        });
    }

    public function pharmacy(): BelongsTo
    {
        return $this->belongsTo(Pharmacy::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(
            SupplierInvoice::class,
            'supplier_invoice_id',
        );
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by_user_id',
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