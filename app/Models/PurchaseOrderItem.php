<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_order_id',
        'pharmacy_medicine_id',
        'quantity_ordered',
        'quantity_received',
        'unit_cost',
        'discount_amount',
        'tax_rate',
        'line_total',
        'notes',
    ];

    protected $attributes = [
        'quantity_received' => 0,
        'discount_amount' => 0,
        'tax_rate' => 0,
        'line_total' => 0,
    ];

    protected function casts(): array
    {
        return [
            'quantity_ordered' => 'decimal:3',
            'quantity_received' => 'decimal:3',
            'unit_cost' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (PurchaseOrderItem $item): void {
            $base = (float) $item->quantity_ordered
                * (float) $item->unit_cost;

            $taxable = max(
                $base - (float) $item->discount_amount,
                0,
            );

            $tax = $taxable * ((float) $item->tax_rate / 100);

            $item->line_total = round(
                $taxable + $tax,
                2,
            );
        });

        static::saved(function (PurchaseOrderItem $item): void {
            $item->purchaseOrder?->recalculateTotals();
        });

        static::deleted(function (PurchaseOrderItem $item): void {
            $item->purchaseOrder?->recalculateTotals();
        });
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function pharmacyMedicine(): BelongsTo
    {
        return $this->belongsTo(PharmacyMedicine::class);
    }
}