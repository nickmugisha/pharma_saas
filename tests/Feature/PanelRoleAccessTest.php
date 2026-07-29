<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PanelRoleAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_platform_user_can_only_access_super_admin_panel(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $user->assignRole('super_admin');

        $this->actingAs($user)
            ->get('/super-admin')
            ->assertOk();

        $this->actingAs($user)
            ->get('/pharmacy')
            ->assertForbidden();
    }

    public function test_pharmacy_user_can_only_access_pharmacy_panel(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $user->assignRole('pharmacy_owner');

        $this->actingAs($user)
            ->get('/pharmacy')
            ->assertOk();

        $this->actingAs($user)
            ->get('/super-admin')
            ->assertForbidden();
    }

    public function test_inactive_user_remains_blocked_even_with_a_valid_role(): void
    {
        $user = User::factory()->create([
            'is_active' => false,
            'email_verified_at' => now(),
        ]);

        $user->assignRole('super_admin');

        $this->actingAs($user)
            ->get('/super-admin')
            ->assertForbidden();
    }
}