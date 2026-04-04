@extends('layouts.admin')
@section('title', 'จัดการ: ' . $user->getDisplayName())
@section('content')
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <h2 class="font-semibold mb-4">ข้อมูลผู้ใช้</h2>
        <div class="flex items-center gap-4 mb-4">
            <img src="{{ $user->getAvatarUrl() }}" class="w-16 h-16 rounded-full">
            <div>
                <div class="font-bold text-lg">{{ $user->getDisplayName() }}</div>
                <div class="text-sm text-gray-500">{{ $user->username }} · {{ $user->email }}</div>
                <div class="text-sm text-gray-500">แรงค์: {{ $user->rank }} · สมัคร: {{ $user->created_at->format('d/m/Y') }}</div>
            </div>
        </div>
        @if($user->stats)
        <div class="grid grid-cols-3 gap-2 text-center text-sm">
            <div class="bg-gray-50 rounded p-2"><div class="font-bold">{{ $user->stats->games_played }}</div><div class="text-xs text-gray-400">เกม</div></div>
            <div class="bg-green-50 rounded p-2"><div class="font-bold text-green-700">{{ $user->stats->games_won }}</div><div class="text-xs text-gray-400">ชนะ</div></div>
            <div class="bg-red-50 rounded p-2"><div class="font-bold text-red-700">{{ $user->stats->games_lost }}</div><div class="text-xs text-gray-400">แพ้</div></div>
        </div>
        @endif
    </div>

    @if(!$user->is_banned)
    <div class="bg-white rounded-xl border border-red-200 p-6">
        <h2 class="font-semibold text-red-700 mb-4">ระงับบัญชี</h2>
        <form method="POST" action="{{ route('admin.users.ban', $user) }}" class="space-y-3">
            @csrf
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">เหตุผล</label>
                <textarea name="ban_reason" rows="2" required
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-red-400"></textarea>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">ถึงวันที่ (ว่าง = ถาวร)</label>
                <input type="datetime-local" name="banned_until"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-red-400">
            </div>
            <button type="submit" class="bg-red-600 text-white text-sm px-4 py-2 rounded-lg hover:bg-red-700 transition">ระงับบัญชี</button>
        </form>
    </div>
    @else
    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <h2 class="font-semibold mb-2">สถานะ: ถูกระงับ</h2>
        <p class="text-sm text-gray-600 mb-1">เหตุผล: {{ $user->ban_reason }}</p>
        <p class="text-sm text-gray-600 mb-4">ถึงวันที่: {{ $user->banned_until?->format('d/m/Y H:i') ?? 'ถาวร' }}</p>
        <form method="POST" action="{{ route('admin.users.unban', $user) }}">
            @csrf
            <button class="bg-green-600 text-white text-sm px-4 py-2 rounded-lg hover:bg-green-700 transition">ยกเลิกการระงับ</button>
        </form>
    </div>
    @endif
</div>
@endsection
