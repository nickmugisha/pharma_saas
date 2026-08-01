<?php

namespace Tests\Feature;

use App\Actions\Sales\VoidCompletedSale;
use App\Models\Pharmacy;
use App\Models\PharmacyBranch;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosSaleVoidInterfaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_owner_sees_void_action_and_void_record_details(): void
    {
        $context = $this->createContext('OWNER');

        $this->actingAs($context['owner'])
            ->get(
                "/pharmacy/sales/{$context['sale']->id}"
            )
            ->assertOk()
            ->assertSee('Void sale');

        $voidedSale = app(VoidCompletedSale::class)
            ->handle(
                $context['owner'],
                $context['sale'],
                'Customer requested cancellation after checkout.',
            );

        $void = $voidedSale
            ->voidRecord()
            ->firstOrFail();

        $this->actingAs($context['owner'])
            ->get(
                "/pharmacy/sales/{$context['sale']->id}"
            )
            ->assertOk()
            ->assertSee($void->void_number)
            ->assertSee(
                'Customer requested cancellation after checkout.'
            )
            ->assertDontSee('Void sale');
    }

    public function test_user_without_void_permission_does_not_see_action(): void
    {
        $context = $this->createContext('CASHIER');

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

        $this->actingAs($cashier)
            ->get(
                "/pharmacy/sales/{$context['sale']->id}"
            )
            ->assertOk()
            ->assertDontSee('Void sale');

        $this->assertSame(
            'completed',
            $context['sale']->fresh()->status,
        );
    }

    private function createContext(
        string $suffix,
    ): array {
        $pharmacy = Pharmacy::create([
            'name' => "{$suffix} Void UI Pharmacy",
            'phone' => '+257 61 '.random_int(
                100000,
                999999,
            ),
            'status' => 'approved',
        ]);

        $branch = PharmacyBranch::create([
            'pharmacy_id' => $pharmacy->id,
            'name' => "{$suffix} Void UI Branch",
            'code' => 'VUI-'.strtoupper(
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

        $sale = Sale::create([
            'pharmacy_id' => $pharmacy->id,
            'pharmacy_branch_id' => $branch->id,
            'cashier_user_id' => $owner->id,
            'sale_number' => "VUI-SALE-{$suffix}",
            'receipt_number' => "VUI-RCT-{$suffix}",
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
        ]);

        SalePayment::create([
            'pharmacy_id' => $pharmacy->id,
            'sale_id' => $sale->id,
            'received_by_user_id' => $owner->id,
            'payment_number' => "VUI-PAY-{$suffix}",
            'paid_at' => now(),
            'amount' => 7000,
            'payment_method' => 'cash',
            'status' => 'completed',
        ]);

        return compact(
            'pharmacy',
            'branch',
            'owner',
            'sale',
        );
    }
}