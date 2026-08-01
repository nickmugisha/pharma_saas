<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PrescriptionAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'prescription_id',
        'uploaded_by_user_id',
        'attachment_type',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size_bytes',
    ];

    protected $attributes = [
        'attachment_type' => 'prescription',
        'disk' => 'public',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(
            function (PrescriptionAttachment $attachment): void {
                $attachment->uuid ??= (string) Str::uuid();
            },
        );
    }

    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }

    public function uploadedByUser(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'uploaded_by_user_id',
        );
    }
}