<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Prescription extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_UNDER_REVIEW = 'under_review';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_PARTIALLY_DISPENSED = 'partially_dispensed';
    public const STATUS_DISPENSED = 'dispensed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'pharmacy_id',
        'pharmacy_branch_id',
        'customer_id',
        'created_by_user_id',
        'reviewed_by_user_id',
        'prescription_number',
        'status',
        'source',
        'prescriber_name',
        'prescriber_phone',
        'prescriber_facility',
        'prescriber_registration_number',
        'issued_at',
        'valid_until',
        'submitted_at',
        'reviewed_at',
        'approved_at',
        'rejected_at',
        'dispensed_at',
        'rejection_reason',
        'notes',
    ];

    protected $attributes = [
        'status' => self::STATUS_DRAFT,
        'source' => 'uploaded',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'date',
            'valid_until' => 'date',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'dispensed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Prescription $prescription): void {
            $prescription->uuid ??= (string) Str::uuid();

            $reference = Str::upper(
                Str::substr(
                    Str::replace('-', '', $prescription->uuid),
                    0,
                    8,
                ),
            );

            $prescription->prescription_number ??= sprintf(
                'RX-%s-%s',
                now()->format('Ymd'),
                $reference,
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

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by_user_id',
        );
    }

    public function reviewedByUser(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'reviewed_by_user_id',
        );
    }

    public function items(): HasMany
    {
        return $this->hasMany(PrescriptionItem::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(
            PrescriptionAttachment::class,
        );
    }

    public function dispensings(): HasMany
{
    return $this->hasMany(
        PrescriptionDispensing::class,
    )->latest('dispensed_at');
}

    public function activities(): HasMany
    {
        return $this->hasMany(PrescriptionActivity::class)
            ->latest('occurred_at');
    }
}