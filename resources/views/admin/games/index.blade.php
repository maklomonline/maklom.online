@extends('layouts.admin')
@section('title', 'จัดการเกม')
@section('content')
<div class="bg-white rounded-xl border border-gray-200">
    <div class="p-4 border-b border-gray-100">
        <form method="GET" class="flex gap-2">
            <select name="status" class="border border-gray-200 rounded-lg px-2 py-1.5 text-sm outline-none">
                <option value="">ทุกสถานะ</option>
                <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>กำลังเล่น</option>
                <option value="finished" {{ request('status') === 'finished' ? 'selected' : '' }}>จบแล้ว</option>
                <option value="aborted" {{ request('status') === 'aborted' ? 'selected' : '' }}>ถูกยกเลิก</option>
            </select>
            <button class="bg-indigo-600 text-white text-sm px-3 py-1.5 rounded-lg">กรอง</button>
        </form>
    </div>
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
            <tr>
                <th class="px-4 py-3 text-left">ID</th>
                <th class="px-4 py-3 text-left">ผู้เล่น</th>
                <th class="px-4 py-3 text-left">กระดาน</th>
                <th class="px-4 py-3 text-left">สถานะ</th>
                <th class="px-4 py-3 text-left">ผลลัพธ์</th>
                <th class="px-4 py-3 text-left">เริ่มเมื่อ</th>
                <th class="px-4 py-3 text-left">การดำเนินการ</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @foreach($games as $game)
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-3 text-gray-400">#{{ $game->id }}</td>
                <td class="px-4 py-3">⚫ {{ $game->blackPlayer?->username }} VS ⚪ {{ $game->whitePlayer?->username }}</td>
                <td class="px-4 py-3">{{ $game->board_size }}×{{ $game->board_size }}</td>
                <td class="px-4 py-3">
                    <span class="text-xs px-1.5 py-0.5 rounded {{ $game->status === 'active' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' }}">
                        {{ $game->status }}
                    </span>
                </td>
                <td class="px-4 py-3 font-medium">{{ $game->result ?? '-' }}</td>
                <td class="px-4 py-3 text-gray-400 text-xs">{{ $game->started_at->format('d/m/Y H:i') }}</td>
                <td class="px-4 py-3 flex gap-2">
                    <a href="{{ route('admin.games.show', $game) }}" class="text-xs text-indigo-600 hover:underline">ดู</a>
                    @if($game->status === 'active')
                    <form method="POST" action="{{ route('admin.games.abort', $game) }}">
                        @csrf
                        <button class="text-xs text-red-600 hover:underline">ยกเลิก</button>
                    </form>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    <div class="p-4">{{ $games->links() }}</div>
</div>
@endsection
