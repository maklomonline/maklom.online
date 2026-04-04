@extends('layouts.app')
@section('title', 'การแจ้งเตือน')
@section('content')
<div class="max-w-2xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-xl font-bold text-gray-900">การแจ้งเตือน</h1>
        <form method="POST" action="{{ route('notifications.read-all') }}">
            @csrf @method('PATCH')
            <button class="text-sm text-indigo-600 hover:underline">อ่านทั้งหมด</button>
        </form>
    </div>

    <div class="space-y-2">
        @forelse($notifications as $n)
        <div class="bg-white rounded-lg border border-gray-200 p-4 flex gap-3 {{ $n->isRead() ? 'opacity-60' : '' }}">
            <div class="text-2xl flex-shrink-0">
                @switch($n->type)
                    @case('friend_request') 👋 @break
                    @case('game_invite') 🎮 @break
                    @case('challenge') ⚔️ @break
                    @case('challenge_accepted') ✅ @break
                    @case('challenge_declined') ❌ @break
                    @case('mention') 💬 @break
                    @default 🔔 @break
                @endswitch
            </div>
            <div class="flex-1">
                <p class="text-sm font-medium text-gray-800">{{ $n->title }}</p>
                @if($n->body)<p class="text-xs text-gray-500 mt-0.5">{{ $n->body }}</p>@endif
                <p class="text-xs text-gray-400 mt-1">{{ $n->created_at->diffForHumans() }}</p>

                @if($n->type === 'challenge' && !empty($n->data['challenge_id']))
                <div x-data="{ resolved: false }" class="flex gap-2 mt-2">
                    <template x-if="!resolved">
                        <div class="flex gap-2">
                            <button @click="
                                axios.post('/challenges/{{ $n->data['challenge_id'] }}/accept')
                                    .then(r => { resolved = true; if(r.data.game_url) window.location = r.data.game_url; })
                                    .catch(e => alert(e.response?.data?.error || 'เกิดข้อผิดพลาด'))
                            " class="text-xs bg-gray-900 text-white px-3 py-1 rounded-md font-semibold">
                                รับ
                            </button>
                            <button @click="
                                axios.post('/challenges/{{ $n->data['challenge_id'] }}/decline')
                                    .then(() => resolved = true)
                                    .catch(() => {})
                            " class="text-xs bg-gray-100 text-gray-700 px-3 py-1 rounded-md font-semibold">
                                ปฏิเสธ
                            </button>
                        </div>
                    </template>
                    <template x-if="resolved">
                        <p class="text-xs text-gray-400 italic">ตอบกลับแล้ว</p>
                    </template>
                </div>
                @endif

                @if($n->type === 'challenge_accepted' && !empty($n->data['game_url']))
                <a href="{{ $n->data['game_url'] }}" class="text-xs text-indigo-600 font-semibold mt-1 inline-block">ไปยังเกม →</a>
                @endif
            </div>
            @if(!$n->isRead())
            <div class="w-2 h-2 bg-indigo-500 rounded-full shrink-0 mt-2"></div>
            @endif
        </div>
        @empty
        <div class="text-center text-gray-400 py-8">ไม่มีการแจ้งเตือน</div>
        @endforelse
    </div>
    {{ $notifications->links() }}
</div>
@endsection
