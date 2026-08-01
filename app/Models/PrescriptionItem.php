<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PrescriptionItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'prescription_id',
        'medicine_id',
        'pharmacy_medicine_id',
        'prescribed_name',
        'strength',
        'dosage_form',
        'dosage',
        'frequency',
        'duration',
        'quantity_prescribed',
        'quantity_dispensed',
        'instructions',
        'substitution_allowed',
        'status',
    ];

    protected $attributes = [
        'quantity_dispensed' => 0,
        'substitution_allowed' => false,
        'status' => 'pending',
    ];

    protected function casts(): array
    {
        return [
            'quantity_prescribed' => 'decimal:3',
            'quantity_dispensed' => 'decimal:3',
            'substitution_allowed' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (PrescriptionItem $item): void {
            $item->uuid ??= (string) Str::uuid();
        });
    }

    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }

    public function dispensingItems(): HasMany
{
    return $this->hasMany(
        PrescriptionDispensingItem::class,
    );
}

    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class);
    }

    public function pharmacyMedicine(): BelongsTo
    {
        return $this->belongsTo(PharmacyMedicine::class);
    }
}