<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseReceiptItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_receipt_id',
        'purchase_order_item_id',
        'pharmacy_medicine_id',
        'medicine_batch_id',
        'batch_number',
        'manufacturing_date',
        'expiry_date',
        'quantity_received',
        'unit_cost',
        'line_cost',
        'notes',
    ];

    protected $attributes = [
        'line_cost' => 0,
    ];

    protected function casts(): array
    {
        return [
            'manufacturing_date' => 'date',
            'expiry_date' => 'date',
            'quantity_received' => 'decimal:3',
            'unit_cost' => 'decimal:2',
            'line_cost' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (PurchaseReceiptItem $item): void {
            $item->line_cost = round(
                (float) $item->quantity_received
                * (float) $item->unit_cost,
                2,
            );
        });
    }

    public function purchaseReceipt(): BelongsTo
    {
        return $this->belongsTo(PurchaseReceipt::class);
    }

    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }

    public function pharmacyMedicine(): BelongsTo
    {
        return $this->belongsTo(PharmacyMedicine::class);
    }

    public function medicineBatch(): BelongsTo
    {
        return $this->belongsTo(MedicineBatch::class);
    }
}