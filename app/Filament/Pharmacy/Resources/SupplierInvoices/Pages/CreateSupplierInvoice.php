<?php

namespace App\Filament\Pharmacy\Resources\SupplierInvoices\Pages;

use App\Filament\Pharmacy\Resources\SupplierInvoices\SupplierInvoiceResource;
use App\Models\PurchaseOrder;
use App\Models\SupplierInvoice;
use Carbon\Carbon;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateSupplierInvoice extends CreateRecord
{
    protected static string $resource = SupplierInvoiceResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = auth()->user();
        $pharmacyId = $user?->pharmacy_id;

        abort_unless($pharmacyId, 403);

        $order = PurchaseOrder::query()
            ->with('supplier')
            ->whereKey($data['purchase_order_id'])
            ->where('pharmacy_id', $pharmacyId)
            ->where('status', 'approved')
            ->firstOrFail();

        $duplicateNumber = SupplierInvoice::query()
            ->where('pharmacy_id', $pharmacyId)
            ->where('supplier_id', $order->supplier_id)
            ->where('invoice_number', $data['invoice_number'])
            ->exists();

        if ($duplicateNumber) {
            throw ValidationException::withMessages([
                'data.invoice_number' =>
                    'This supplier invoice number already exists.',
            ]);
        }

        $invoiceDate = Carbon::parse(
            $data['invoice_date'] ?? today(),
        );

        $data['pharmacy_id'] = $pharmacyId;
        $data['pharmacy_branch_id'] = $order->pharmacy_branch_id;
        $data['supplier_id'] = $order->supplier_id;
        $data['created_by_user_id'] = $user->id;
        $data['currency'] = 'BIF';

        $data['subtotal'] = $order->subtotal;
        $data['discount_total'] = $order->discount_total;
        $data['tax_total'] = $order->tax_total;
        $data['shipping_total'] = $order->shipping_total;
        $data['grand_total'] = $order->grand_total;
        $data['paid_amount'] = 0;
        $data['balance_due'] = $order->grand_total;
        $data['status'] = 'unpaid';

        $data['due_date'] ??= $invoiceDate
            ->copy()
            ->addDays(
                (int) ($order->supplier?->payment_terms_days ?? 0),
            )
            ->toDateString();

        return $data;
    }
}