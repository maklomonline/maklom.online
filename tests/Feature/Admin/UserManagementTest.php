<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
        $this->regularUser = User::factory()->create();
    }

    public function test_admin_can_view_user_list(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.users.index'))
            ->assertOk();
    }

    public function test_non_admin_cannot_access_admin_panel(): void
    {
        $this->actingAs($this->regularUser)
            ->get(route('admin.users.index'))
            ->assertForbidden();
    }

    public function test_guest_cannot_access_admin_panel(): void
    {
        $this->get(route('admin.users.index'))->assertRedirect(route('login'));
    }

    public function test_admin_can_view_user_detail(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.users.show', $this->regularUser))
            ->assertOk();
    }

    public function test_admin_can_ban_user(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('admin.users.ban', $this->regularUser), [
                'ban_reason' => 'ละเมิดกฎการใช้งาน',
                'banned_until' => null,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('users', ['id' => $this->regularUser->id, 'is_banned' => true]);
        $this->assertDatabaseHas('admin_logs', [
            'admin_id' => $this->admin->id,
            'action' => 'ban_user',
            'target_id' => $this->regularUser->id,
        ]);
    }

    public function test_admin_can_unban_user(): void
    {
        $this->regularUser->update(['is_banned' => true, 'ban_reason' => 'Test']);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.users.unban', $this->regularUser));

        $response->assertRedirect();
        $this->assertDatabaseHas('users', ['id' => $this->regularUser->id, 'is_banned' => false]);
        $this->assertDatabaseHas('admin_logs', [
            'admin_id' => $this->admin->id,
            'action' => 'unban_user',
        ]);
    }

    public function test_admin_can_delete_user(): void
    {
        $response = $this->actingAs($this->admin)
            ->delete(route('admin.users.delete', $this->regularUser));

        $response->assertRedirect(route('admin.users.index'));
        $this->assertDatabaseMissing('users', ['id' => $this->regularUser->id]);
        $this->assertDatabaseHas('admin_logs', [
            'admin_id' => $this->admin->id,
            'action' => 'delete_user',
        ]);
    }

    public function test_admin_cannot_delete_themselves(): void
    {
        $this->actingAs($this->admin)
            ->delete(route('admin.users.delete', $this->admin))
            ->assertSessionHasErrors('user');
    }

    public function test_ban_requires_reason(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.users.ban', $this->regularUser), ['ban_reason' => ''])
            ->assertSessionHasErrors('ban_reason');
    }

    public function test_admin_can_search_users(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.users.index', ['search' => $this->regularUser->name]))
            ->assertOk()
            ->assertSee($this->regularUser->name);
    }
}
