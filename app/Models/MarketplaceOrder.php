<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class MarketplaceOrder extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_AWAITING_REVIEW = 'awaiting_prescription_review';
    public const STATUS_AWAITING_PAYMENT = 'awaiting_wallet_payment';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_EXPIRED = 'expired';

    public const PAYMENT_UNPAID = 'unpaid';
    public const PAYMENT_PAID = 'paid';
    public const PAYMENT_REFUNDED = 'refunded';

    protected $fillable = [
        'user_id',
        'client_wallet_id',
        'wallet_payment_transaction_id',
        'wallet_refund_transaction_id',
        'pharmacy_id',
        'pharmacy_branch_id',
        'order_number',
        'status',
        'payment_status',
        'prescription_status',
        'fulfillment_method',
        'client_name',
        'client_email',
        'client_phone',
        'address_label',
        'address_line_1',
        'address_line_2',
        'city',
        'province',
        'country',
        'delivery_instructions',
        'subtotal',
        'delivery_fee',
        'grand_total',
        'currency',
        'reservation_expires_at',
        'placed_at',
        'paid_at',
        'refunded_at',
        'cancelled_at',
        'cancellation_reason',
        'notes',
    ];

    protected $attributes = [
        'status' => self::STATUS_DRAFT,
        'payment_status' => self::PAYMENT_UNPAID,
        'prescription_status' => 'not_required',
        'fulfillment_method' => 'pickup',
        'currency' => 'BIF',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'delivery_fee' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'reservation_expires_at' => 'datetime',
            'placed_at' => 'datetime',
            'paid_at' => 'datetime',
            'refunded_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (MarketplaceOrder $order): void {
            $order->uuid ??= (string) Str::uuid();
            $order->order_number ??= sprintf(
                'ORD-%s-%s',
                now()->format('Ymd'),
                Str::upper(Str::random(7)),
            );
            $order->placed_at ??= now();
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

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(ClientWallet::class, 'client_wallet_id');
    }

    public function walletPaymentTransaction(): BelongsTo
    {
        return $this->belongsTo(
            WalletTransaction::class,
            'wallet_payment_transaction_id',
        );
    }

    public function walletRefundTransaction(): BelongsTo
    {
        return $this->belongsTo(
            WalletTransaction::class,
            'wallet_refund_transaction_id',
        );
    }

    public function pharmacy(): BelongsTo
    {
        return $this->belongsTo(Pharmacy::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(PharmacyBranch::class, 'pharmacy_branch_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(MarketplaceOrderItem::class);
    }

    public function stockReservations(): HasMany
    {
        return $this->hasMany(MarketplaceStockReservation::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(MarketplaceOrderEvent::class)
            ->latest('occurred_at');
    }
}
