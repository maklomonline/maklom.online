<?php

namespace App\Http\Controllers;

use App\Events\ChatMessageSent;
use App\Http\Requests\SendMessageRequest;
use App\Models\ChatMessage;
use App\Models\ChatRoom;

class ChatController extends Controller
{
    public function globalMessages()
    {
        $chatRoom = ChatRoom::forGlobal();
        $messages = $chatRoom->messages()->with('user')->visible()->latest()->take(100)->get()->reverse()->values();

        return view('chat.global', compact('chatRoom', 'messages'));
    }

    public function roomMessages(ChatRoom $chatRoom)
    {
        $messages = $chatRoom->messages()->with('user')->visible()->reorder()->latest()->take(100)->get()->reverse()->values();

        return response()->json($messages->map(fn($msg) => [
            'id' => $msg->id,
            'user' => ['id' => $msg->user?->id, 'name' => $msg->user?->getDisplayName(), 'avatar' => $msg->user?->getAvatarUrl(), 'rank' => $msg->user?->rank],
            'body' => $msg->body,
            'createdAt' => $msg->created_at->toISOString(),
        ]));
    }

    public function sendMessage(ChatRoom $chatRoom, SendMessageRequest $request)
    {
        $message = ChatMessage::create([
            'chat_room_id' => $chatRoom->id,
            'user_id' => $request->user()->id,
            'body' => $request->body,
        ]);

        $message->load('user');
        broadcast(new ChatMessageSent($message))->toOthers();

        return response()->json([
            'id' => $message->id,
            'user' => ['id' => $message->user->id, 'name' => $message->user->getDisplayName(), 'avatar' => $message->user->getAvatarUrl(), 'rank' => $message->user->rank],
            'body' => $message->body,
            'createdAt' => $message->created_at->toISOString(),
        ]);
    }

    public function deleteMessage(ChatMessage $message, \Illuminate\Http\Request $request)
    {
        $user = $request->user();
        if ($message->user_id !== $user->id && ! $user->is_admin) {
            abort(403);
        }

        $message->update(['is_deleted' => true]);

        return response()->json(['success' => true]);
    }
}
