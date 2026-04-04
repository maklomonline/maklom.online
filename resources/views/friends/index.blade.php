@extends('layouts.app')
@section('title', 'เพื่อน')
@section('content')
<div x-data="{ tab: 'friends' }">
    <h1 class="text-xl font-bold text-gray-900 mb-4">เพื่อน</h1>

    <div class="flex gap-2 mb-6">
        @foreach(['friends' => 'เพื่อน (' . $friends->count() . ')', 'pending' => 'คำขอ (' . $pending->count() . ')', 'blocked' => 'บล็อก'] as $key => $label)
        <button @click="tab = '{{ $key }}'"
            :class="tab === '{{ $key }}' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50'"
            class="px-4 py-1.5 rounded-lg text-sm transition">{{ $label }}</button>
        @endforeach
    </div>

    {{-- Friends Tab --}}
    <div x-show="tab === 'friends'">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
            @forelse($friends as $friend)
            <div class="bg-white rounded-lg border border-gray-200 p-4 flex items-center gap-3">
                <img src="{{ $friend->getAvatarUrl() }}" class="w-10 h-10 rounded-full object-cover">
                <div class="flex-1 min-w-0">
                    <div class="font-medium text-sm truncate">{{ $friend->getDisplayName() }}</div>
                    <div class="text-xs text-gray-400">{{ $friend->rank }} · {{ $friend->last_seen_at?->diffForHumans() ?? 'ไม่ทราบ' }}</div>
                </div>
                <div class="flex gap-1">
                    <a href="{{ route('profile.show', $friend->username) }}" class="text-xs text-indigo-600 hover:underline">โปรไฟล์</a>
                    <form method="POST" action="{{ route('friends.remove', $friend) }}" class="ml-1">
                        @csrf @method('DELETE')
                        <button type="submit" class="text-xs text-red-400 hover:text-red-600">ลบ</button>
                    </form>
                </div>
            </div>
            @empty
            <div class="col-span-3 text-center text-gray-400 py-8">ยังไม่มีเพื่อน</div>
            @endforelse
        </div>
    </div>

    {{-- Pending Tab --}}
    <div x-show="tab === 'pending'" class="space-y-2">
        @forelse($pending as $friendship)
        <div class="bg-white rounded-lg border border-gray-200 p-4 flex items-center gap-3">
            <img src="{{ $friendship->requester->getAvatarUrl() }}" class="w-10 h-10 rounded-full">
            <div class="flex-1">
                <div class="font-medium text-sm">{{ $friendship->requester->getDisplayName() }}</div>
                <div class="text-xs text-gray-400">{{ $friendship->created_at->diffForHumans() }}</div>
            </div>
            <div class="flex gap-2">
                <form method="POST" action="{{ route('friends.accept', $friendship) }}">
                    @csrf
                    <button class="text-xs bg-indigo-600 text-white px-3 py-1 rounded hover:bg-indigo-700 transition">ยอมรับ</button>
                </form>
                <form method="POST" action="{{ route('friends.decline', $friendship) }}">
                    @csrf
                    <button class="text-xs bg-gray-100 text-gray-700 px-3 py-1 rounded hover:bg-gray-200 transition">ปฏิเสธ</button>
                </form>
            </div>
        </div>
        @empty
        <div class="text-center text-gray-400 py-8">ไม่มีคำขอรอดำเนินการ</div>
        @endforelse
    </div>

    {{-- Blocked Tab --}}
    <div x-show="tab === 'blocked'" class="space-y-2">
        @forelse($blocked as $friendship)
        <div class="bg-white rounded-lg border border-gray-200 p-4 flex items-center gap-3">
            <div class="flex-1">
                <div class="font-medium text-sm">{{ $friendship->addressee->getDisplayName() }}</div>
            </div>
            <form method="POST" action="{{ route('friends.unblock', $friendship->addressee) }}">
                @csrf
                <button class="text-xs text-indigo-600 hover:underline">ยกเลิกบล็อก</button>
            </form>
        </div>
        @empty
        <div class="text-center text-gray-400 py-8">ไม่มีรายการบล็อก</div>
        @endforelse
    </div>
</div>
@endsection
