<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

class WalletFundingRequest extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'client_wallet_id',
        'user_id',
        'reviewed_by_user_id',
        'wallet_transaction_id',
        'request_number',
        'amount',
        'currency',
        'funding_method',
        'external_reference',
        'status',
        'requested_at',
        'reviewed_at',
        'rejection_reason',
        'notes',
    ];

    protected $attributes = [
        'currency' => 'BIF',
        'status' => self::STATUS_PENDING,
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'requested_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (WalletFundingRequest $request): void {
            $request->uuid ??= (string) Str::uuid();
            $request->request_number ??= sprintf(
                'WFR-%s-%s',
                now()->format('Ymd'),
                Str::upper(Str::random(7)),
            );
            $request->requested_at ??= now();
        });

        static::deleting(function (): never {
            throw new LogicException('Wallet funding requests cannot be deleted.');
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(ClientWallet::class, 'client_wallet_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function walletTransaction(): BelongsTo
    {
        return $this->belongsTo(WalletTransaction::class);
    }
}
