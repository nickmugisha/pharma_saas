<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Medicine extends Model
{
    use HasFactory, SoftDeletes;

    public const ONLINE_OTC = 'otc';
    public const ONLINE_PRESCRIPTION_REQUIRED = 'prescription_required';
    public const ONLINE_PHARMACIST_REVIEW = 'pharmacist_review';
    public const ONLINE_IN_STORE_ONLY = 'in_store_only';

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
        'marketplace_summary',
        'indications',
        'contraindications',
        'side_effects',
        'storage_instructions',
        'prescription_status',
        'online_sale_mode',
        'approval_status',
        'submitted_by_pharmacy_id',
        'submitted_by_user_id',
        'reviewed_by_user_id',
        'submitted_at',
        'reviewed_at',
        'review_notes',
        'is_active',
        'is_marketplace_featured',
    ];

    protected $attributes = [
        'prescription_status' => 'otc',
        'online_sale_mode' => self::ONLINE_OTC,
        'approval_status' => 'draft',
        'is_active' => true,
        'is_marketplace_featured' => false,
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'is_active' => 'boolean',
            'is_marketplace_featured' => 'boolean',
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

            if ($medicine->approval_status === 'pending_review' && ! $medicine->submitted_at) {
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

    public function requiresPrescriptionForOnlineOrder(): bool
    {
        return $this->online_sale_mode === self::ONLINE_PRESCRIPTION_REQUIRED;
    }

    public function requiresPharmacistReviewForOnlineOrder(): bool
    {
        return in_array($this->online_sale_mode, [
            self::ONLINE_PRESCRIPTION_REQUIRED,
            self::ONLINE_PHARMACIST_REVIEW,
        ], true);
    }

    public function isOnlineOrderable(): bool
    {
        return $this->online_sale_mode !== self::ONLINE_IN_STORE_ONLY;
    }

    public function getMarketplaceImageUrlAttribute(): ?string
    {
        $image = $this->relationLoaded('primaryImage')
            ? $this->primaryImage
            : $this->primaryImage()->first();

        if (! $image) {
            return null;
        }

        return Storage::disk($image->disk)->url($image->path);
    }

    private static function generateUniqueSlug(Medicine $medicine): string
    {
        $base = Str::slug(collect([
            $medicine->brand_name,
            $medicine->strength,
        ])->filter()->implode(' '));

        $base = $base !== '' ? $base : 'medicine';
        $slug = $base;
        $number = 2;

        while (static::withTrashed()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$number}";
            $number++;
        }

        return $slug;
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(MedicineCategory::class, 'medicine_category_id');
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
        return $this->belongsTo(Pharmacy::class, 'submitted_by_pharmacy_id');
    }

    public function submittedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    public function reviewedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function ingredients(): HasMany
    {
        return $this->hasMany(MedicineIngredient::class)->orderBy('sort_order');
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
        return $this->hasOne(MedicineImage::class)->where('is_primary', true);
    }

    public function marketplaceOrderItems(): HasMany
    {
        return $this->hasMany(MarketplaceOrderItem::class);
    }
}
