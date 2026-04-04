@extends('layouts.app')
@section('title', 'ล็อบบี้')
@section('content')
@php
    $onlineUsersJs = Illuminate\Support\Js::from(
        $onlineUsers->map(fn($u) => ['id' => $u->id, 'name' => $u->getDisplayName(), 'rank' => $u->rank])->values()
    );
@endphp
<div x-data="lobbyRooms({{ $onlineCount }}, {{ $onlineUsersJs }})"
    style="display:grid;grid-template-columns:1fr;gap:1.25rem"
    class="lg:grid-cols-[1fr_280px]">

    {{-- ── Room List ────────────────────────────────── --}}
    <div style="display:flex;flex-direction:column;gap:1rem">
        {{-- Header --}}
        <div style="display:flex;align-items:center;justify-content:space-between;gap:1rem">
            <div>
                <h1 style="font-size:1.25rem;font-weight:800;color:#111118;margin:0 0 0.125rem">ล็อบบี้</h1>
                <p style="font-size:0.8125rem;color:#6B6B80;margin:0;display:flex;align-items:center;gap:0.375rem">
                    <span style="width:7px;height:7px;background:#22C55E;border-radius:50%;display:inline-block"></span>
                    <span x-text="onlineCount"></span> คนออนไลน์
                </p>
            </div>
            @auth
            <a href="{{ route('rooms.create') }}" class="btn btn-primary btn-sm">
                <ion-icon name="add-outline"></ion-icon> สร้างห้อง
            </a>
            @endauth
        </div>

        {{-- Room cards --}}
        <div style="display:flex;flex-direction:column;gap:0.625rem" x-ref="roomList">
            @forelse($rooms as $room)
            <div class="card" style="padding:0" data-room-id="{{ $room->id }}">
                <div style="display:flex;align-items:center;gap:1rem;padding:0.875rem 1rem">
                    {{-- Board size --}}
                    <div style="flex-shrink:0;width:44px;height:44px;background:#F5F5F7;border-radius:0.625rem;display:flex;flex-direction:column;align-items:center;justify-content:center">
                        <span style="font-size:1rem;font-weight:800;color:#111118;line-height:1">{{ $room->board_size }}</span>
                        <span style="font-size:0.5625rem;font-weight:600;color:#9CA3AF;letter-spacing:0.02em">路</span>
                    </div>

                    {{-- Info --}}
                    <div style="flex:1;min-width:0">
                        <div style="display:flex;align-items:center;gap:0.5rem;flex-wrap:wrap;margin-bottom:0.25rem">
                            <span style="font-size:0.9375rem;font-weight:700;color:#111118;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $room->name }}</span>
                            @if($room->is_private)
                            <span class="badge badge-yellow" style="flex-shrink:0">
                                <ion-icon name="lock-closed-outline"></ion-icon> ส่วนตัว
                            </span>
                            @endif
                            <span class="badge {{ $room->status === 'waiting' ? 'badge-green' : 'badge-blue' }}" style="flex-shrink:0" data-status-badge>
                                @if($room->status === 'waiting')
                                <ion-icon name="time-outline"></ion-icon> รอผู้เล่น
                                @else
                                <ion-icon name="game-controller-outline"></ion-icon> กำลังเล่น
                                @endif
                            </span>
                        </div>
                        <div style="font-size:0.75rem;color:#6B6B80;display:flex;align-items:center;gap:0.5rem;flex-wrap:wrap">
                            <span style="display:flex;align-items:center;gap:0.25rem">
                                <ion-icon name="person-outline" style="font-size:0.75rem"></ion-icon>
                                {{ $room->creator?->getDisplayName() }}
                                <span style="color:#9CA3AF">[{{ $room->creator?->rank }}]</span>
                            </span>
                            <span style="color:#D1D5DB">·</span>
                            <span>{{ $room->getClockDescription() }}</span>
                            <span style="color:#D1D5DB">·</span>
                            <span>โคมิ {{ $room->komi }}</span>
                            @if($room->handicap > 0)
                            <span style="color:#D1D5DB">·</span>
                            <span>หมากต่อ {{ $room->handicap }}</span>
                            @endif
                        </div>
                    </div>

                    {{-- Action --}}
                    <a href="{{ route('rooms.show', $room) }}"
                        class="btn {{ $room->status === 'waiting' ? 'btn-primary' : 'btn-secondary' }} btn-sm"
                        style="flex-shrink:0" data-join-btn>
                        @if($room->status === 'waiting')
                        <ion-icon name="enter-outline"></ion-icon> เข้าร่วม
                        @else
                        <ion-icon name="eye-outline"></ion-icon> ดู
                        @endif
                    </a>
                </div>
            </div>
            @empty
            <div class="card" data-empty>
                <div style="padding:3rem 1rem;text-align:center">
                    <div style="width:48px;height:48px;background:#F5F5F7;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 0.875rem">
                        <ion-icon name="game-controller-outline" style="font-size:1.5rem;color:#C7C7D0"></ion-icon>
                    </div>
                    <p style="font-size:0.9375rem;font-weight:600;color:#6B6B80;margin:0 0 0.375rem">ยังไม่มีห้องเปิด</p>
                    <p style="font-size:0.8125rem;color:#9CA3AF;margin:0">สร้างห้องแรกแล้วเริ่มเล่นเลย!</p>
                </div>
            </div>
            @endforelse
        </div>

        {{ $rooms->links() }}
    </div>

    {{-- ── Sidebar ──────────────────────────────────── --}}
    <div style="display:flex;flex-direction:column;gap:1rem">
        @auth
        <a href="{{ route('rooms.create') }}" class="btn btn-primary btn-block">
            <ion-icon name="add-circle-outline"></ion-icon> สร้างห้องใหม่
        </a>
        @endauth

        {{-- Online users --}}
        <div class="card">
            <div class="card-header">
                <span style="display:flex;align-items:center;gap:0.5rem">
                    <span style="width:8px;height:8px;background:#22C55E;border-radius:50%;display:inline-block"></span>
                    ออนไลน์ (<span x-text="onlineCount">{{ $onlineCount }}</span>)
                </span>
            </div>
            <div style="padding:0.625rem">
                <template x-if="onlineUsers.length > 0">
                    <div style="display:flex;flex-direction:column;gap:0.25rem">
                        <template x-for="user in onlineUsers.slice(0, 12)" :key="user.id">
                            <div style="display:flex;align-items:center;gap:0.5rem;padding:0.375rem 0.5rem;border-radius:0.5rem">
                                <span style="width:7px;height:7px;background:#22C55E;border-radius:50%;flex-shrink:0"></span>
                                <span style="font-size:0.8125rem;font-weight:600;color:#111118;flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" x-text="user.name"></span>
                                <span style="font-size:0.6875rem;color:#6B6B80;font-family:monospace" x-text="'[' + user.rank + ']'"></span>
                            </div>
                        </template>
                        <div x-show="onlineUsers.length > 12"
                            style="text-align:center;font-size:0.75rem;color:#9CA3AF;padding:0.25rem 0"
                            x-text="'และอีก ' + (onlineUsers.length - 12) + ' คน'">
                        </div>
                    </div>
                </template>
                <template x-if="onlineUsers.length === 0">
                    <p style="font-size:0.8125rem;color:#9CA3AF;text-align:center;padding:0.75rem 0;margin:0">ไม่มีผู้ใช้ออนไลน์</p>
                </template>
            </div>
        </div>
    </div>

</div>
@endsection
