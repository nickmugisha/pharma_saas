<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

class PrescriptionDispensingItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'prescription_dispensing_id',
        'prescription_item_id',
        'sale_item_id',
        'quantity_dispensed',
    ];

    protected function casts(): array
    {
        return [
            'quantity_dispensed' => 'decimal:3',
        ];
    }

    protected static function booted(): void
    {
        static::creating(
            function (
                PrescriptionDispensingItem $item,
            ): void {
                $item->uuid ??= (string) Str::uuid();
            },
        );

        static::updating(function (): never {
            throw new LogicException(
                'Prescription dispensing items are immutable.',
            );
        });

        static::deleting(function (): never {
            throw new LogicException(
                'Prescription dispensing items cannot be deleted.',
            );
        });
    }

    public function dispensing(): BelongsTo
    {
        return $this->belongsTo(
            PrescriptionDispensing::class,
            'prescription_dispensing_id',
        );
    }

    public function prescriptionItem(): BelongsTo
    {
        return $this->belongsTo(
            PrescriptionItem::class,
        );
    }

    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(SaleItem::class);
    }
}