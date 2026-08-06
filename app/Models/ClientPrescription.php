<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ClientPrescription extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'reviewed_by_user_id',
        'prescription_number',
        'status',
        'prescriber_name',
        'prescriber_facility',
        'issued_at',
        'valid_until',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size_bytes',
        'reviewed_at',
        'rejection_reason',
        'notes',
    ];

    protected $attributes = [
        'status' => 'submitted',
        'disk' => 'local',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'date',
            'valid_until' => 'date',
            'reviewed_at' => 'datetime',
            'size_bytes' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ClientPrescription $prescription): void {
            $prescription->uuid ??= (string) Str::uuid();
            $prescription->prescription_number ??= sprintf(
                'CRX-%s-%s',
                now()->format('Ymd'),
                Str::upper(Str::random(7)),
            );
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(MarketplaceOrderItem::class);
    }
}
