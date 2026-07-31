<?php

namespace Tests\Feature;

use App\Actions\Purchasing\RecordSupplierPayment;
use App\Models\Pharmacy;
use App\Models\PharmacyBranch;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\SupplierPayment;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SupplierFinanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_supplier_invoice_is_linked_to_approved_purchase_order(): void
    {
        $context = $this->createFinanceContext('LINK');

        $invoice = $context['invoice'];

        $this->assertSame(
            $context['order']->id,
            $invoice->purchaseOrder->id,
        );

        $this->assertSame(
            $context['supplier']->id,
            $invoice->supplier->id,
        );

        $this->assertSame('270000.00', $invoice->grand_total);
        $this->assertSame('0.00', $invoice->paid_amount);
        $this->assertSame('270000.00', $invoice->balance_due);
        $this->assertSame('unpaid', $invoice->status);
    }

    public function test_partial_full_and_voided_payments_recalculate_invoice(): void
    {
        $context = $this->createFinanceContext('LIFE');
        $invoice = $context['invoice'];

        $firstPayment = SupplierPayment::create([
            'pharmacy_id' => $context['pharmacy']->id,
            'supplier_invoice_id' => $invoice->id,
            'supplier_id' => $context['supplier']->id,
            'created_by_user_id' => $context['owner']->id,
            'payment_number' => 'PAY-LIFE-001',
            'payment_date' => today(),
            'amount' => 100000,
            'payment_method' => 'bank_transfer',
            'status' => 'completed',
        ]);

        $invoice->refresh();

        $this->assertSame('100000.00', $invoice->paid_amount);
        $this->assertSame('170000.00', $invoice->balance_due);
        $this->assertSame('partially_paid', $invoice->status);

        $secondPayment = SupplierPayment::create([
            'pharmacy_id' => $context['pharmacy']->id,
            'supplier_invoice_id' => $invoice->id,
            'supplier_id' => $context['supplier']->id,
            'created_by_user_id' => $context['owner']->id,
            'payment_number' => 'PAY-LIFE-002',
            'payment_date' => today(),
            'amount' => 170000,
            'payment_method' => 'cash',
            'status' => 'completed',
        ]);

        $invoice->refresh();

        $this->assertSame('270000.00', $invoice->paid_amount);
        $this->assertSame('0.00', $invoice->balance_due);
        $this->assertSame('paid', $invoice->status);

        $secondPayment->forceFill([
            'status' => 'voided',
            'voided_by_user_id' => $context['owner']->id,
            'voided_at' => now(),
            'void_reason' => 'Duplicate payment test.',
        ])->save();

        $invoice->refresh();

        $this->assertSame('100000.00', $invoice->paid_amount);
        $this->assertSame('170000.00', $invoice->balance_due);
        $this->assertSame('partially_paid', $invoice->status);

        $this->assertSame(
            'completed',
            $firstPayment->fresh()->status,
        );
    }

    public function test_payment_action_rejects_amount_above_invoice_balance(): void
    {
        $context = $this->createFinanceContext('LIMIT');

        try {
            app(RecordSupplierPayment::class)->handle(
                $context['owner'],
                [
                    'supplier_invoice_id' =>
                        $context['invoice']->id,
                    'payment_date' => today()->toDateString(),
                    'amount' => 270001,
                    'payment_method' => 'cash',
                ],
            );

            $this->fail('An excessive payment was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey(
                'amount',
                $exception->errors(),
            );
        }

        $this->assertDatabaseCount('supplier_payments', 0);

        $context['invoice']->refresh();

        $this->assertSame('0.00', $context['invoice']->paid_amount);
        $this->assertSame(
            '270000.00',
            $context['invoice']->balance_due,
        );
    }

    public function test_pharmacy_owner_only_accesses_own_supplier_finance_records(): void
    {
        $contextA = $this->createFinanceContext('A');
        $contextB = $this->createFinanceContext('B');

        $paymentA = SupplierPayment::create([
            'pharmacy_id' => $contextA['pharmacy']->id,
            'supplier_invoice_id' => $contextA['invoice']->id,
            'supplier_id' => $contextA['supplier']->id,
            'created_by_user_id' => $contextA['owner']->id,
            'payment_number' => 'PAY-PHARMACY-A',
            'payment_date' => today(),
            'amount' => 50000,
            'payment_method' => 'cash',
            'status' => 'completed',
        ]);

        $paymentB = SupplierPayment::create([
            'pharmacy_id' => $contextB['pharmacy']->id,
            'supplier_invoice_id' => $contextB['invoice']->id,
            'supplier_id' => $contextB['supplier']->id,
            'created_by_user_id' => $contextB['owner']->id,
            'payment_number' => 'PAY-PHARMACY-B',
            'payment_date' => today(),
            'amount' => 60000,
            'payment_method' => 'cash',
            'status' => 'completed',
        ]);

        $this->actingAs($contextA['owner'])
            ->get('/pharmacy/supplier-invoices')
            ->assertOk()
            ->assertSee('INV-A')
            ->assertDontSee('INV-B');

        $this->actingAs($contextA['owner'])
            ->get(
                "/pharmacy/supplier-invoices/{$contextA['invoice']->id}/edit"
            )
            ->assertOk();

        $this->actingAs($contextA['owner'])
            ->get(
                "/pharmacy/supplier-invoices/{$contextB['invoice']->id}/edit"
            )
            ->assertNotFound();

        $this->actingAs($contextA['owner'])
            ->get('/pharmacy/supplier-payments')
            ->assertOk()
            ->assertSee('PAY-PHARMACY-A')
            ->assertDontSee('PAY-PHARMACY-B');

        $this->actingAs($contextA['owner'])
            ->get("/pharmacy/supplier-payments/{$paymentA->id}/edit")
            ->assertOk();

        $this->actingAs($contextA['owner'])
            ->get("/pharmacy/supplier-payments/{$paymentB->id}/edit")
            ->assertNotFound();
    }

    private function createFinanceContext(string $suffix): array
    {
        $pharmacy = Pharmacy::create([
            'name' => "Finance {$suffix} Pharmacy",
            'phone' => '+257 61 '.random_int(100000, 999999),
            'status' => 'approved',
        ]);

        $branch = PharmacyBranch::create([
            'pharmacy_id' => $pharmacy->id,
            'name' => "Finance {$suffix} Branch",
            'code' => "FIN-{$suffix}",
            'is_main' => true,
            'status' => 'active',
        ]);

        $owner = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        $owner->forceFill([
            'pharmacy_id' => $pharmacy->id,
            'pharmacy_branch_id' => $branch->id,
        ])->save();

        $owner->assignRole('pharmacy_owner');

        $supplier = Supplier::create([
            'pharmacy_id' => $pharmacy->id,
            'created_by_user_id' => $owner->id,
            'name' => "Finance {$suffix} Supplier",
            'phone' => '+257 79 '.random_int(100000, 999999),
            'status' => 'active',
        ]);

        $order = PurchaseOrder::create([
            'pharmacy_id' => $pharmacy->id,
            'pharmacy_branch_id' => $branch->id,
            'supplier_id' => $supplier->id,
            'created_by_user_id' => $owner->id,
            'order_number' => "PO-{$suffix}",
            'subtotal' => 250000,
            'shipping_total' => 20000,
            'grand_total' => 270000,
            'status' => 'approved',
        ]);

        $invoice = SupplierInvoice::create([
            'pharmacy_id' => $pharmacy->id,
            'pharmacy_branch_id' => $branch->id,
            'supplier_id' => $supplier->id,
            'purchase_order_id' => $order->id,
            'created_by_user_id' => $owner->id,
            'invoice_number' => "INV-{$suffix}",
            'invoice_date' => today(),
            'due_date' => today()->addDays(30),
            'subtotal' => 250000,
            'shipping_total' => 20000,
            'grand_total' => 270000,
        ]);

        return compact(
            'pharmacy',
            'branch',
            'owner',
            'supplier',
            'order',
            'invoice',
        );
    }
}