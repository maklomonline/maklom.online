@extends('layouts.admin')
@section('title', 'รายละเอียดเกม #' . $game->id)
@section('content')
<div class="max-w-4xl mx-auto">
    <h1 class="text-2xl font-bold mb-6">เกม #{{ $game->id }}</h1>
    <div class="card p-6 mb-6">
        <dl class="grid grid-cols-2 gap-4">
            <div><dt class="text-sm text-gray-500">ดำ</dt><dd>{{ $game->blackPlayer?->getDisplayName() }}</dd></div>
            <div><dt class="text-sm text-gray-500">ขาว</dt><dd>{{ $game->whitePlayer?->getDisplayName() }}</dd></div>
            <div><dt class="text-sm text-gray-500">สถานะ</dt><dd>{{ $game->status }}</dd></div>
            <div><dt class="text-sm text-gray-500">ขนาดกระดาน</dt><dd>{{ $game->board_size }}×{{ $game->board_size }}</dd></div>
            <div><dt class="text-sm text-gray-500">ผลลัพธ์</dt><dd>{{ $game->result ?? '-' }}</dd></div>
        </dl>
    </div>
    @if($game->isActive())
    <form method="POST" action="{{ route('admin.games.abort', $game) }}">
        @csrf
        <button type="submit" class="btn-danger">ยกเลิกเกม</button>
    </form>
    @endif
    <a href="{{ route('admin.games.index') }}" class="btn-secondary mt-4 inline-block">กลับ</a>
</div>
@endsection
