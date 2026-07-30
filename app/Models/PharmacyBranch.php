<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class PharmacyBranch extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'pharmacy_id',
        'name',
        'code',
        'is_main',
        'status',
        'email',
        'phone',
        'address',
        'city',
        'province',
    ];

    protected $attributes = [
        'status' => 'active',
        'is_main' => false,
    ];

    protected function casts(): array
    {
        return [
            'is_main' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (PharmacyBranch $branch): void {
            $branch->uuid ??= (string) Str::uuid();
        });

        static::saving(function (PharmacyBranch $branch): void {
            if (! $branch->is_main || ! $branch->pharmacy_id) {
                return;
            }

            $query = static::query()
                ->where('pharmacy_id', $branch->pharmacy_id)
                ->where('is_main', true);

            if ($branch->exists) {
                $query->whereKeyNot($branch->getKey());
            }

            $query->update([
                'is_main' => false,
            ]);
        });
    }

    public function pharmacy(): BelongsTo
    {
        return $this->belongsTo(Pharmacy::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'pharmacy_branch_id');
    }
}