<?php

namespace Tests\Feature;

use App\Models\InventoryAlert;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\Pharmacy;
use App\Models\PharmacyBranch;
use App\Models\PharmacyMedicine;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class InventoryAlertInterfaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_owner_only_sees_own_inventory_alerts(): void
    {
        $contextA = $this->createContext('ALPHA');
        $contextB = $this->createContext('BRAVO');

        $this->actingAs($contextA['owner'])
            ->get('/pharmacy/inventory-alerts')
            ->assertOk()
            ->assertSee('ALPHA medicine stock is low')
            ->assertDontSee('BRAVO medicine stock is low');

        $this->actingAs($contextA['owner'])
            ->get(
                "/pharmacy/inventory-alerts/{$contextA['alert']->id}"
            )
            ->assertOk();

        $this->actingAs($contextA['owner'])
            ->get(
                "/pharmacy/inventory-alerts/{$contextB['alert']->id}"
            )
            ->assertNotFound();
    }

    public function test_stock_manager_can_acknowledge_and_resolve_alert(): void
    {
        $context = $this->createContext('WORKFLOW');

        $context['alert']->acknowledge($context['owner']);

        $context['alert']->refresh();

        $this->assertSame(
            'acknowledged',
            $context['alert']->status,
        );

        $this->assertSame(
            $context['owner']->id,
            $context['alert']->acknowledged_by_user_id,
        );

        $this->assertNotNull(
            $context['alert']->acknowledged_at,
        );

        $context['alert']->resolve($context['owner']);

        $context['alert']->refresh();

        $this->assertSame(
            'resolved',
            $context['alert']->status,
        );

        $this->assertSame(
            $context['owner']->id,
            $context['alert']->resolved_by_user_id,
        );

        $this->assertNotNull(
            $context['alert']->resolved_at,
        );
    }

    public function test_view_only_user_cannot_change_alert_status(): void
    {
        $context = $this->createContext('VIEWER');

        $viewer = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        $viewer->forceFill([
            'pharmacy_id' => $context['pharmacy']->id,
            'pharmacy_branch_id' => $context['branch']->id,
        ])->save();

        $viewer->givePermissionTo('stock.view');

        $this->actingAs($viewer)
            ->get(
                "/pharmacy/inventory-alerts/{$context['alert']->id}"
            )
            ->assertOk();

        try {
            $context['alert']->acknowledge($viewer);

            $this->fail(
                'A view-only user acknowledged an inventory alert.'
            );
        } catch (HttpException $exception) {
            $this->assertSame(
                403,
                $exception->getStatusCode(),
            );
        }

        $this->assertSame(
            'open',
            $context['alert']->fresh()->status,
        );
    }

    private function createContext(string $suffix): array
    {
        $pharmacy = Pharmacy::create([
            'name' => "{$suffix} Alert Pharmacy",
            'phone' => '+257 61 '.random_int(100000, 999999),
            'status' => 'approved',
        ]);

        $branch = PharmacyBranch::create([
            'pharmacy_id' => $pharmacy->id,
            'name' => "{$suffix} Alert Branch",
            'code' => "ALR-{$suffix}",
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
            'brand_name' => "{$suffix} Alert Medicine",
            'approval_status' => 'approved',
            'is_active' => true,
        ]);

        $listing = PharmacyMedicine::create([
            'pharmacy_id' => $pharmacy->id,
            'medicine_id' => $medicine->id,
            'selling_price' => 3500,
            'minimum_stock_level' => 10,
            'reorder_quantity' => 20,
            'expiry_warning_days' => 30,
            'alerts_enabled' => true,
            'status' => 'active',
        ]);

        $batch = MedicineBatch::create([
            'pharmacy_id' => $pharmacy->id,
            'pharmacy_branch_id' => $branch->id,
            'pharmacy_medicine_id' => $listing->id,
            'batch_number' => "LOT-UI-{$suffix}",
            'expiry_date' => today()->addDays(20),
            'unit_cost' => 2500,
            'quantity_received' => 5,
            'quantity_available' => 5,
            'status' => 'active',
            'received_at' => now(),
        ]);

        $alert = InventoryAlert::create([
            'pharmacy_id' => $pharmacy->id,
            'pharmacy_branch_id' => $branch->id,
            'pharmacy_medicine_id' => $listing->id,
            'medicine_batch_id' => $batch->id,
            'alert_key' => "interface:{$suffix}",
            'alert_type' => 'low_stock',
            'severity' => 'warning',
            'current_value' => 5,
            'threshold_value' => 10,
            'message' => "{$suffix} medicine stock is low.",
            'status' => 'open',
            'detected_at' => now(),
        ]);

        return compact(
            'pharmacy',
            'branch',
            'owner',
            'medicine',
            'listing',
            'batch',
            'alert',
        );
    }
}