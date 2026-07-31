<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class SupplierInvoice extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'pharmacy_id',
        'pharmacy_branch_id',
        'supplier_id',
        'purchase_order_id',
        'created_by_user_id',
        'invoice_number',
        'invoice_date',
        'due_date',
        'currency',
        'subtotal',
        'discount_total',
        'tax_total',
        'shipping_total',
        'grand_total',
        'paid_amount',
        'balance_due',
        'status',
        'notes',
    ];

    protected $attributes = [
        'currency' => 'BIF',
        'subtotal' => 0,
        'discount_total' => 0,
        'tax_total' => 0,
        'shipping_total' => 0,
        'paid_amount' => 0,
        'balance_due' => 0,
        'status' => 'unpaid',
    ];

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'due_date' => 'date',
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'shipping_total' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'balance_due' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (SupplierInvoice $invoice): void {
            $invoice->uuid ??= (string) Str::uuid();
            $invoice->invoice_date ??= today();
            $invoice->balance_due = $invoice->grand_total;
        });
    }

    public function recalculatePayments(): void
    {
        if ($this->status === 'cancelled') {
            return;
        }

        $paid = (float) $this->payments()
            ->where('status', 'completed')
            ->sum('amount');

        $grandTotal = (float) $this->grand_total;
        $balance = max($grandTotal - $paid, 0);

        $status = match (true) {
            $grandTotal > 0 && $balance <= 0 => 'paid',
            $paid > 0 => 'partially_paid',
            $this->due_date?->isPast() => 'overdue',
            default => 'unpaid',
        };

        $this->forceFill([
            'paid_amount' => round($paid, 2),
            'balance_due' => round($balance, 2),
            'status' => $status,
        ])->saveQuietly();
    }

    public function pharmacy(): BelongsTo
    {
        return $this->belongsTo(Pharmacy::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(
            PharmacyBranch::class,
            'pharmacy_branch_id',
        );
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by_user_id',
        );
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SupplierPayment::class);
    }
}