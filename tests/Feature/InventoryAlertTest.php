<?php

namespace Tests\Feature;

use App\Actions\Stock\ReceivePurchaseOrder;
use App\Actions\Stock\SyncInventoryAlerts;
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

class InventoryAlertTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_receiving_stock_creates_low_stock_and_expiry_alerts(): void
    {
        $context = $this->createContext(
            suffix: 'LOW',
            quantity: 5,
            minimumStock: 10,
            expiryDays: 20,
            warningDays: 30,
        );

        $this->assertDatabaseHas('inventory_alerts', [
            'pharmacy_id' => $context['pharmacy']->id,
            'pharmacy_branch_id' => $context['branch']->id,
            'pharmacy_medicine_id' => $context['listing']->id,
            'alert_type' => 'low_stock',
            'status' => 'open',
        ]);

        $this->assertDatabaseHas('inventory_alerts', [
            'medicine_batch_id' => $context['batch']->id,
            'alert_type' => 'expiring',
            'status' => 'open',
        ]);

        app(SyncInventoryAlerts::class)->handle(
            pharmacyId: $context['pharmacy']->id,
        );

        $this->assertDatabaseCount('inventory_alerts', 2);
    }

    public function test_depleted_batch_creates_out_of_stock_and_resolves_expiry_alert(): void
    {
        $context = $this->createContext(
            suffix: 'EMPTY',
            quantity: 5,
            minimumStock: 10,
            expiryDays: 20,
            warningDays: 30,
        );

        $context['batch']->forceFill([
            'quantity_available' => 0,
            'status' => 'depleted',
        ])->save();

        app(SyncInventoryAlerts::class)->handle(
            pharmacyId: $context['pharmacy']->id,
        );

        $this->assertDatabaseHas('inventory_alerts', [
            'alert_type' => 'out_of_stock',
            'status' => 'open',
            'current_value' => 0,
        ]);

        $this->assertDatabaseHas('inventory_alerts', [
            'medicine_batch_id' => $context['batch']->id,
            'alert_type' => 'expiring',
            'status' => 'resolved',
        ]);

        $this->assertDatabaseCount('inventory_alerts', 2);
    }

    public function test_expired_batch_creates_critical_expired_alert(): void
    {
        $context = $this->createContext(
            suffix: 'EXPIRED',
            quantity: 10,
            minimumStock: 5,
            expiryDays: 10,
            warningDays: 30,
        );

        $context['batch']->forceFill([
            'expiry_date' => today()->subDay(),
            'status' => 'active',
        ])->save();

        app(SyncInventoryAlerts::class)->handle(
            pharmacyId: $context['pharmacy']->id,
        );

        $this->assertDatabaseHas('inventory_alerts', [
            'medicine_batch_id' => $context['batch']->id,
            'alert_type' => 'expired',
            'severity' => 'critical',
            'status' => 'open',
        ]);

        $this->assertSame(
            'expired',
            $context['batch']->fresh()->status,
        );
    }

    public function test_disabling_alerts_resolves_existing_alerts(): void
    {
        $context = $this->createContext(
            suffix: 'DISABLED',
            quantity: 5,
            minimumStock: 10,
            expiryDays: 20,
            warningDays: 30,
        );

        $context['setting']->forceFill([
            'alerts_enabled' => false,
        ])->save();

        app(SyncInventoryAlerts::class)->handle(
            pharmacyId: $context['pharmacy']->id,
        );

        $this->assertDatabaseMissing('inventory_alerts', [
            'pharmacy_id' => $context['pharmacy']->id,
            'status' => 'open',
        ]);

        $this->assertSame(
            2,
            $context['pharmacy']
                ->inventoryAlerts()
                ->where('status', 'resolved')
                ->count(),
        );
    }

    private function createContext(
        string $suffix,
        float $quantity,
        float $minimumStock,
        int $expiryDays,
        int $warningDays,
    ): array {
        $pharmacy = Pharmacy::create([
            'name' => "Alert {$suffix} Pharmacy",
            'phone' => '+257 61 '.random_int(100000, 999999),
            'status' => 'approved',
        ]);

        $branch = PharmacyBranch::create([
            'pharmacy_id' => $pharmacy->id,
            'name' => "Alert {$suffix} Branch",
            'code' => "ALT-{$suffix}",
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
            'name' => "Alert {$suffix} Supplier",
            'phone' => '+257 79 '.random_int(100000, 999999),
            'status' => 'active',
        ]);

        $medicine = Medicine::create([
            'brand_name' => "Alert {$suffix} Medicine",
            'approval_status' => 'approved',
            'is_active' => true,
        ]);

        $listing = PharmacyMedicine::create([
            'pharmacy_id' => $pharmacy->id,
            'medicine_id' => $medicine->id,
            'selling_price' => 3500,
            'minimum_stock_level' => $minimumStock,
            'reorder_quantity' => 20,
            'expiry_warning_days' => $warningDays,
            'alerts_enabled' => true,
            'status' => 'active',
        ]);

        $order = PurchaseOrder::create([
            'pharmacy_id' => $pharmacy->id,
            'pharmacy_branch_id' => $branch->id,
            'supplier_id' => $supplier->id,
            'created_by_user_id' => $owner->id,
            'order_number' => "PO-ALERT-{$suffix}",
            'status' => 'approved',
        ]);

        $item = $order->items()->create([
            'pharmacy_medicine_id' => $listing->id,
            'quantity_ordered' => $quantity,
            'unit_cost' => 2500,
        ]);

        $receipt = app(ReceivePurchaseOrder::class)->handle(
            $owner,
            $order->id,
            [[
                'purchase_order_item_id' => $item->id,
                'batch_number' => "LOT-ALERT-{$suffix}",
                'expiry_date' => today()
                    ->addDays($expiryDays)
                    ->toDateString(),
                'quantity_received' => $quantity,
            ]],
        );

        $batch = $receipt->items
            ->first()
            ->medicineBatch;

        $setting = $listing
            ->branchInventorySettings()
            ->where('pharmacy_branch_id', $branch->id)
            ->firstOrFail();

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
            'batch',
            'setting',
        );
    }
}