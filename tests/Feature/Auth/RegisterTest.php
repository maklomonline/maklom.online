<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_page_is_accessible_to_guests(): void
    {
        $this->get(route('register'))->assertOk();
    }

    public function test_authenticated_user_cannot_access_register(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('register'))->assertRedirect();
    }

    public function test_user_can_register(): void
    {
        Event::fake();

        $response = $this->post(route('register'), [
            'name' => 'Test User',
            'username' => 'testuser',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('verification.notice'));
        $this->assertDatabaseHas('users', ['email' => 'test@example.com', 'username' => 'testuser']);
        $this->assertAuthenticated();
        Event::assertDispatched(Registered::class);
    }

    public function test_user_stat_created_on_register(): void
    {
        Event::fake();

        $this->post(route('register'), [
            'name' => 'Test User',
            'username' => 'statuser',
            'email' => 'stat@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $user = User::where('email', 'stat@example.com')->first();
        $this->assertNotNull($user->stats);
    }

    public function test_register_requires_unique_email(): void
    {
        User::factory()->create(['email' => 'existing@example.com']);

        $response = $this->post(route('register'), [
            'name' => 'Test User',
            'username' => 'newuser',
            'email' => 'existing@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_register_requires_unique_username(): void
    {
        User::factory()->create(['username' => 'takenuser']);

        $response = $this->post(route('register'), [
            'name' => 'Test User',
            'username' => 'takenuser',
            'email' => 'unique@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertSessionHasErrors('username');
    }

    public function test_register_requires_password_confirmation(): void
    {
        $response = $this->post(route('register'), [
            'name' => 'Test',
            'username' => 'someuser',
            'email' => 'some@example.com',
            'password' => 'password123',
            'password_confirmation' => 'wrong',
        ]);

        $response->assertSessionHasErrors('password');
    }
}
