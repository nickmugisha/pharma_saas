<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'pharmacy_id',
        'registered_branch_id',
        'user_id',
        'customer_number',
        'name',
        'phone',
        'email',
        'address',
        'city',
        'country',
        'preferred_language',
        'status',
        'registered_at',
        'last_activity_at',
        'notes',
    ];

    protected $attributes = [
        'country' => 'Burundi',
        'preferred_language' => 'fr',
        'status' => 'active',
    ];

    protected function casts(): array
    {
        return [
            'registered_at' => 'datetime',
            'last_activity_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Customer $customer): void {
            $customer->uuid ??= (string) Str::uuid();

            $reference = Str::upper(
                Str::substr(
                    Str::replace('-', '', $customer->uuid),
                    0,
                    8,
                ),
            );

            $customer->customer_number ??= sprintf(
                'CUS-%s-%s',
                now()->format('Ymd'),
                $reference,
            );

            $customer->registered_at ??= now();
        });
    }

    public function pharmacy(): BelongsTo
    {
        return $this->belongsTo(Pharmacy::class);
    }

    public function registeredBranch(): BelongsTo
    {
        return $this->belongsTo(
            PharmacyBranch::class,
            'registered_branch_id',
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function patientProfile(): HasOne
    {
        return $this->hasOne(PatientProfile::class);
    }

    public function activities(): HasMany
{
    return $this->hasMany(CustomerActivity::class)
        ->latest('occurred_at');
}

public function prescriptions(): HasMany
{
    return $this->hasMany(Prescription::class)
        ->latest('created_at');
}

   public function sales(): HasMany
{
    return $this->hasMany(Sale::class)
        ->latest('sold_at');
}
}