<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchMedicineSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'pharmacy_id',
        'pharmacy_branch_id',
        'pharmacy_medicine_id',
        'minimum_stock_level',
        'reorder_quantity',
        'expiry_warning_days',
        'alerts_enabled',
    ];

    protected function casts(): array
    {
        return [
            'minimum_stock_level' => 'decimal:3',
            'reorder_quantity' => 'decimal:3',
            'expiry_warning_days' => 'integer',
            'alerts_enabled' => 'boolean',
        ];
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

    public function pharmacyMedicine(): BelongsTo
    {
        return $this->belongsTo(PharmacyMedicine::class);
    }
}