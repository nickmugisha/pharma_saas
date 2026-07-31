<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class PurchaseReceipt extends Model
{
    use HasFactory;

    protected $fillable = [
        'pharmacy_id',
        'pharmacy_branch_id',
        'purchase_order_id',
        'supplier_id',
        'received_by_user_id',
        'receipt_number',
        'received_at',
        'status',
        'notes',
    ];

    protected $attributes = [
        'status' => 'draft',
    ];

    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (PurchaseReceipt $receipt): void {
            $receipt->uuid ??= (string) Str::uuid();

            $receipt->receipt_number ??= sprintf(
                'REC-%s-%s',
                now()->format('Ymd'),
                Str::upper(Str::random(6)),
            );
        });
    }

    public function pharmacy(): BelongsTo
    {
        return $this->belongsTo(Pharmacy::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(
            PharmacyBranch::class,
            'pharmacy_branch_id',
        );
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function receivedByUser(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'received_by_user_id',
        );
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseReceiptItem::class);
    }
}