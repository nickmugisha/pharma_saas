<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InventoryAlert extends Model
{
    use HasFactory;

    protected $fillable = [
        'pharmacy_id',
        'pharmacy_branch_id',
        'pharmacy_medicine_id',
        'medicine_batch_id',
        'acknowledged_by_user_id',
        'resolved_by_user_id',
        'alert_key',
        'alert_type',
        'severity',
        'current_value',
        'threshold_value',
        'message',
        'status',
        'detected_at',
        'acknowledged_at',
        'resolved_at',
    ];

    protected $attributes = [
        'severity' => 'warning',
        'status' => 'open',
    ];

    protected function casts(): array
    {
        return [
            'current_value' => 'decimal:3',
            'threshold_value' => 'decimal:3',
            'detected_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (InventoryAlert $alert): void {
            $alert->uuid ??= (string) Str::uuid();
            $alert->detected_at ??= now();
        });
    }

public function acknowledge(User $user): void
{
    abort_unless(
        $user->can('stock.manage'),
        403,
    );

    abort_unless(
        (int) $user->pharmacy_id === (int) $this->pharmacy_id,
        403,
    );

    if ($this->status === 'resolved') {
        throw ValidationException::withMessages([
            'alert' => 'A resolved alert cannot be acknowledged.',
        ]);
    }

    $this->forceFill([
        'status' => 'acknowledged',
        'acknowledged_by_user_id' => $user->id,
        'acknowledged_at' => now(),
    ])->save();
}

public function resolve(User $user): void
{
    abort_unless(
        $user->can('stock.manage'),
        403,
    );

    abort_unless(
        (int) $user->pharmacy_id === (int) $this->pharmacy_id,
        403,
    );

    if ($this->status === 'resolved') {
        return;
    }

    $this->forceFill([
        'status' => 'resolved',
        'resolved_by_user_id' => $user->id,
        'resolved_at' => now(),
    ])->save();
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

    public function acknowledgedByUser(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'acknowledged_by_user_id',
        );
    }

    public function resolvedByUser(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'resolved_by_user_id',
        );
    }
}