<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Pharmacy extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'legal_name',
        'registration_number',
        'license_number',
        'tax_number',
        'email',
        'phone',
        'alternate_phone',
        'address',
        'city',
        'province',
        'country',
        'status',
        'suspension_reason',
        'notes',
    ];

    protected $attributes = [
        'status' => 'pending',
        'country' => 'Burundi',
    ];

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
            'suspended_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Pharmacy $pharmacy): void {
            $pharmacy->uuid ??= (string) Str::uuid();
        });

        static::saving(function (Pharmacy $pharmacy): void {
            if (! $pharmacy->isDirty('status')) {
                return;
            }

            if ($pharmacy->status === 'approved') {
                $pharmacy->approved_at ??= now();
                $pharmacy->suspended_at = null;
                $pharmacy->suspension_reason = null;
            }

            if ($pharmacy->status === 'suspended') {
                $pharmacy->suspended_at ??= now();
            }
        });
    }
}