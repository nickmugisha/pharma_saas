<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class PurchaseOrder extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'pharmacy_id',
        'pharmacy_branch_id',
        'supplier_id',
        'created_by_user_id',
        'approved_by_user_id',
        'order_number',
        'order_date',
        'expected_delivery_date',
        'currency',
        'subtotal',
        'discount_total',
        'tax_total',
        'shipping_total',
        'grand_total',
        'status',
        'notes',
        'submitted_at',
        'approved_at',
        'cancelled_at',
    ];

    protected $attributes = [
        'currency' => 'BIF',
        'subtotal' => 0,
        'discount_total' => 0,
        'tax_total' => 0,
        'shipping_total' => 0,
        'grand_total' => 0,
        'status' => 'draft',
    ];

    protected function casts(): array
    {
        return [
            'order_date' => 'date',
            'expected_delivery_date' => 'date',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'shipping_total' => 'decimal:2',
            'grand_total' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (PurchaseOrder $order): void {
            $order->uuid ??= (string) Str::uuid();
            $order->order_date ??= today();

            $order->order_number ??= sprintf(
                'PO-%s-%s',
                now()->format('Ymd'),
                Str::upper(Str::random(6)),
            );
        });

        static::saving(function (PurchaseOrder $order): void {
            if (! $order->isDirty('status')) {
                return;
            }

            if ($order->status === 'submitted') {
                $order->submitted_at ??= now();
            }

            if ($order->status === 'approved') {
                $order->approved_at ??= now();
            }

            if ($order->status === 'cancelled') {
                $order->cancelled_at ??= now();
            }
        });
    }

    public function recalculateTotals(): void
    {
        $items = $this->items()->get();

        $subtotal = $items->sum(
            fn (PurchaseOrderItem $item): float =>
                (float) $item->quantity_ordered
                * (float) $item->unit_cost,
        );

        $discount = $items->sum(
            fn (PurchaseOrderItem $item): float =>
                (float) $item->discount_amount,
        );

        $tax = $items->sum(function (PurchaseOrderItem $item): float {
            $beforeTax = max(
                ((float) $item->quantity_ordered
                    * (float) $item->unit_cost)
                    - (float) $item->discount_amount,
                0,
            );

            return $beforeTax * ((float) $item->tax_rate / 100);
        });

        $shipping = (float) $this->shipping_total;

        $this->forceFill([
            'subtotal' => round($subtotal, 2),
            'discount_total' => round($discount, 2),
            'tax_total' => round($tax, 2),
            'grand_total' => round(
                $subtotal - $discount + $tax + $shipping,
                2,
            ),
        ])->saveQuietly();
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

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'approved_by_user_id',
        );
    }

    public function supplierInvoices(): HasMany
{
    return $this->hasMany(SupplierInvoice::class);
}

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }
}