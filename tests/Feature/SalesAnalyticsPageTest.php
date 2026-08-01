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

class SalesAnalyticsPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_owner_sees_only_own_pharmacy_analytics(): void
    {
        $contextA = $this->createContext('ALPHA');
        $contextB = $this->createContext('BRAVO');

        $this->actingAs($contextA['owner'])
            ->get('/pharmacy/sales-analytics')
            ->assertOk()
            ->assertSee('Sales Analytics')
            ->assertSee('Report filters')
            ->assertSee('Sales revenue')
            ->assertSee('Top medicines')
            ->assertSee('Payment methods')
            ->assertSee('Branch performance')
            ->assertSee($contextA['branch']->name)
            ->assertSee($contextA['medicine']->brand_name)
            ->assertDontSee($contextB['branch']->name)
            ->assertDontSee($contextB['medicine']->brand_name);
    }

    public function test_user_without_reports_permission_is_forbidden(): void
    {
        $context = $this->createContext('DENIED');

        $cashier = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        $cashier->forceFill([
            'pharmacy_id' => $context['pharmacy']->id,
            'pharmacy_branch_id' => $context['branch']->id,
        ])->save();

        $cashier->assignRole('cashier');

        $this->assertFalse(
            $cashier->can('reports.view'),
        );

        $this->actingAs($cashier)
            ->get('/pharmacy/sales-analytics')
            ->assertForbidden();
    }

    private function createContext(string $suffix): array
    {
        $pharmacy = Pharmacy::create([
            'name' => "{$suffix} Analytics Pharmacy",
            'phone' => '+257 61 '.random_int(
                100000,
                999999,
            ),
            'status' => 'approved',
        ]);

        $branch = PharmacyBranch::create([
            'pharmacy_id' => $pharmacy->id,
            'name' => "{$suffix} Analytics Branch",
            'code' => 'ANA-'.strtoupper(
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
                "{$suffix} Analytics Medicine",

            'approval_status' => 'approved',
            'is_active' => true,
        ]);

        $listing = PharmacyMedicine::create([
            'pharmacy_id' => $pharmacy->id,
            'medicine_id' => $medicine->id,
            'sku' => "ANA-SKU-{$suffix}",
            'selling_price' => 5000,
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
            'sale_number' => "ANA-SALE-{$suffix}",
            'receipt_number' => "ANA-RCT-{$suffix}",
            'channel' => 'pos',
            'sold_at' => now()->subDay(),
            'completed_at' => now()->subDay(),
            'status' => 'completed',
            'payment_status' => 'paid',
            'currency' => 'BIF',
            'subtotal' => 10000,
            'discount_total' => 0,
            'tax_total' => 0,
            'grand_total' => 10000,
            'paid_amount' => 10000,
            'change_amount' => 0,
        ]);

        SaleItem::create([
            'sale_id' => $sale->id,
            'pharmacy_medicine_id' => $listing->id,
            'medicine_name' => $medicine->brand_name,
            'sku' => $listing->sku,
            'quantity' => 2,
            'unit_price' => 5000,
            'discount_amount' => 0,
            'tax_rate' => 0,
            'tax_amount' => 0,
            'line_total' => 10000,
            'cost_total' => 6000,
        ]);

        SalePayment::create([
            'pharmacy_id' => $pharmacy->id,
            'sale_id' => $sale->id,
            'received_by_user_id' => $owner->id,
            'payment_number' => "ANA-PAY-{$suffix}",
            'paid_at' => now()->subDay(),
            'amount' => 10000,
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