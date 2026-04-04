<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroupTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_user_can_view_groups_list(): void
    {
        $this->actingAs($this->user)
            ->get(route('groups.index'))
            ->assertOk();
    }

    public function test_user_can_create_group(): void
    {
        $response = $this->actingAs($this->user)->post(route('groups.store'), [
            'name' => 'Test Group',
            'description' => 'A test group',
            'is_public' => true,
            'max_members' => 50,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('groups', ['name' => 'Test Group', 'owner_id' => $this->user->id]);

        // Creator should be member with owner role
        $group = Group::where('name', 'Test Group')->first();
        $this->assertDatabaseHas('group_members', [
            'group_id' => $group->id,
            'user_id' => $this->user->id,
            'role' => 'owner',
        ]);
    }

    public function test_user_can_join_public_group(): void
    {
        $owner = User::factory()->create();
        $group = Group::factory()->create(['owner_id' => $owner->id, 'is_public' => true]);
        $group->members()->attach($owner->id, ['role' => 'owner', 'joined_at' => now()]);

        $this->actingAs($this->user)
            ->post(route('groups.join', $group))
            ->assertRedirect();

        $this->assertDatabaseHas('group_members', ['group_id' => $group->id, 'user_id' => $this->user->id]);
    }

    public function test_user_cannot_join_private_group(): void
    {
        $owner = User::factory()->create();
        $group = Group::factory()->create(['owner_id' => $owner->id, 'is_public' => false]);

        $this->actingAs($this->user)
            ->post(route('groups.join', $group))
            ->assertSessionHasErrors('group');
    }

    public function test_user_cannot_join_full_group(): void
    {
        $owner = User::factory()->create();
        $group = Group::factory()->create([
            'owner_id' => $owner->id,
            'is_public' => true,
            'max_members' => 1,
        ]);
        $group->members()->attach($owner->id, ['role' => 'owner', 'joined_at' => now()]);

        $this->actingAs($this->user)
            ->post(route('groups.join', $group))
            ->assertSessionHasErrors('group');
    }

    public function test_member_can_leave_group(): void
    {
        $owner = User::factory()->create();
        $group = Group::factory()->create(['owner_id' => $owner->id]);
        $group->members()->attach($this->user->id, ['role' => 'member', 'joined_at' => now()]);

        $this->actingAs($this->user)
            ->post(route('groups.leave', $group))
            ->assertRedirect();

        $this->assertDatabaseMissing('group_members', ['group_id' => $group->id, 'user_id' => $this->user->id]);
    }

    public function test_owner_cannot_leave_group(): void
    {
        $group = Group::factory()->create(['owner_id' => $this->user->id]);
        $group->members()->attach($this->user->id, ['role' => 'owner', 'joined_at' => now()]);

        $this->actingAs($this->user)
            ->post(route('groups.leave', $group))
            ->assertSessionHasErrors('group');
    }

    public function test_owner_can_destroy_group(): void
    {
        $group = Group::factory()->create(['owner_id' => $this->user->id]);
        $group->members()->attach($this->user->id, ['role' => 'owner', 'joined_at' => now()]);

        $this->actingAs($this->user)
            ->delete(route('groups.destroy', $group))
            ->assertRedirect(route('groups.index'));

        $this->assertDatabaseMissing('groups', ['id' => $group->id]);
    }

    public function test_non_owner_cannot_destroy_group(): void
    {
        $owner = User::factory()->create();
        $group = Group::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($this->user)
            ->delete(route('groups.destroy', $group))
            ->assertForbidden();
    }

    public function test_admin_can_kick_member(): void
    {
        $owner = User::factory()->create();
        $group = Group::factory()->create(['owner_id' => $owner->id]);
        $member = User::factory()->create();
        $group->members()->attach($owner->id, ['role' => 'owner', 'joined_at' => now()]);
        $group->members()->attach($member->id, ['role' => 'member', 'joined_at' => now()]);

        // Owner is admin of group
        $this->actingAs($owner)
            ->delete(route('groups.kick', [$group, $member]))
            ->assertRedirect();

        $this->assertDatabaseMissing('group_members', ['group_id' => $group->id, 'user_id' => $member->id]);
    }
}
