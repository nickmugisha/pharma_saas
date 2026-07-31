<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Medicine extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'brand_name',
        'generic_name',
        'medicine_category_id',
        'dosage_form_id',
        'manufacturer_id',
        'strength',
        'package_size',
        'barcode',
        'regulatory_code',
        'description',
        'indications',
        'contraindications',
        'side_effects',
        'storage_instructions',
        'prescription_status',
        'approval_status',
        'submitted_by_pharmacy_id',
        'submitted_by_user_id',
        'reviewed_by_user_id',
        'submitted_at',
        'reviewed_at',
        'review_notes',
        'is_active',
    ];

    protected $attributes = [
        'prescription_status' => 'otc',
        'approval_status' => 'draft',
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Medicine $medicine): void {
            $medicine->uuid ??= (string) Str::uuid();
            $medicine->slug ??= static::generateUniqueSlug($medicine);
        });

        static::saving(function (Medicine $medicine): void {
            if (! $medicine->isDirty('approval_status')) {
                return;
            }

            if (
                $medicine->approval_status === 'pending_review'
                && ! $medicine->submitted_at
            ) {
                $medicine->submitted_at = now();
            }

            if (
                in_array($medicine->approval_status, [
                    'approved',
                    'changes_requested',
                    'rejected',
                    'suspended',
                ], true)
                && ! $medicine->reviewed_at
            ) {
                $medicine->reviewed_at = now();
            }
        });
    }

    private static function generateUniqueSlug(Medicine $medicine): string
    {
        $base = Str::slug(
            collect([
                $medicine->brand_name,
                $medicine->strength,
            ])->filter()->implode(' '),
        );

        $base = $base !== '' ? $base : 'medicine';

        $slug = $base;
        $number = 2;

        while (
            static::withTrashed()
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = "{$base}-{$number}";
            $number++;
        }

        return $slug;
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(
            MedicineCategory::class,
            'medicine_category_id',
        );
    }

    public function dosageForm(): BelongsTo
    {
        return $this->belongsTo(DosageForm::class);
    }

    public function manufacturer(): BelongsTo
    {
        return $this->belongsTo(Manufacturer::class);
    }

    public function submittedByPharmacy(): BelongsTo
    {
        return $this->belongsTo(
            Pharmacy::class,
            'submitted_by_pharmacy_id',
        );
    }

    public function submittedByUser(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'submitted_by_user_id',
        );
    }

    public function reviewedByUser(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'reviewed_by_user_id',
        );
    }

    public function ingredients(): HasMany
    {
        return $this->hasMany(MedicineIngredient::class)
            ->orderBy('sort_order');
    }

    public function pharmacyListings(): HasMany
{
    return $this->hasMany(PharmacyMedicine::class);
}

    public function images(): HasMany
    {
        return $this->hasMany(MedicineImage::class)
            ->orderByDesc('is_primary')
            ->orderBy('sort_order');
    }

    public function primaryImage(): HasOne
{
    return $this->hasOne(MedicineImage::class)
        ->where('is_primary', true);
}
}