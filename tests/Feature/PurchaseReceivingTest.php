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
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Tests\TestCase;

class PurchaseReceivingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_full_receipt_creates_batch_movement_and_received_order(): void
    {
        $context = $this->createContext('FULL');

        $receipt = app(ReceivePurchaseOrder::class)->handle(
            $context['owner'],
            $context['order']->id,
            [[
                'purchase_order_item_id' => $context['item']->id,
                'batch_number' => 'LOT-FULL-001',
                'manufacturing_date' => today()
                    ->subMonth()
                    ->toDateString(),
                'expiry_date' => today()
                    ->addYear()
                    ->toDateString(),
                'quantity_received' => 100,
            ]],
        );

        $batch = $receipt->items->first()->medicineBatch;

        $this->assertSame('completed', $receipt->status);
        $this->assertSame(
            'received',
            $context['order']->fresh()->status,
        );
        $this->assertSame(
            '100.000',
            $context['item']->fresh()->quantity_received,
        );
        $this->assertSame('100.000', $batch->quantity_available);

        $this->assertDatabaseHas('stock_movements', [
            'medicine_batch_id' => $batch->id,
            'movement_type' => 'purchase_receipt',
            'direction' => 'in',
            'balance_after' => 100,
        ]);
    }

    public function test_partial_then_final_receipt_updates_same_batch(): void
    {
        $context = $this->createContext('PARTIAL');

        $action = app(ReceivePurchaseOrder::class);

        $action->handle(
            $context['owner'],
            $context['order']->id,
            [[
                'purchase_order_item_id' => $context['item']->id,
                'batch_number' => 'LOT-PART-001',
                'expiry_date' => today()
                    ->addYear()
                    ->toDateString(),
                'quantity_received' => 40,
            ]],
        );

        $this->assertSame(
            'partially_received',
            $context['order']->fresh()->status,
        );

        $action->handle(
            $context['owner'],
            $context['order']->id,
            [[
                'purchase_order_item_id' => $context['item']->id,
                'batch_number' => 'LOT-PART-001',
                'expiry_date' => today()
                    ->addYear()
                    ->toDateString(),
                'quantity_received' => 60,
            ]],
        );

        $batch = $context['listing']->medicineBatches()
            ->where('batch_number', 'LOT-PART-001')
            ->firstOrFail();

        $this->assertSame(
            'received',
            $context['order']->fresh()->status,
        );
        $this->assertSame('100.000', $batch->quantity_received);
        $this->assertSame('100.000', $batch->quantity_available);
        $this->assertCount(2, $batch->stockMovements);
    }

    public function test_receiving_more_than_remaining_quantity_is_rejected(): void
    {
        $context = $this->createContext('LIMIT');

        app(ReceivePurchaseOrder::class)->handle(
            $context['owner'],
            $context['order']->id,
            [[
                'purchase_order_item_id' => $context['item']->id,
                'batch_number' => 'LOT-LIMIT-001',
                'expiry_date' => today()
                    ->addYear()
                    ->toDateString(),
                'quantity_received' => 80,
            ]],
        );

        try {
            app(ReceivePurchaseOrder::class)->handle(
                $context['owner'],
                $context['order']->id,
                [[
                    'purchase_order_item_id' => $context['item']->id,
                    'batch_number' => 'LOT-LIMIT-001',
                    'expiry_date' => today()
                        ->addYear()
                        ->toDateString(),
                    'quantity_received' => 30,
                ]],
            );

            $this->fail('Excess receipt was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey(
                'items.0.quantity_received',
                $exception->errors(),
            );
        }

        $this->assertDatabaseCount('purchase_receipts', 1);
        $this->assertDatabaseCount('stock_movements', 1);
    }
public function test_user_cannot_receive_another_pharmacys_order(): void
{
    $contextA = $this->createContext('A');
    $contextB = $this->createContext('B');

    try {
        app(ReceivePurchaseOrder::class)->handle(
            $contextB['owner'],
            $contextA['order']->id,
            [[
                'purchase_order_item_id' => $contextA['item']->id,
                'batch_number' => 'FOREIGN-BATCH',
                'expiry_date' => today()
                    ->addYear()
                    ->toDateString(),
                'quantity_received' => 10,
            ]],
        );

        $this->fail('Cross-pharmacy receipt was accepted.');
    } catch (ModelNotFoundException) {
        $this->assertTrue(true);
    }

    $this->assertDatabaseCount('purchase_receipts', 0);
}

    private function createContext(string $suffix): array
    {
        $pharmacy = Pharmacy::create([
            'name' => "Stock {$suffix} Pharmacy",
            'phone' => '+257 61 '.random_int(100000, 999999),
            'status' => 'approved',
        ]);

        $branch = PharmacyBranch::create([
            'pharmacy_id' => $pharmacy->id,
            'name' => "Stock {$suffix} Branch",
            'code' => "STK-{$suffix}",
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
            'name' => "Stock {$suffix} Supplier",
            'phone' => '+257 79 '.random_int(100000, 999999),
            'status' => 'active',
        ]);

        $medicine = Medicine::create([
            'brand_name' => "Stock {$suffix} Medicine",
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
            'order_number' => "PO-STOCK-{$suffix}",
            'status' => 'approved',
        ]);

        $item = $order->items()->create([
            'pharmacy_medicine_id' => $listing->id,
            'quantity_ordered' => 100,
            'unit_cost' => 2500,
        ]);

        return compact(
            'pharmacy',
            'branch',
            'owner',
            'supplier',
            'medicine',
            'listing',
            'order',
            'item',
        );
    }
}