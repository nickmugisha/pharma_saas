<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserAdministrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_super_admin_can_open_user_management_pages(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $user->assignRole('super_admin');

        $this->actingAs($user)
            ->get('/super-admin/users')
            ->assertOk();

        $this->actingAs($user)
            ->get('/super-admin/users/create')
            ->assertOk();
    }

    public function test_support_agent_can_view_users_but_cannot_create_them(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $user->assignRole('support_agent');

        $this->actingAs($user)
            ->get('/super-admin/users')
            ->assertOk();

        $this->actingAs($user)
            ->get('/super-admin/users/create')
            ->assertForbidden();
    }

    public function test_pharmacy_account_cannot_access_platform_user_management(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $user->assignRole('pharmacy_owner');

        $this->actingAs($user)
            ->get('/super-admin/users')
            ->assertForbidden();
    }
}