<?php

namespace Tests\Feature;

use App\Models\Medicine;
use App\Models\Pharmacy;
use App\Models\PharmacyBranch;
use App\Models\PharmacyMedicine;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseOrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_purchase_order_totals_are_calculated_from_items(): void
    {
        [
            'pharmacy' => $pharmacy,
            'branch' => $branch,
            'supplier' => $supplier,
            'owner' => $owner,
            'listing' => $listing,
        ] = $this->createPurchaseContext('Totals');

        $order = PurchaseOrder::create([
            'pharmacy_id' => $pharmacy->id,
            'pharmacy_branch_id' => $branch->id,
            'supplier_id' => $supplier->id,
            'created_by_user_id' => $owner->id,
            'shipping_total' => 20000,
            'status' => 'draft',
        ]);

        $item = $order->items()->create([
            'pharmacy_medicine_id' => $listing->id,
            'quantity_ordered' => 100,
            'unit_cost' => 2500,
            'discount_amount' => 0,
            'tax_rate' => 0,
        ]);

        $order->refresh();

        $this->assertSame('250000.00', $item->fresh()->line_total);
        $this->assertSame('250000.00', $order->subtotal);
        $this->assertSame('0.00', $order->discount_total);
        $this->assertSame('0.00', $order->tax_total);
        $this->assertSame('270000.00', $order->grand_total);
    }

    public function test_submission_and_approval_record_workflow_timestamps(): void
    {
        [
            'pharmacy' => $pharmacy,
            'branch' => $branch,
            'supplier' => $supplier,
            'owner' => $owner,
        ] = $this->createPurchaseContext('Workflow');

        $order = PurchaseOrder::create([
            'pharmacy_id' => $pharmacy->id,
            'pharmacy_branch_id' => $branch->id,
            'supplier_id' => $supplier->id,
            'created_by_user_id' => $owner->id,
            'status' => 'draft',
        ]);

        $order->update([
            'status' => 'submitted',
        ]);

        $this->assertNotNull($order->fresh()->submitted_at);

        $order->forceFill([
            'status' => 'approved',
            'approved_by_user_id' => $owner->id,
        ])->save();

        $order->refresh();

        $this->assertSame('approved', $order->status);
        $this->assertSame($owner->id, $order->approved_by_user_id);
        $this->assertNotNull($order->approved_at);
    }

    public function test_pharmacy_owner_only_accesses_own_purchase_orders(): void
    {
        $contextA = $this->createPurchaseContext('Pharmacy A');
        $contextB = $this->createPurchaseContext('Pharmacy B');

        $orderA = PurchaseOrder::create([
            'pharmacy_id' => $contextA['pharmacy']->id,
            'pharmacy_branch_id' => $contextA['branch']->id,
            'supplier_id' => $contextA['supplier']->id,
            'created_by_user_id' => $contextA['owner']->id,
            'order_number' => 'PO-PHARMACY-A',
            'status' => 'draft',
        ]);

        $orderB = PurchaseOrder::create([
            'pharmacy_id' => $contextB['pharmacy']->id,
            'pharmacy_branch_id' => $contextB['branch']->id,
            'supplier_id' => $contextB['supplier']->id,
            'created_by_user_id' => $contextB['owner']->id,
            'order_number' => 'PO-PHARMACY-B',
            'status' => 'draft',
        ]);

        $this->actingAs($contextA['owner'])
            ->get('/pharmacy/purchase-orders')
            ->assertOk()
            ->assertSee('PO-PHARMACY-A')
            ->assertDontSee('PO-PHARMACY-B');

        $this->actingAs($contextA['owner'])
            ->get("/pharmacy/purchase-orders/{$orderA->id}/edit")
            ->assertOk();

        $this->actingAs($contextA['owner'])
            ->get("/pharmacy/purchase-orders/{$orderB->id}/edit")
            ->assertNotFound();
    }

    private function createPurchaseContext(string $suffix): array
    {
        $pharmacy = Pharmacy::create([
            'name' => "{$suffix} Pharmacy",
            'phone' => '+257 61 '.random_int(100000, 999999),
            'status' => 'approved',
        ]);

        $branch = PharmacyBranch::create([
            'pharmacy_id' => $pharmacy->id,
            'name' => "{$suffix} Main Branch",
            'code' => 'HQ-'.strtoupper(substr(md5($suffix), 0, 6)),
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
            'name' => "{$suffix} Supplier",
            'phone' => '+257 79 '.random_int(100000, 999999),
            'status' => 'active',
        ]);

        $medicine = Medicine::create([
            'brand_name' => "{$suffix} Medicine",
            'approval_status' => 'approved',
            'is_active' => true,
        ]);

        $listing = PharmacyMedicine::create([
            'pharmacy_id' => $pharmacy->id,
            'medicine_id' => $medicine->id,
            'created_by_user_id' => $owner->id,
            'selling_price' => 3500,
            'status' => 'active',
        ]);

        return compact(
            'pharmacy',
            'branch',
            'owner',
            'supplier',
            'medicine',
            'listing',
        );
    }
}