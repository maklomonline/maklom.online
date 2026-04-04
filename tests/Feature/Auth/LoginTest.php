<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_is_accessible_to_guests(): void
    {
        $this->get(route('login'))->assertOk();
    }

    public function test_user_can_login_with_correct_credentials(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password123')]);

        $response = $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('lobby'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_user_cannot_login_with_wrong_password(): void
    {
        $user = User::factory()->create(['password' => bcrypt('correct')]);

        $response = $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'wrong',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_user_can_logout(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('logout'))->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_banned_user_is_redirected_after_login(): void
    {
        $user = User::factory()->banned()->create(['password' => bcrypt('password123')]);

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        // Banned middleware should kick them out when accessing protected routes
        $response = $this->actingAs($user)->get(route('lobby'));
        $response->assertRedirect(route('login'));
    }
}
