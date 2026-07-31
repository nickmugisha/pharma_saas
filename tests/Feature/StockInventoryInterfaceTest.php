<?php

namespace Tests\Feature;

use App\Actions\Stock\ReceivePurchaseOrder;
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

class StockInventoryInterfaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_owner_only_sees_own_inventory_records(): void
    {
        $contextA = $this->createContext('ALPHA');
        $contextB = $this->createContext('BRAVO');

        $this->actingAs($contextA['owner'])
            ->get('/pharmacy/purchase-receipts')
            ->assertOk()
            ->assertSee($contextA['receipt']->receipt_number)
            ->assertDontSee($contextB['receipt']->receipt_number);

        $this->actingAs($contextA['owner'])
            ->get('/pharmacy/medicine-batches')
            ->assertOk()
            ->assertSee('LOT-ALPHA-001')
            ->assertDontSee('LOT-BRAVO-001');

        $this->actingAs($contextA['owner'])
            ->get('/pharmacy/stock-movements')
            ->assertOk()
            ->assertSee('LOT-ALPHA-001')
            ->assertDontSee('LOT-BRAVO-001');
    }

    private function createContext(string $suffix): array
    {
        $pharmacy = Pharmacy::create([
            'name' => "{$suffix} Inventory Pharmacy",
            'phone' => '+257 61 '.random_int(100000, 999999),
            'status' => 'approved',
        ]);

        $branch = PharmacyBranch::create([
            'pharmacy_id' => $pharmacy->id,
            'name' => "{$suffix} Main Branch",
            'code' => "INV-{$suffix}",
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
            'name' => "{$suffix} Inventory Supplier",
            'phone' => '+257 79 '.random_int(100000, 999999),
            'status' => 'active',
        ]);

        $medicine = Medicine::create([
            'brand_name' => "{$suffix} Inventory Medicine",
            'approval_status' => 'approved',
            'is_active' => true,
        ]);

        $listing = PharmacyMedicine::create([
            'pharmacy_id' => $pharmacy->id,
            'medicine_id' => $medicine->id,
            'selling_price' => 3500,
            'status' => 'active',
        ]);

        $order = PurchaseOrder::create([
            'pharmacy_id' => $pharmacy->id,
            'pharmacy_branch_id' => $branch->id,
            'supplier_id' => $supplier->id,
            'created_by_user_id' => $owner->id,
            'order_number' => "PO-INV-{$suffix}",
            'status' => 'approved',
        ]);

        $item = $order->items()->create([
            'pharmacy_medicine_id' => $listing->id,
            'quantity_ordered' => 20,
            'unit_cost' => 2500,
        ]);

        $receipt = app(ReceivePurchaseOrder::class)->handle(
            $owner,
            $order->id,
            [[
                'purchase_order_item_id' => $item->id,
                'batch_number' => "LOT-{$suffix}-001",
                'expiry_date' => today()
                    ->addYear()
                    ->toDateString(),
                'quantity_received' => 20,
            ]],
        );

        return compact(
            'pharmacy',
            'branch',
            'owner',
            'supplier',
            'medicine',
            'listing',
            'order',
            'item',
            'receipt',
        );
    }
}