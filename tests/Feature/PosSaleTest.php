<?php

namespace Tests\Feature;

use App\Actions\Sales\CompletePosSale;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\Pharmacy;
use App\Models\PharmacyBranch;
use App\Models\PharmacyMedicine;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PosSaleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_pos_sale_uses_fefo_and_records_payment_and_change(): void
    {
        $context = $this->createContext('FEFO');

        $firstBatch = $this->createBatch(
            context: $context,
            suffix: 'EARLY',
            quantity: 5,
            expiryDays: 10,
        );

        $secondBatch = $this->createBatch(
            context: $context,
            suffix: 'LATER',
            quantity: 10,
            expiryDays: 40,
        );

        $sale = app(CompletePosSale::class)->handle(
            $context['owner'],
            [[
                'pharmacy_medicine_id' =>
                    $context['listing']->id,
                'quantity' => 8,
                'discount_amount' => 0,
                'tax_rate' => 0,
            ]],
            [[
                'payment_method' => 'cash',
                'amount' => 30000,
            ]],
            [
                'customer_name' => 'Walk-in customer',
            ],
        );

        $saleItem = $sale->items()->firstOrFail();

        $allocations = $saleItem
            ->batchAllocations()
            ->orderBy('id')
            ->get();

        $this->assertSame('completed', $sale->status);
        $this->assertSame('paid', $sale->payment_status);
        $this->assertNotNull($sale->receipt_number);
        $this->assertSame('28000.00', $sale->grand_total);
        $this->assertSame('28000.00', $sale->paid_amount);
        $this->assertSame('2000.00', $sale->change_amount);

        $this->assertCount(2, $allocations);

        $this->assertSame(
            $firstBatch->id,
            $allocations[0]->medicine_batch_id,
        );

        $this->assertSame(
            '5.000',
            $allocations[0]->quantity,
        );

        $this->assertSame(
            $secondBatch->id,
            $allocations[1]->medicine_batch_id,
        );

        $this->assertSame(
            '3.000',
            $allocations[1]->quantity,
        );

        $this->assertSame(
            '0.000',
            $firstBatch->fresh()->quantity_available,
        );

        $this->assertSame(
            'depleted',
            $firstBatch->fresh()->status,
        );

        $this->assertSame(
            '7.000',
            $secondBatch->fresh()->quantity_available,
        );

        $this->assertSame(
            'active',
            $secondBatch->fresh()->status,
        );

        $this->assertDatabaseHas('sale_payments', [
            'sale_id' => $sale->id,
            'payment_method' => 'cash',
            'amount' => 30000,
            'status' => 'completed',
        ]);

        $this->assertDatabaseCount('stock_movements', 2);

        $this->assertDatabaseHas('stock_movements', [
            'medicine_batch_id' => $firstBatch->id,
            'movement_type' => 'sale',
            'direction' => 'out',
            'quantity' => 5,
            'balance_after' => 0,
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'medicine_batch_id' => $secondBatch->id,
            'movement_type' => 'sale',
            'direction' => 'out',
            'quantity' => 3,
            'balance_after' => 7,
        ]);
    }

    public function test_insufficient_stock_rolls_back_the_entire_sale(): void
    {
        $context = $this->createContext('LIMIT');

        $batch = $this->createBatch(
            context: $context,
            suffix: 'ONLY',
            quantity: 3,
            expiryDays: 30,
        );

        try {
            app(CompletePosSale::class)->handle(
                $context['owner'],
                [[
                    'pharmacy_medicine_id' =>
                        $context['listing']->id,
                    'quantity' => 5,
                ]],
                [[
                    'payment_method' => 'cash',
                    'amount' => 17500,
                ]],
            );

            $this->fail(
                'A sale with insufficient stock was accepted.'
            );
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey(
                'items.0.quantity',
                $exception->errors(),
            );
        }

        $this->assertSame(
            '3.000',
            $batch->fresh()->quantity_available,
        );

        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('sale_items', 0);
        $this->assertDatabaseCount('sale_item_batches', 0);
        $this->assertDatabaseCount('sale_payments', 0);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_noncash_overpayment_rolls_back_stock_and_sale(): void
    {
        $context = $this->createContext('OVERPAY');

        $batch = $this->createBatch(
            context: $context,
            suffix: 'MOBILE',
            quantity: 10,
            expiryDays: 30,
        );

        try {
            app(CompletePosSale::class)->handle(
                $context['owner'],
                [[
                    'pharmacy_medicine_id' =>
                        $context['listing']->id,
                    'quantity' => 2,
                ]],
                [[
                    'payment_method' => 'mobile_money',
                    'amount' => 8000,
                    'reference' => 'MM-TEST-001',
                ]],
            );

            $this->fail(
                'A noncash overpayment was accepted.'
            );
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey(
                'payments',
                $exception->errors(),
            );
        }

        $this->assertSame(
            '10.000',
            $batch->fresh()->quantity_available,
        );

        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('sale_items', 0);
        $this->assertDatabaseCount('sale_item_batches', 0);
        $this->assertDatabaseCount('sale_payments', 0);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_user_cannot_sell_another_pharmacys_medicine(): void
    {
        $contextA = $this->createContext('PHARMACY-A');
        $contextB = $this->createContext('PHARMACY-B');

        $foreignBatch = $this->createBatch(
            context: $contextB,
            suffix: 'FOREIGN',
            quantity: 10,
            expiryDays: 30,
        );

        try {
            app(CompletePosSale::class)->handle(
                $contextA['owner'],
                [[
                    'pharmacy_medicine_id' =>
                        $contextB['listing']->id,
                    'quantity' => 2,
                ]],
                [[
                    'payment_method' => 'cash',
                    'amount' => 7000,
                ]],
            );

            $this->fail(
                'A cross-pharmacy sale was accepted.'
            );
        } catch (ModelNotFoundException) {
            $this->assertTrue(true);
        }

        $this->assertSame(
            '10.000',
            $foreignBatch->fresh()->quantity_available,
        );

        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_user_with_sales_view_only_cannot_complete_sale(): void
    {
        $context = $this->createContext('VIEW-ONLY');

        $batch = $this->createBatch(
            context: $context,
            suffix: 'VIEWER',
            quantity: 10,
            expiryDays: 30,
        );

        $viewer = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        $viewer->forceFill([
            'pharmacy_id' => $context['pharmacy']->id,
            'pharmacy_branch_id' => $context['branch']->id,
        ])->save();

        $viewer->givePermissionTo('sales.view');

        try {
            app(CompletePosSale::class)->handle(
                $viewer,
                [[
                    'pharmacy_medicine_id' =>
                        $context['listing']->id,
                    'quantity' => 1,
                ]],
                [[
                    'payment_method' => 'cash',
                    'amount' => 3500,
                ]],
            );

            $this->fail(
                'A view-only user completed a POS sale.'
            );
        } catch (HttpException $exception) {
            $this->assertSame(
                403,
                $exception->getStatusCode(),
            );
        }

        $this->assertSame(
            '10.000',
            $batch->fresh()->quantity_available,
        );

        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    private function createContext(string $suffix): array
    {
        $pharmacy = Pharmacy::create([
            'name' => "{$suffix} POS Pharmacy",
            'phone' => '+257 61 '.random_int(100000, 999999),
            'status' => 'approved',
        ]);

        $branch = PharmacyBranch::create([
            'pharmacy_id' => $pharmacy->id,
            'name' => "{$suffix} POS Branch",
            'code' => 'POS-'.strtoupper(
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
            'brand_name' => "{$suffix} POS Medicine",
            'approval_status' => 'approved',
            'is_active' => true,
        ]);

        $listing = PharmacyMedicine::create([
            'pharmacy_id' => $pharmacy->id,
            'medicine_id' => $medicine->id,
            'sku' => "SKU-{$suffix}",
            'selling_price' => 3500,
            'minimum_stock_level' => 0,
            'reorder_quantity' => 0,
            'expiry_warning_days' => 90,
            'alerts_enabled' => true,
            'status' => 'active',
        ]);

        return compact(
            'pharmacy',
            'branch',
            'owner',
            'medicine',
            'listing',
        );
    }

    private function createBatch(
        array $context,
        string $suffix,
        float $quantity,
        int $expiryDays,
    ): MedicineBatch {
        return MedicineBatch::create([
            'pharmacy_id' => $context['pharmacy']->id,
            'pharmacy_branch_id' => $context['branch']->id,
            'pharmacy_medicine_id' =>
                $context['listing']->id,
            'batch_number' => "LOT-{$suffix}",
            'expiry_date' => today()
                ->addDays($expiryDays),
            'unit_cost' => 2500,
            'quantity_received' => $quantity,
            'quantity_available' => $quantity,
            'status' => 'active',
            'received_at' => now(),
        ]);
    }
}