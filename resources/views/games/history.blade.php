@extends('layouts.app')
@section('title', 'ประวัติเกมของ ' . $user->getDisplayName())
@section('content')
<h1 class="text-xl font-bold text-gray-900 mb-4">ประวัติเกมของ {{ $user->getDisplayName() }}</h1>

<div class="space-y-2">
    @forelse($games as $game)
    <a href="{{ route('games.show', $game) }}" class="block bg-white rounded-lg border border-gray-200 p-4 hover:shadow-sm transition">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3 text-sm">
                <span class="font-medium">⚫ {{ $game->blackPlayer?->getDisplayName() }}</span>
                <span class="text-gray-400">VS</span>
                <span class="font-medium">⚪ {{ $game->whitePlayer?->getDisplayName() }}</span>
            </div>
            <div class="text-right text-sm">
                <span class="font-bold {{ $game->winner_id === $user->id ? 'text-green-600' : ($game->winner_id ? 'text-red-600' : 'text-gray-500') }}">
                    {{ $game->result ?? 'ไม่ทราบผล' }}
                </span>
                <div class="text-xs text-gray-400">{{ $game->finished_at?->format('d/m/Y') }}</div>
            </div>
        </div>
        <div class="text-xs text-gray-400 mt-1">{{ $game->board_size }}×{{ $game->board_size }} · {{ $game->move_number }} ตา</div>
    </a>
    @empty
    <div class="text-center text-gray-400 py-8">ยังไม่มีประวัติเกม</div>
    @endforelse
</div>
{{ $games->links() }}
@endsection
