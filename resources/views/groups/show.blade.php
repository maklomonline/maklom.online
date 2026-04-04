@extends('layouts.app')
@section('title', $group->name)
@section('content')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2">
        <div class="bg-white rounded-xl border border-gray-200 p-6 mb-4">
            <div class="flex items-start justify-between">
                <div class="flex items-center gap-4">
                    <img src="{{ $group->getAvatarUrl() }}" class="w-16 h-16 rounded-xl object-cover">
                    <div>
                        <h1 class="text-xl font-bold text-gray-900">{{ $group->name }}</h1>
                        <p class="text-sm text-gray-500">เจ้าของ: {{ $group->owner->getDisplayName() }} · {{ $group->members_count }} สมาชิก</p>
                    </div>
                </div>
                <div class="flex gap-2">
                    @auth
                    @if($group->isMember(auth()->user()))
                        @if(auth()->id() !== $group->owner_id)
                        <form method="POST" action="{{ route('groups.leave', $group) }}">
                            @csrf
                            <button class="text-sm text-red-600 border border-red-200 px-3 py-1 rounded-lg hover:bg-red-50 transition">ออกจากกลุ่ม</button>
                        </form>
                        @endif
                        @if(auth()->id() === $group->owner_id || auth()->user()->is_admin)
                        <a href="{{ route('groups.edit', $group) }}" class="text-sm text-gray-600 border border-gray-200 px-3 py-1 rounded-lg hover:bg-gray-50 transition">แก้ไข</a>
                        <form method="POST" action="{{ route('groups.destroy', $group) }}"
                            onsubmit="return confirm('ยืนยันการลบกลุ่ม « {{ $group->name }} » ? การกระทำนี้ไม่สามารถย้อนกลับได้')">
                            @csrf
                            @method('DELETE')
                            <button class="text-sm text-red-600 border border-red-200 px-3 py-1 rounded-lg hover:bg-red-50 transition">ลบกลุ่ม</button>
                        </form>
                        @endif
                    @else
                        @if($group->is_public)
                        <form method="POST" action="{{ route('groups.join', $group) }}">
                            @csrf
                            <button class="text-sm bg-indigo-600 text-white px-4 py-1 rounded-lg hover:bg-indigo-700 transition">เข้าร่วม</button>
                        </form>
                        @endif
                    @endif
                    @endauth
                </div>
            </div>
            @if($group->description)
            <p class="text-sm text-gray-600 mt-4">{{ $group->description }}</p>
            @endif
        </div>

        {{-- Group Chat --}}
        @if($chatRoom)
        <div class="bg-white rounded-xl border border-gray-200 flex flex-col"
            x-data="chatWindow({{ $chatRoom->id }})">
            <div class="p-4 border-b border-gray-100 font-semibold">แชทกลุ่ม</div>
            <div class="h-72 overflow-y-auto p-4 space-y-3" x-ref="msgContainer">
                <template x-for="msg in messages" :key="msg.id">
                    <div class="flex items-start gap-2">
                        <img :src="msg.user?.avatar" class="w-7 h-7 rounded-full flex-shrink-0">
                        <div>
                            <span class="text-xs font-medium text-gray-700" x-text="msg.user?.name"></span>
                            <span class="text-xs text-gray-400 ml-1" x-text="'[' + (msg.user?.rank || '?') + ']'"></span>
                            <p class="text-sm text-gray-800 mt-0.5" x-text="msg.body"></p>
                        </div>
                    </div>
                </template>
            </div>
            @auth
            <form @submit.prevent="sendMessage()" class="p-3 border-t border-gray-100 flex gap-2">
                <input x-model="draft" type="text" placeholder="พิมพ์ข้อความ..."
                    class="flex-1 border border-gray-200 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-indigo-400">
                <button class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-indigo-700 transition">ส่ง</button>
            </form>
            @endauth
        </div>
        @endif
    </div>

    {{-- Members Sidebar --}}
    <div class="bg-white rounded-xl border border-gray-200 p-4">
        <div class="flex justify-between items-center mb-3">
            <h3 class="font-semibold">สมาชิก</h3>
            <a href="{{ route('groups.members', $group) }}" class="text-xs text-indigo-600 hover:underline">ดูทั้งหมด</a>
        </div>
        <div class="space-y-2">
            @foreach($members->take(10) as $member)
            <div class="flex items-center gap-2">
                <img src="{{ $member->getAvatarUrl() }}" class="w-8 h-8 rounded-full object-cover">
                <div class="flex-1 min-w-0">
                    <div class="text-sm font-medium truncate">{{ $member->getDisplayName() }}</div>
                    <div class="text-xs text-gray-400">{{ $member->pivot->role === 'owner' ? '👑 เจ้าของ' : ($member->pivot->role === 'moderator' ? '🛡️ ผู้ดูแล' : 'สมาชิก') }}</div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</div>
@endsection
