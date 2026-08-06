<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LogicException;

class ClientWallet extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'wallet_number',
        'currency',
        'status',
        'activated_at',
        'suspended_at',
        'suspension_reason',
    ];

    protected $attributes = [
        'currency' => 'BIF',
        'status' => 'active',
    ];

    protected function casts(): array
    {
        return [
            'activated_at' => 'datetime',
            'suspended_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ClientWallet $wallet): void {
            $wallet->uuid ??= (string) Str::uuid();
            $wallet->wallet_number ??= sprintf(
                'WLT-%s-%s',
                now()->format('Ymd'),
                Str::upper(Str::random(8)),
            );
            $wallet->activated_at ??= now();
        });

        static::deleting(function (): never {
            throw new LogicException('Client wallets cannot be deleted.');
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function getAvailableBalanceAttribute(): string
    {
        $credits = (float) $this->transactions()
            ->where('direction', WalletTransaction::DIRECTION_CREDIT)
            ->sum('amount');

        $debits = (float) $this->transactions()
            ->where('direction', WalletTransaction::DIRECTION_DEBIT)
            ->sum('amount');

        return number_format(
            round($credits - $debits, 2),
            2,
            '.',
            '',
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(
            WalletTransaction::class,
            'client_wallet_id',
        );
    }

    public function fundingRequests(): HasMany
    {
        return $this->hasMany(
            WalletFundingRequest::class,
            'client_wallet_id',
        );
    }
}
