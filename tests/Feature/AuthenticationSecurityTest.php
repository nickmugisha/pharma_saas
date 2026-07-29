<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_user_can_access_both_panels_for_now(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/super-admin')
            ->assertOk();

        $this->actingAs($user)
            ->get('/pharmacy')
            ->assertOk();
    }

    public function test_inactive_user_is_forbidden_from_both_panels(): void
    {
        $user = User::factory()->create([
            'is_active' => false,
        ]);

        $this->actingAs($user)
            ->get('/super-admin')
            ->assertForbidden();

        $this->actingAs($user)
            ->get('/pharmacy')
            ->assertForbidden();
    }

    public function test_login_event_is_recorded_only_once(): void
    {
        $user = User::factory()->create();

        event(new Login('web', $user, false));

        $this->assertDatabaseCount('login_histories', 1);

        $this->assertDatabaseHas('login_histories', [
            'user_id' => $user->id,
            'email' => $user->email,
            'event' => 'login_success',
        ]);

        $this->assertNotNull($user->refresh()->last_login_at);
    }
}