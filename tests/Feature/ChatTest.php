<?php

namespace Tests\Feature;

use App\Events\ChatMessageSent;
use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ChatTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private ChatRoom $chatRoom;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->chatRoom = ChatRoom::forGlobal();
    }

    public function test_user_can_view_global_chat(): void
    {
        $this->actingAs($this->user)
            ->get(route('chat.global'))
            ->assertOk();
    }

    public function test_user_can_send_message(): void
    {
        Event::fake();

        $response = $this->actingAs($this->user)
            ->postJson(route('chat.send', $this->chatRoom), ['body' => 'สวัสดีครับ']);

        $response->assertOk();
        $this->assertDatabaseHas('chat_messages', [
            'chat_room_id' => $this->chatRoom->id,
            'user_id' => $this->user->id,
            'body' => 'สวัสดีครับ',
        ]);
        Event::assertDispatched(ChatMessageSent::class);
    }

    public function test_message_requires_body(): void
    {
        $this->actingAs($this->user)
            ->postJson(route('chat.send', $this->chatRoom), ['body' => ''])
            ->assertStatus(422);
    }

    public function test_user_can_delete_own_message(): void
    {
        Event::fake();

        $message = ChatMessage::create([
            'chat_room_id' => $this->chatRoom->id,
            'user_id' => $this->user->id,
            'body' => 'Delete me',
        ]);

        $this->actingAs($this->user)
            ->deleteJson(route('chat.delete', $message))
            ->assertOk();

        $this->assertDatabaseHas('chat_messages', ['id' => $message->id, 'is_deleted' => true]);
    }

    public function test_user_cannot_delete_others_message(): void
    {
        $other = User::factory()->create();

        $message = ChatMessage::create([
            'chat_room_id' => $this->chatRoom->id,
            'user_id' => $other->id,
            'body' => 'Not mine',
        ]);

        $this->actingAs($this->user)
            ->deleteJson(route('chat.delete', $message))
            ->assertForbidden();
    }

    public function test_admin_can_delete_any_message(): void
    {
        $admin = User::factory()->admin()->create();
        $other = User::factory()->create();

        $message = ChatMessage::create([
            'chat_room_id' => $this->chatRoom->id,
            'user_id' => $other->id,
            'body' => 'Removable',
        ]);

        $this->actingAs($admin)
            ->deleteJson(route('chat.delete', $message))
            ->assertOk();

        $this->assertDatabaseHas('chat_messages', ['id' => $message->id, 'is_deleted' => true]);
    }

    public function test_get_room_messages_returns_json(): void
    {
        ChatMessage::create([
            'chat_room_id' => $this->chatRoom->id,
            'user_id' => $this->user->id,
            'body' => 'Hello',
        ]);

        $this->actingAs($this->user)
            ->getJson(route('chat.messages', $this->chatRoom))
            ->assertOk()
            ->assertJsonCount(1);
    }

    public function test_deleted_messages_not_returned(): void
    {
        ChatMessage::create([
            'chat_room_id' => $this->chatRoom->id,
            'user_id' => $this->user->id,
            'body' => 'Visible',
        ]);
        ChatMessage::create([
            'chat_room_id' => $this->chatRoom->id,
            'user_id' => $this->user->id,
            'body' => 'Hidden',
            'is_deleted' => true,
        ]);

        $this->actingAs($this->user)
            ->getJson(route('chat.messages', $this->chatRoom))
            ->assertOk()
            ->assertJsonCount(1);
    }
}
