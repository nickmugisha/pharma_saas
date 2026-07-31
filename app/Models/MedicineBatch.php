<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class MedicineBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'pharmacy_id',
        'pharmacy_branch_id',
        'pharmacy_medicine_id',
        'supplier_id',
        'purchase_order_item_id',
        'batch_number',
        'manufacturing_date',
        'expiry_date',
        'unit_cost',
        'quantity_received',
        'quantity_available',
        'status',
        'received_at',
        'notes',
    ];

    protected $attributes = [
        'quantity_received' => 0,
        'quantity_available' => 0,
        'status' => 'active',
    ];

    protected function casts(): array
    {
        return [
            'manufacturing_date' => 'date',
            'expiry_date' => 'date',
            'unit_cost' => 'decimal:2',
            'quantity_received' => 'decimal:3',
            'quantity_available' => 'decimal:3',
            'received_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (MedicineBatch $batch): void {
            $batch->uuid ??= (string) Str::uuid();
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

    public function pharmacyMedicine(): BelongsTo
    {
        return $this->belongsTo(PharmacyMedicine::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }

    public function receiptItems(): HasMany
    {
        return $this->hasMany(PurchaseReceiptItem::class);
    }
    public function inventoryAlerts(): HasMany
{
    return $this->hasMany(InventoryAlert::class);
}

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }
}