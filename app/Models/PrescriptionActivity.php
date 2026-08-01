<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

class PrescriptionActivity extends Model
{
    use HasFactory;

    protected $fillable = [
        'pharmacy_id',
        'pharmacy_branch_id',
        'prescription_id',
        'actor_user_id',
        'activity_type',
        'title',
        'description',
        'metadata',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(
            function (PrescriptionActivity $activity): void {
                $activity->uuid ??= (string) Str::uuid();
                $activity->occurred_at ??= now();
            },
        );

        static::updating(function (): never {
            throw new LogicException(
                'Prescription activities are immutable.',
            );
        });

        static::deleting(function (): never {
            throw new LogicException(
                'Prescription activities cannot be deleted.',
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

    public function actorUser(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'actor_user_id',
        );
    }
}