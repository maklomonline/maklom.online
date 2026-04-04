@extends('layouts.app')
@section('title', 'สมาชิก: ' . $group->name)
@section('content')
<div class="max-w-2xl mx-auto">
    <h1 class="text-2xl font-bold mb-6">สมาชิกกลุ่ม {{ $group->name }}</h1>
    <div class="card divide-y">
        @forelse($members as $member)
        <div class="flex items-center justify-between p-4">
            <div class="flex items-center gap-3">
                <img src="{{ $member->getAvatarUrl() }}" class="w-8 h-8 rounded-full" alt="">
                <div>
                    <p class="font-medium">{{ $member->getDisplayName() }}</p>
                    <p class="text-sm text-gray-500">{{ $member->pivot->role }}</p>
                </div>
            </div>
            @if(auth()->user()?->isAdminOf($group) || auth()->user()?->is_admin)
            <form method="POST" action="{{ route('groups.kick', [$group, $member]) }}">
                @csrf @method('DELETE')
                <button type="submit" class="text-red-600 text-sm hover:underline">นำออก</button>
            </form>
            @endif
        </div>
        @empty
        <p class="p-4 text-gray-500">ยังไม่มีสมาชิก</p>
        @endforelse
    </div>
    {{ $members->links() }}
    <a href="{{ route('groups.show', $group) }}" class="block mt-4 text-center text-gray-500 hover:underline">กลับ</a>
</div>
@endsection
