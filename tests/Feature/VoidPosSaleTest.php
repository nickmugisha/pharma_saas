<?php

namespace Tests\Feature;

use App\Actions\Sales\CompletePosSale;
use App\Actions\Sales\VoidCompletedSale;
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

class VoidPosSaleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_void_restores_exact_batches_and_reverses_payments(): void
    {
        $context = $this->createContext('RESTORE');

        $early = $this->createBatch(
            $context,
            'EARLY',
            5,
            20,
        );

        $later = $this->createBatch(
            $context,
            'LATER',
            10,
            60,
        );

        $sale = $this->completeSale(
            $context,
            quantity: 8,
            payment: 30000,
        );

        $this->assertSame(
            '0.000',
            $early->fresh()->quantity_available,
        );

        $this->assertSame(
            '7.000',
            $later->fresh()->quantity_available,
        );

        $voidedSale = app(VoidCompletedSale::class)
            ->handle(
                $context['owner'],
                $sale,
                'Customer requested cancellation.',
            );

        $this->assertSame('voided', $voidedSale->status);
        $this->assertSame(
            'refunded',
            $voidedSale->payment_status,
        );

        $this->assertSame(
            '5.000',
            $early->fresh()->quantity_available,
        );

        $this->assertSame(
            '10.000',
            $later->fresh()->quantity_available,
        );

        $this->assertDatabaseHas('sale_voids', [
            'sale_id' => $sale->id,
            'voided_by_user_id' =>
                $context['owner']->id,
            'restored_quantity' => 8,
            'reversed_payment_amount' => 30000,
        ]);

        $this->assertDatabaseHas('sale_payments', [
            'sale_id' => $sale->id,
            'status' => 'voided',
        ]);

        $this->assertDatabaseCount(
            'sale_item_batches',
            2,
        );

        $this->assertSame(
            2,
            \App\Models\StockMovement::query()
                ->where('movement_type', 'sale_void')
                ->count(),
        );
    }

    public function test_sale_cannot_be_voided_twice(): void
    {
        $context = $this->createContext('TWICE');

        $batch = $this->createBatch(
            $context,
            'SINGLE',
            10,
            30,
        );

        $sale = $this->completeSale(
            $context,
            quantity: 2,
            payment: 7000,
        );

        app(VoidCompletedSale::class)->handle(
            $context['owner'],
            $sale,
            'First authorised cancellation.',
        );

        try {
            app(VoidCompletedSale::class)->handle(
                $context['owner'],
                $sale,
                'Second cancellation attempt.',
            );

            $this->fail(
                'The same sale was voided twice.'
            );
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey(
                'sale',
                $exception->errors(),
            );
        }

        $this->assertSame(
            '10.000',
            $batch->fresh()->quantity_available,
        );

        $this->assertDatabaseCount('sale_voids', 1);

        $this->assertSame(
            1,
            \App\Models\StockMovement::query()
                ->where('movement_type', 'sale_void')
                ->count(),
        );
    }

    public function test_user_cannot_void_another_pharmacys_sale(): void
    {
        $contextA = $this->createContext('VOID-A');
        $contextB = $this->createContext('VOID-B');

        $batchB = $this->createBatch(
            $contextB,
            'FOREIGN',
            10,
            30,
        );

        $saleB = $this->completeSale(
            $contextB,
            quantity: 2,
            payment: 7000,
        );

        try {
            app(VoidCompletedSale::class)->handle(
                $contextA['owner'],
                $saleB,
                'Invalid foreign sale cancellation.',
            );

            $this->fail(
                'A foreign pharmacy sale was voided.'
            );
        } catch (ModelNotFoundException) {
            $this->assertTrue(true);
        }

        $this->assertSame(
            '8.000',
            $batchB->fresh()->quantity_available,
        );

        $this->assertSame(
            'completed',
            $saleB->fresh()->status,
        );

        $this->assertDatabaseCount('sale_voids', 0);
    }

    public function test_sales_manager_without_void_permission_is_denied(): void
    {
        $context = $this->createContext('NO-VOID');

        $batch = $this->createBatch(
            $context,
            'PROTECTED',
            10,
            30,
        );

        $sale = $this->completeSale(
            $context,
            quantity: 2,
            payment: 7000,
        );

        $cashier = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        $cashier->forceFill([
            'pharmacy_id' =>
                $context['pharmacy']->id,

            'pharmacy_branch_id' =>
                $context['branch']->id,
        ])->save();

        $cashier->givePermissionTo([
            'sales.view',
            'sales.manage',
        ]);

        try {
            app(VoidCompletedSale::class)->handle(
                $cashier,
                $sale,
                'Cashier attempted sale cancellation.',
            );

            $this->fail(
                'A user without sales.void voided a sale.'
            );
        } catch (HttpException $exception) {
            $this->assertSame(
                403,
                $exception->getStatusCode(),
            );
        }

        $this->assertSame(
            '8.000',
            $batch->fresh()->quantity_available,
        );

        $this->assertSame(
            'completed',
            $sale->fresh()->status,
        );

        $this->assertDatabaseCount('sale_voids', 0);
    }

    private function completeSale(
        array $context,
        float $quantity,
        float $payment,
    ) {
        return app(CompletePosSale::class)->handle(
            $context['owner'],
            [[
                'pharmacy_medicine_id' =>
                    $context['listing']->id,

                'quantity' =>
                    $quantity,
            ]],
            [[
                'payment_method' =>
                    'cash',

                'amount' =>
                    $payment,
            ]],
        );
    }

    private function createContext(string $suffix): array
    {
        $pharmacy = Pharmacy::create([
            'name' => "{$suffix} Void Pharmacy",
            'phone' => '+257 61 '.random_int(
                100000,
                999999,
            ),
            'status' => 'approved',
        ]);

        $branch = PharmacyBranch::create([
            'pharmacy_id' => $pharmacy->id,
            'name' => "{$suffix} Void Branch",
            'code' => 'VOID-'.strtoupper(
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
            'brand_name' => "{$suffix} Void Medicine",
            'approval_status' => 'approved',
            'is_active' => true,
        ]);

        $listing = PharmacyMedicine::create([
            'pharmacy_id' => $pharmacy->id,
            'medicine_id' => $medicine->id,
            'sku' => "VOID-SKU-{$suffix}",
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
            'pharmacy_id' =>
                $context['pharmacy']->id,

            'pharmacy_branch_id' =>
                $context['branch']->id,

            'pharmacy_medicine_id' =>
                $context['listing']->id,

            'batch_number' =>
                "VOID-LOT-{$suffix}",

            'expiry_date' =>
                today()->addDays($expiryDays),

            'unit_cost' =>
                2500,

            'quantity_received' =>
                $quantity,

            'quantity_available' =>
                $quantity,

            'status' =>
                'active',

            'received_at' =>
                now(),
        ]);
    }
}