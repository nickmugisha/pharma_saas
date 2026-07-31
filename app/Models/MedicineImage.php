<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MedicineImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'medicine_id',
        'disk',
        'path',
        'alt_text',
        'is_primary',
        'sort_order',
    ];

    protected $attributes = [
        'disk' => 'public',
        'is_primary' => false,
        'sort_order' => 0,
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (MedicineImage $image): void {
            if (! $image->is_primary || ! $image->medicine_id) {
                return;
            }

            $query = static::query()
                ->where('medicine_id', $image->medicine_id)
                ->where('is_primary', true);

            if ($image->exists) {
                $query->whereKeyNot($image->getKey());
            }

            $query->update([
                'is_primary' => false,
            ]);
        });
    }

    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class);
    }
}