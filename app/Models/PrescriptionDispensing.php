<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LogicException;

class PrescriptionDispensing extends Model
{
    use HasFactory;

    public const STATUS_COMPLETED = 'completed';
    public const STATUS_VOIDED = 'voided';

    protected $fillable = [
        'pharmacy_id',
        'pharmacy_branch_id',
        'prescription_id',
        'sale_id',
        'dispensed_by_user_id',
        'voided_by_user_id',
        'dispensing_number',
        'status',
        'dispensed_at',
        'voided_at',
        'void_reason',
        'notes',
    ];

    protected $attributes = [
        'status' => self::STATUS_COMPLETED,
    ];

    protected function casts(): array
    {
        return [
            'dispensed_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(
            function (
                PrescriptionDispensing $dispensing,
            ): void {
                $dispensing->uuid ??= (string) Str::uuid();

                $dispensing->dispensing_number ??= sprintf(
                    'RXD-%s-%s',
                    now()->format('Ymd'),
                    Str::upper(Str::random(6)),
                );

                $dispensing->dispensed_at ??= now();
            },
        );

        static::deleting(function (): never {
            throw new LogicException(
                'Prescription dispensing records cannot be deleted.',
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

    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function dispensedByUser(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'dispensed_by_user_id',
        );
    }

    public function voidedByUser(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'voided_by_user_id',
        );
    }

    public function items(): HasMany
    {
        return $this->hasMany(
            PrescriptionDispensingItem::class,
        );
    }
}