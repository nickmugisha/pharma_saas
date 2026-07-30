<?php

namespace Tests\Feature;

use App\Models\Pharmacy;
use App\Models\PharmacyBranch;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PharmacyBranchIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_pharmacy_owner_can_only_access_branches_from_own_pharmacy(): void
    {
        $pharmacyA = $this->createPharmacy(
            'Pharmacy A',
            '+257 61 00 00 01',
        );

        $pharmacyB = $this->createPharmacy(
            'Pharmacy B',
            '+257 61 00 00 02',
        );

        $ownerA = $this->createPharmacyUser(
            'owner-a@example.test',
            'pharmacy_owner',
            $pharmacyA,
        );

        $branchA = PharmacyBranch::create([
            'pharmacy_id' => $pharmacyA->id,
            'name' => 'Branch A',
            'code' => 'A-HQ',
            'is_main' => true,
            'status' => 'active',
        ]);

        $branchB = PharmacyBranch::create([
            'pharmacy_id' => $pharmacyB->id,
            'name' => 'Branch B',
            'code' => 'B-HQ',
            'is_main' => true,
            'status' => 'active',
        ]);

        $this->actingAs($ownerA)
            ->get('/pharmacy/pharmacy-branches')
            ->assertOk()
            ->assertSee('Branch A')
            ->assertDontSee('Branch B');

        $this->actingAs($ownerA)
            ->get("/pharmacy/pharmacy-branches/{$branchA->id}/edit")
            ->assertOk();

        $this->actingAs($ownerA)
            ->get("/pharmacy/pharmacy-branches/{$branchB->id}/edit")
            ->assertNotFound();
    }

    public function test_user_without_branch_permission_cannot_manage_branches(): void
    {
        $pharmacy = $this->createPharmacy(
            'Restricted Pharmacy',
            '+257 61 00 00 03',
        );

        $cashier = $this->createPharmacyUser(
            'cashier@example.test',
            'cashier',
            $pharmacy,
        );

        $this->assertFalse($cashier->can('branches.manage'));

        $this->actingAs($cashier)
            ->get('/pharmacy/pharmacy-branches')
            ->assertForbidden();

        $this->actingAs($cashier)
            ->get('/pharmacy/pharmacy-branches/create')
            ->assertForbidden();
    }

    private function createPharmacy(
        string $name,
        string $phone,
    ): Pharmacy {
        return Pharmacy::create([
            'name' => $name,
            'phone' => $phone,
            'status' => 'approved',
        ]);
    }

    private function createPharmacyUser(
        string $email,
        string $role,
        Pharmacy $pharmacy,
    ): User {
        $user = User::factory()->create([
            'email' => $email,
            'email_verified_at' => now(),
        ]);

        $user->forceFill([
            'pharmacy_id' => $pharmacy->id,
        ])->save();

        $user->assignRole($role);

        return $user;
    }
}