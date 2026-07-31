<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

class StockMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'pharmacy_id',
        'pharmacy_branch_id',
        'pharmacy_medicine_id',
        'medicine_batch_id',
        'created_by_user_id',
        'movement_type',
        'direction',
        'quantity',
        'unit_cost',
        'balance_after',
        'reference_type',
        'reference_id',
        'occurred_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_cost' => 'decimal:2',
            'balance_after' => 'decimal:3',
            'occurred_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (StockMovement $movement): void {
            $movement->uuid ??= (string) Str::uuid();
            $movement->occurred_at ??= now();
        });

        static::updating(function (): never {
            throw new LogicException(
                'Stock movements are immutable. Create a reversal instead.',
            );
        });

        static::deleting(function (): never {
            throw new LogicException(
                'Stock movements cannot be deleted.',
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

    public function pharmacyMedicine(): BelongsTo
    {
        return $this->belongsTo(PharmacyMedicine::class);
    }

    public function medicineBatch(): BelongsTo
    {
        return $this->belongsTo(MedicineBatch::class);
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by_user_id',
        );
    }
}