@extends('layouts.app')
@section('title', 'กลุ่ม')
@section('content')
<div class="flex items-center justify-between mb-6">
    <h1 class="text-xl font-bold text-gray-900">กลุ่ม</h1>
    <a href="{{ route('groups.create') }}" class="bg-indigo-600 text-white text-sm px-4 py-2 rounded-lg hover:bg-indigo-700 transition">
        + สร้างกลุ่ม
    </a>
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
    @forelse($groups as $group)
    <a href="{{ route('groups.show', $group) }}" class="bg-white rounded-xl border border-gray-200 p-5 hover:shadow-sm transition block">
        <div class="flex items-center gap-3 mb-3">
            <img src="{{ $group->getAvatarUrl() }}" class="w-12 h-12 rounded-lg object-cover">
            <div>
                <div class="font-semibold text-gray-900">{{ $group->name }}</div>
                <div class="text-xs text-gray-400">{{ $group->members_count }} สมาชิก</div>
            </div>
        </div>
        @if($group->description)
        <p class="text-xs text-gray-500 line-clamp-2">{{ $group->description }}</p>
        @endif
    </a>
    @empty
    <div class="col-span-3 text-center text-gray-400 py-8">ยังไม่มีกลุ่ม</div>
    @endforelse
</div>
{{ $groups->links() }}
@endsection
