<?php

namespace Tests\Feature;

use App\Models\Pharmacy;
use App\Models\PharmacyBranch;
use App\Models\StaffManagementEvent;
use App\Models\User;
use App\Services\StaffRecruitmentService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StaffRecruitmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_owner_can_recruit_cashier_in_own_pharmacy(): void
    {
        $context = $this->context('OWNER');

        $cashier = app(StaffRecruitmentService::class)->create(
            $context['owner'],
            $this->staffData(
                email: 'cashier.owner@example.test',
                role: 'cashier',
                branchId: $context['branchA']->id,
            ),
        );

        $this->assertSame($context['pharmacy']->id, $cashier->pharmacy_id);
        $this->assertSame($context['branchA']->id, $cashier->pharmacy_branch_id);
        $this->assertTrue($cashier->hasRole('cashier'));
        $this->assertTrue($cashier->is_active);
        $this->assertDatabaseHas('staff_management_events', [
            'target_user_id' => $cashier->id,
            'event_type' => 'staff_recruited',
            'new_role' => 'cashier',
        ]);
    }

    public function test_branch_manager_can_recruit_only_inside_assigned_branch(): void
    {
        $context = $this->context('BRANCH');

        $cashier = app(StaffRecruitmentService::class)->create(
            $context['manager'],
            $this->staffData(
                email: 'cashier.branch@example.test',
                role: 'cashier',
                branchId: $context['branchA']->id,
            ),
        );

        $this->assertSame($context['branchA']->id, $cashier->pharmacy_branch_id);

        $this->expectException(ValidationException::class);

        app(StaffRecruitmentService::class)->create(
            $context['manager'],
            $this->staffData(
                email: 'foreign.branch@example.test',
                role: 'cashier',
                branchId: $context['branchB']->id,
            ),
        );
    }

    public function test_branch_manager_cannot_create_another_manager(): void
    {
        $context = $this->context('PRIVILEGE');

        $this->expectException(ValidationException::class);

        app(StaffRecruitmentService::class)->create(
            $context['manager'],
            $this->staffData(
                email: 'manager.two@example.test',
                role: 'branch_manager',
                branchId: $context['branchA']->id,
            ),
        );
    }

    public function test_owner_cannot_manage_staff_from_another_pharmacy(): void
    {
        $contextA = $this->context('ALPHA');
        $contextB = $this->context('BRAVO');

        $foreignCashier = app(StaffRecruitmentService::class)->create(
            $contextB['owner'],
            $this->staffData(
                email: 'foreign.cashier@example.test',
                role: 'cashier',
                branchId: $contextB['branchA']->id,
            ),
        );

        $this->expectException(AuthorizationException::class);

        app(StaffRecruitmentService::class)->setActive(
            actor: $contextA['owner'],
            target: $foreignCashier,
            active: false,
        );
    }

    public function test_deactivation_is_audited_and_blocks_panel_access(): void
    {
        $context = $this->context('STATUS');

        $cashier = app(StaffRecruitmentService::class)->create(
            $context['owner'],
            $this->staffData(
                email: 'status.cashier@example.test',
                role: 'cashier',
                branchId: $context['branchA']->id,
            ),
        );

        app(StaffRecruitmentService::class)->setActive(
            actor: $context['owner'],
            target: $cashier,
            active: false,
            reason: 'Employment ended.',
        );

        $this->assertFalse((bool) $cashier->fresh()->is_active);
        $this->assertDatabaseHas('staff_management_events', [
            'target_user_id' => $cashier->id,
            'event_type' => 'staff_deactivated',
            'reason' => 'Employment ended.',
        ]);
    }

    private function context(string $suffix): array
    {
        $pharmacy = Pharmacy::create([
            'name' => "{$suffix} Recruitment Pharmacy",
            'phone' => '+257 79 000 000',
            'status' => 'approved',
        ]);

        $branchA = PharmacyBranch::create([
            'pharmacy_id' => $pharmacy->id,
            'name' => "{$suffix} Main Branch",
            'code' => "{$suffix}-MAIN",
            'is_main' => true,
            'status' => 'active',
        ]);

        $branchB = PharmacyBranch::create([
            'pharmacy_id' => $pharmacy->id,
            'name' => "{$suffix} Second Branch",
            'code' => "{$suffix}-SECOND",
            'is_main' => false,
            'status' => 'active',
        ]);

        $owner = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
        $owner->forceFill([
            'pharmacy_id' => $pharmacy->id,
            'pharmacy_branch_id' => $branchA->id,
        ])->save();
        $owner->assignRole('pharmacy_owner');

        $manager = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
        $manager->forceFill([
            'pharmacy_id' => $pharmacy->id,
            'pharmacy_branch_id' => $branchA->id,
        ])->save();
        $manager->assignRole('branch_manager');

        return compact(
            'pharmacy',
            'branchA',
            'branchB',
            'owner',
            'manager',
        );
    }

    private function staffData(
        string $email,
        string $role,
        int $branchId,
    ): array {
        return [
            'name' => 'Demo Employee',
            'email' => $email,
            'phone' => '+257 79 123 456',
            'job_title' => 'Pharmacy employee',
            'hired_at' => today()->toDateString(),
            'pharmacy_branch_id' => $branchId,
            'staff_role' => $role,
            'is_active' => true,
            'password' => 'SecureDemoPassword2026!',
            'password_confirmation' => 'SecureDemoPassword2026!',
            'reason' => 'Automated recruitment test.',
        ];
    }
}
