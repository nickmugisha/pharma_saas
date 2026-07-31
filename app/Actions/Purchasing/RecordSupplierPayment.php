<?php

namespace App\Actions\Purchasing;

use App\Models\SupplierInvoice;
use App\Models\SupplierPayment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordSupplierPayment
{
    public function handle(User $user, array $data): SupplierPayment
    {
        return DB::transaction(function () use ($user, $data): SupplierPayment {
            $pharmacyId = $user->pharmacy_id;

            abort_unless($pharmacyId, 403);

            $invoice = SupplierInvoice::query()
                ->whereKey($data['supplier_invoice_id'])
                ->where('pharmacy_id', $pharmacyId)
                ->lockForUpdate()
                ->firstOrFail();

            $invoice->recalculatePayments();
            $invoice->refresh();

            if (
                in_array($invoice->status, ['paid', 'cancelled'], true)
                || (float) $invoice->balance_due <= 0
            ) {
                throw ValidationException::withMessages([
                    'supplier_invoice_id' =>
                        'This invoice cannot receive another payment.',
                ]);
            }

            $amount = round((float) $data['amount'], 2);

            if ($amount <= 0) {
                throw ValidationException::withMessages([
                    'amount' =>
                        'The payment amount must be greater than zero.',
                ]);
            }

            if ($amount > (float) $invoice->balance_due) {
                throw ValidationException::withMessages([
                    'amount' =>
                        'The payment cannot exceed the invoice balance.',
                ]);
            }

            return SupplierPayment::create([
                'pharmacy_id' => $pharmacyId,
                'supplier_invoice_id' => $invoice->id,
                'supplier_id' => $invoice->supplier_id,
                'created_by_user_id' => $user->id,
                'payment_date' =>
                    $data['payment_date'] ?? today()->toDateString(),
                'amount' => $amount,
                'payment_method' => $data['payment_method'],
                'reference' => $data['reference'] ?? null,
                'status' => 'completed',
                'notes' => $data['notes'] ?? null,
            ]);
        });
    }
}