<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolePermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_expected_roles_are_created(): void
    {
        $expectedRoles = [
            'super_admin',
            'platform_admin',
            'compliance_officer',
            'finance_manager',
            'support_agent',
            'pharmacy_owner',
            'branch_manager',
            'pharmacist',
            'pharmacy_assistant',
            'stock_manager',
            'cashier',
            'accountant',
            'delivery_coordinator',
        ];

        foreach ($expectedRoles as $role) {
            $this->assertDatabaseHas('roles', [
                'name' => $role,
                'guard_name' => 'web',
            ]);
        }
    }

    public function test_super_admin_receives_every_permission(): void
    {
        $role = Role::findByName('super_admin');

        $this->assertSame(
            Permission::count(),
            $role->permissions()->count(),
        );
    }

    public function test_user_can_receive_a_role_and_its_permissions(): void
    {
        $user = User::factory()->create();

        $user->assignRole('pharmacist');

        $this->assertTrue($user->hasRole('pharmacist'));
        $this->assertTrue($user->can('prescriptions.validate'));
        $this->assertFalse($user->can('platform.roles.manage'));
    }

    public function test_pharmacy_roles_do_not_receive_platform_administration(): void
    {
        $pharmacyOwner = Role::findByName('pharmacy_owner');

        $this->assertFalse(
            $pharmacyOwner->hasPermissionTo('platform.roles.manage'),
        );

        $this->assertTrue(
            $pharmacyOwner->hasPermissionTo('stock.manage'),
        );
    }
}