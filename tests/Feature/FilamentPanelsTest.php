<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FilamentPanelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_login_page_is_displayed(): void
    {
        $this->get('/super-admin/login')
            ->assertOk()
            ->assertSee('Sign in')
            ->assertSee('pharma-saas-super-admin.svg', false);
    }

    public function test_pharmacy_login_page_is_displayed(): void
    {
        $this->get('/pharmacy/login')
            ->assertOk()
            ->assertSee('Sign in')
            ->assertSee('pharma-saas-pharmacy.svg', false);
    }

    public function test_unauthenticated_user_is_redirected_from_super_admin_dashboard(): void
    {
        $this->get('/super-admin')
            ->assertRedirect('/super-admin/login');
    }

    public function test_unauthenticated_user_is_redirected_from_pharmacy_dashboard(): void
    {
        $this->get('/pharmacy')
            ->assertRedirect('/pharmacy/login');
    }

    public function test_authenticated_user_can_access_both_panels_for_now(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/super-admin')
            ->assertOk();

        $this->actingAs($user)
            ->get('/pharmacy')
            ->assertOk();
    }
}