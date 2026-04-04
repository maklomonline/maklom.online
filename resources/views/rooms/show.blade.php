@extends('layouts.app')
@section('title', $room->name)
@section('content')
<div style="max-width:480px;margin:0 auto"
    x-data="roomWaiting('{{ $room->status }}', {{ $room->id }})">

    {{-- Status banner when game starts --}}
    <div x-show="status === 'playing'" x-cloak
        style="background:#F5F3FF;border:1.5px solid #DDD6FE;border-radius:0.75rem;padding:0.875rem 1rem;margin-bottom:1rem;display:flex;align-items:center;gap:0.625rem">
        <ion-icon name="checkmark-circle" style="color:#7C3AED;font-size:1.25rem;flex-shrink:0"></ion-icon>
        <span style="font-size:0.9rem;font-weight:600;color:#4C1D95">เกมเริ่มต้นแล้ว! กำลังพาคุณไปหน้าเกม...</span>
    </div>

    <div class="card">
        {{-- Header --}}
        <div class="card-body" style="border-bottom:1.5px solid #E2E2E7">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:1rem">
                <div>
                    <h1 style="font-size:1.25rem;font-weight:800;color:#111118;margin:0 0 0.375rem">{{ $room->name }}</h1>
                    <a href="{{ route('profile.show', $room->creator->username) }}"
                        style="font-size:0.8125rem;color:#6B6B80;text-decoration:none;display:flex;align-items:center;gap:0.375rem">
                        <ion-icon name="person-outline"></ion-icon>
                        {{ $room->creator?->getDisplayName() }} [{{ $room->creator?->rank }}]
                    </a>
                </div>
                <span class="badge {{ $room->status === 'waiting' ? 'badge-green' : 'badge-blue' }}">
                    @if($room->status === 'waiting')
                        <ion-icon name="time-outline"></ion-icon> รอผู้เล่น
                    @elseif($room->status === 'playing')
                        <ion-icon name="game-controller-outline"></ion-icon> กำลังเล่น
                    @else
                        <ion-icon name="close-circle-outline"></ion-icon> ปิดแล้ว
                    @endif
                </span>
            </div>
        </div>

        {{-- Game Settings --}}
        <div class="card-body" style="border-bottom:1.5px solid #E2E2E7">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem">
                <div style="background:#F8F8FA;border-radius:0.625rem;padding:0.75rem">
                    <div style="font-size:0.6875rem;font-weight:700;color:#6B6B80;text-transform:uppercase;letter-spacing:0.04em;margin-bottom:4px">กระดาน</div>
                    <div style="font-size:1rem;font-weight:800">{{ $room->board_size }}×{{ $room->board_size }}</div>
                </div>
                <div style="background:#F8F8FA;border-radius:0.625rem;padding:0.75rem">
                    <div style="font-size:0.6875rem;font-weight:700;color:#6B6B80;text-transform:uppercase;letter-spacing:0.04em;margin-bottom:4px">โคมิ</div>
                    <div style="font-size:1rem;font-weight:800">{{ $room->komi }}</div>
                </div>
                @if($room->handicap > 0)
                <div style="background:#F8F8FA;border-radius:0.625rem;padding:0.75rem">
                    <div style="font-size:0.6875rem;font-weight:700;color:#6B6B80;text-transform:uppercase;letter-spacing:0.04em;margin-bottom:4px">หมากต่อ</div>
                    <div style="font-size:1rem;font-weight:800">{{ $room->handicap }}</div>
                </div>
                @endif
                <div style="background:#F8F8FA;border-radius:0.625rem;padding:0.75rem;{{ $room->handicap > 0 ? '' : 'grid-column:span 2' }}">
                    <div style="font-size:0.6875rem;font-weight:700;color:#6B6B80;text-transform:uppercase;letter-spacing:0.04em;margin-bottom:4px">นาฬิกา</div>
                    <div style="font-size:0.9375rem;font-weight:700">{{ $room->getClockDescription() }}</div>
                </div>
            </div>
        </div>

        {{-- Players --}}
        <div class="card-body" style="border-bottom:1.5px solid #E2E2E7">
            <div style="display:flex;align-items:center;gap:1rem">
                {{-- Creator --}}
                <div style="flex:1;display:flex;align-items:center;gap:0.625rem">
                    <img src="{{ $room->creator?->getAvatarUrl() }}" class="avatar avatar-md" alt="">
                    <div>
                        <div style="font-size:0.875rem;font-weight:700">{{ $room->creator?->getDisplayName() }}</div>
                        <div style="font-size:0.75rem;color:#6B6B80">[{{ $room->creator?->rank }}]</div>
                    </div>
                </div>

                <div style="font-size:0.75rem;font-weight:800;color:#C7C7D0;padding:0.375rem 0.75rem;border:1.5px solid #E2E2E7;border-radius:999px">VS</div>

                {{-- Opponent slot --}}
                @if($room->status === 'playing' && $room->currentGame)
                @php
                    $game = $room->currentGame;
                    $opp  = $game->black_player_id === $room->creator_id ? $game->whitePlayer : $game->blackPlayer;
                @endphp
                <div style="flex:1;display:flex;align-items:center;gap:0.625rem;justify-content:flex-end">
                    <div style="text-align:right">
                        <div style="font-size:0.875rem;font-weight:700">{{ $opp?->getDisplayName() }}</div>
                        <div style="font-size:0.75rem;color:#6B6B80">[{{ $opp?->rank }}]</div>
                    </div>
                    <img src="{{ $opp?->getAvatarUrl() }}" class="avatar avatar-md" alt="">
                </div>
                @else
                <div style="flex:1;display:flex;align-items:center;gap:0.625rem;justify-content:flex-end">
                    <div style="text-align:right">
                        <div style="font-size:0.875rem;font-weight:600;color:#C7C7D0">รอผู้เล่น</div>
                    </div>
                    <div style="width:40px;height:40px;border-radius:50%;background:#F5F5F7;border:2px dashed #C7C7D0;display:flex;align-items:center;justify-content:center">
                        <ion-icon name="person-outline" style="color:#C7C7D0;font-size:1.125rem"></ion-icon>
                    </div>
                </div>
                @endif
            </div>
        </div>

        {{-- Actions --}}
        <div class="card-body">
            @if($room->status === 'waiting')
                @auth
                    @if(auth()->id() === $room->creator_id)
                    <div style="text-align:center;padding:0.5rem 0;margin-bottom:0.75rem">
                        <div style="display:flex;align-items:center;justify-content:center;gap:0.5rem;color:#6B6B80;font-size:0.875rem">
                            <span style="display:inline-block;width:8px;height:8px;background:#22C55E;border-radius:50%;animation:pulse-green 1.5s ease-in-out infinite"></span>
                            รอผู้เล่นเข้าร่วม...
                        </div>
                    </div>
                    <form method="POST" action="{{ route('rooms.leave', $room) }}">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-danger btn-block btn-sm">
                            <ion-icon name="close-outline"></ion-icon> ยกเลิกห้อง
                        </button>
                    </form>
                    @else
                    <form method="POST" action="{{ route('rooms.join', $room) }}" style="display:flex;flex-direction:column;gap:0.625rem">
                        @csrf
                        @if($room->is_private)
                        <div class="form-group">
                            <label class="label">รหัสผ่านห้อง</label>
                            <input type="password" name="password" class="input" placeholder="••••••••">
                            @error('password')
                            <p style="font-size:0.75rem;color:#DC2626">{{ $message }}</p>
                            @enderror
                        </div>
                        @endif
                        <button type="submit" class="btn btn-primary btn-block">
                            <ion-icon name="game-controller-outline"></ion-icon> เข้าร่วมเกม
                        </button>
                    </form>
                    @endif
                @else
                    <a href="{{ route('login') }}" class="btn btn-primary btn-block">
                        <ion-icon name="log-in-outline"></ion-icon> เข้าสู่ระบบเพื่อเล่น
                    </a>
                @endauth

            @elseif($room->status === 'playing' && $room->currentGame)
                <a href="{{ route('games.show', $room->currentGame) }}" class="btn btn-primary btn-block">
                    <ion-icon name="eye-outline"></ion-icon> ดูเกมที่กำลังเล่น
                </a>
            @else
                <p style="text-align:center;color:#6B6B80;font-size:0.875rem">ห้องนี้ปิดแล้ว</p>
            @endif

            <div style="margin-top:0.875rem;text-align:center">
                <a href="{{ route('lobby') }}" style="font-size:0.8125rem;color:#6B6B80;text-decoration:none;display:inline-flex;align-items:center;gap:0.375rem">
                    <ion-icon name="arrow-back-outline"></ion-icon> กลับไปล็อบบี้
                </a>
            </div>
        </div>
    </div>
</div>

<style>
@keyframes pulse-green {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.6; transform: scale(1.2); }
}
</style>
@endsection
