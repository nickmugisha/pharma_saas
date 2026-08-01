<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleItemBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'sale_item_id',
        'medicine_batch_id',
        'quantity',
        'unit_cost',
        'line_cost',
    ];

    protected $attributes = [
        'line_cost' => 0,
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_cost' => 'decimal:2',
            'line_cost' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (SaleItemBatch $allocation): void {
            $allocation->line_cost = round(
                (float) $allocation->quantity
                * (float) $allocation->unit_cost,
                2,
            );
        });
    }

    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(SaleItem::class);
    }

    public function medicineBatch(): BelongsTo
    {
        return $this->belongsTo(MedicineBatch::class);
    }
}