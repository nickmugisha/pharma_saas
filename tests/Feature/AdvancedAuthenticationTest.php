<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdvancedAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_mfa_storage_columns_exist(): void
    {
        $this->assertTrue(
            Schema::hasColumns('users', [
                'app_authentication_secret',
                'app_authentication_recovery_codes',
            ])
        );
    }

    public function test_verified_user_can_access_both_panels(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)
            ->get('/super-admin')
            ->assertOk();

        $this->actingAs($user)
            ->get('/pharmacy')
            ->assertOk();
    }

    public function test_unverified_user_is_sent_to_super_admin_verification_prompt(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
            'email_verified_at' => null,
        ]);

        $response = $this->actingAs($user)->get('/super-admin');

        $response->assertRedirect();

        $this->assertStringContainsString(
            '/super-admin/email-verification/prompt',
            $response->headers->get('Location')
        );
    }

    public function test_unverified_user_is_sent_to_pharmacy_verification_prompt(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
            'email_verified_at' => null,
        ]);

        $response = $this->actingAs($user)->get('/pharmacy');

        $response->assertRedirect();

        $this->assertStringContainsString(
            '/pharmacy/email-verification/prompt',
            $response->headers->get('Location')
        );
    }
}