@extends('layouts.app')
@section('title', 'ล็อบบี้')
@section('content')
<div class="max-w-4xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold">ห้องเกมทั้งหมด</h1>
        <a href="{{ route('rooms.create') }}" class="btn-primary">สร้างห้องใหม่</a>
    </div>
    @forelse($rooms as $room)
    <div class="card mb-3 p-4">
        <div class="flex justify-between items-center">
            <div>
                <a href="{{ route('rooms.show', $room) }}" class="font-semibold text-lg hover:underline">{{ $room->name }}</a>
                <p class="text-sm text-gray-500">{{ $room->board_size }}×{{ $room->board_size }} | {{ $room->getClockDescription() }}</p>
            </div>
            <span class="badge {{ $room->status === 'waiting' ? 'badge-green' : 'badge-gray' }}">
                {{ $room->status === 'waiting' ? 'รอผู้เล่น' : 'กำลังเล่น' }}
            </span>
        </div>
    </div>
    @empty
    <p class="text-gray-500 text-center py-8">ยังไม่มีห้องเกม</p>
    @endforelse
    {{ $rooms->links() }}
</div>
@endsection
