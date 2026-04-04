@extends('layouts.app')
@section('title', 'แชทสาธารณะ')
@section('content')
<div class="max-w-2xl mx-auto">
    <h1 class="text-xl font-bold text-gray-900 mb-4">แชทสาธารณะ</h1>
    <div class="bg-white rounded-xl border border-gray-200 flex flex-col" style="height: 70vh"
        x-data="chatWindow({{ $chatRoom->id }})">
        <div class="flex-1 overflow-y-auto p-4 space-y-3" x-ref="msgContainer">
            <template x-for="msg in messages" :key="msg.id">
                <div class="flex items-start gap-2">
                    <img :src="msg.user?.avatar || '/default-avatar.png'" class="w-8 h-8 rounded-full flex-shrink-0">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-medium text-gray-800" x-text="msg.user?.name"></span>
                            <span class="text-xs text-gray-400" x-text="'[' + (msg.user?.rank || '?') + ']'"></span>
                        </div>
                        <p class="text-sm text-gray-700" x-text="msg.body"></p>
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
        @else
        <div class="p-3 border-t border-gray-100 text-center text-sm text-gray-400">
            <a href="{{ route('login') }}" class="text-indigo-600 hover:underline">เข้าสู่ระบบ</a>เพื่อส่งข้อความ
        </div>
        @endauth
    </div>
</div>
@endsection
