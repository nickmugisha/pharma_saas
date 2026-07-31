<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Manufacturer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'country',
        'email',
        'phone',
        'website',
        'address',
        'is_active',
    ];

    protected $attributes = [
        'is_active' => true,
    ];

    public function medicines(): HasMany
{
    return $this->hasMany(Medicine::class);
}

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Manufacturer $manufacturer): void {
            $manufacturer->uuid ??= (string) Str::uuid();
        });
    }
}