<?php

namespace Tests\Feature;

use App\Models\Medicine;
use App\Models\Pharmacy;
use App\Models\PharmacyBranch;
use App\Models\PharmacyMedicine;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosSaleInterfaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_owner_only_sees_own_sales_and_receipts(): void
    {
        $contextA = $this->createContext('ALPHA');
        $contextB = $this->createContext('BRAVO');

        $this->actingAs($contextA['owner'])
            ->get('/pharmacy/sales')
            ->assertOk()
            ->assertSee($contextA['sale']->sale_number)
            ->assertDontSee($contextB['sale']->sale_number);

        $this->actingAs($contextA['owner'])
            ->get(
                "/pharmacy/sales/{$contextA['sale']->id}"
            )
            ->assertOk()
            ->assertSee($contextA['sale']->receipt_number)
            ->assertSee('ALPHA Receipt Medicine')
            ->assertSee('Cash');

        $this->actingAs($contextA['owner'])
            ->get(
                "/pharmacy/sales/{$contextB['sale']->id}"
            )
            ->assertNotFound();
    }

    public function test_sales_manager_can_access_pos_create_page(): void
    {
        $context = $this->createContext('MANAGER');

        $this->actingAs($context['owner'])
            ->get('/pharmacy/sales/create')
            ->assertOk()
            ->assertSee('New POS Sale')
            ->assertSee('Payments');
    }

    public function test_view_only_user_cannot_access_create_page(): void
    {
        $context = $this->createContext('VIEWER');

        $viewer = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        $viewer->forceFill([
            'pharmacy_id' =>
                $context['pharmacy']->id,

            'pharmacy_branch_id' =>
                $context['branch']->id,
        ])->save();

        $viewer->givePermissionTo('sales.view');

        $this->actingAs($viewer)
            ->get('/pharmacy/sales')
            ->assertOk();

        $this->actingAs($viewer)
            ->get(
                "/pharmacy/sales/{$context['sale']->id}"
            )
            ->assertOk();

        $this->actingAs($viewer)
            ->get('/pharmacy/sales/create')
            ->assertForbidden();

        $this->actingAs($viewer)
            ->get(
                "/pharmacy/sales/{$context['sale']->id}/edit"
            )
            ->assertNotFound();
    }

    private function createContext(string $suffix): array
    {
        $pharmacy = Pharmacy::create([
            'name' => "{$suffix} Receipt Pharmacy",
            'phone' => '+257 61 '.random_int(
                100000,
                999999,
            ),
            'status' => 'approved',
        ]);

        $branch = PharmacyBranch::create([
            'pharmacy_id' => $pharmacy->id,
            'name' => "{$suffix} Receipt Branch",
            'code' => 'SALE-'.strtoupper(
                substr(md5($suffix), 0, 8),
            ),
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

        $medicine = Medicine::create([
            'brand_name' =>
                "{$suffix} Receipt Medicine",

            'approval_status' => 'approved',
            'is_active' => true,
        ]);

        $listing = PharmacyMedicine::create([
            'pharmacy_id' => $pharmacy->id,
            'medicine_id' => $medicine->id,
            'sku' => "SALE-SKU-{$suffix}",
            'selling_price' => 3500,
            'minimum_stock_level' => 0,
            'reorder_quantity' => 0,
            'expiry_warning_days' => 90,
            'alerts_enabled' => true,
            'status' => 'active',
        ]);

        $sale = Sale::create([
            'pharmacy_id' => $pharmacy->id,
            'pharmacy_branch_id' => $branch->id,
            'cashier_user_id' => $owner->id,
            'sale_number' => "SALE-{$suffix}-001",
            'receipt_number' => "RCT-{$suffix}-001",
            'channel' => 'pos',
            'sold_at' => now(),
            'completed_at' => now(),
            'status' => 'completed',
            'payment_status' => 'paid',
            'currency' => 'BIF',
            'subtotal' => 7000,
            'discount_total' => 0,
            'tax_total' => 0,
            'grand_total' => 7000,
            'paid_amount' => 7000,
            'change_amount' => 0,
            'customer_name' =>
                "{$suffix} Test Customer",
        ]);

        SaleItem::create([
            'sale_id' => $sale->id,
            'pharmacy_medicine_id' => $listing->id,
            'medicine_name' =>
                "{$suffix} Receipt Medicine",

            'sku' => "SALE-SKU-{$suffix}",
            'quantity' => 2,
            'unit_price' => 3500,
            'discount_amount' => 0,
            'tax_rate' => 0,
            'tax_amount' => 0,
            'line_total' => 7000,
            'cost_total' => 5000,
        ]);

        SalePayment::create([
            'pharmacy_id' => $pharmacy->id,
            'sale_id' => $sale->id,
            'received_by_user_id' => $owner->id,
            'payment_number' => "PAY-{$suffix}-001",
            'paid_at' => now(),
            'amount' => 7000,
            'payment_method' => 'cash',
            'status' => 'completed',
        ]);

        return compact(
            'pharmacy',
            'branch',
            'owner',
            'medicine',
            'listing',
            'sale',
        );
    }
}