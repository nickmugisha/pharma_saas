<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;
use LogicException;

class WalletTransaction extends Model
{
    use HasFactory;

    public const DIRECTION_CREDIT = 'credit';
    public const DIRECTION_DEBIT = 'debit';

    public const TYPE_FUNDING = 'funding';
    public const TYPE_MARKETPLACE_PAYMENT = 'marketplace_payment';
    public const TYPE_MARKETPLACE_REFUND = 'marketplace_refund';
    public const TYPE_REVERSAL = 'reversal';
    public const TYPE_MANUAL_ADJUSTMENT = 'manual_adjustment';

    protected $fillable = [
        'client_wallet_id',
        'created_by_user_id',
        'related_transaction_id',
        'transaction_number',
        'idempotency_key',
        'type',
        'direction',
        'amount',
        'balance_after',
        'currency',
        'source_type',
        'source_id',
        'description',
        'metadata',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'metadata' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (WalletTransaction $transaction): void {
            $transaction->uuid ??= (string) Str::uuid();
            $transaction->transaction_number ??= sprintf(
                'WTX-%s-%s',
                now()->format('Ymd'),
                Str::upper(Str::random(8)),
            );
            $transaction->occurred_at ??= now();
        });

        static::updating(function (): never {
            throw new LogicException('Wallet transactions are immutable.');
        });

        static::deleting(function (): never {
            throw new LogicException('Wallet transactions cannot be deleted.');
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

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function relatedTransaction(): BelongsTo
    {
        return $this->belongsTo(self::class, 'related_transaction_id');
    }

    public function reversals(): HasMany
    {
        return $this->hasMany(self::class, 'related_transaction_id');
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }
}
