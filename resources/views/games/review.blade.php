@extends('layouts.app')
@section('title', 'รีวิวเกม #' . $game->id)

@push('head')
<style>
@media (min-width: 1280px) {
  #game-root {
    grid-template-columns: 1fr 320px !important;
    grid-template-rows: auto auto;
    grid-template-areas: "board clock" "board sidebar";
    align-items: start;
  }

  .game-clock { grid-area: clock; }
  .game-col-left { grid-area: board; display: flex; flex-direction: column; position: sticky; top: 1rem; align-self: start; }
  .game-col-right { grid-area: sidebar; display: flex; flex-direction: column; }
  .game-board-card { display: flex; flex-direction: column; }
  .game-board-card .board-wrap { display: flex; align-items: center; justify-content: center; padding: 0.625rem; }
  .game-board-card .board-svg-wrap { width: 100%; max-width: min(calc(100vw - 380px), 80vh); aspect-ratio: 1 / 1; }
  .game-board-card .board-svg-wrap svg { width: 100%; height: 100%; display: block; }
}
</style>
@endpush

@php
    $bs = $game->board_size;
    $cell = 36;
    $pad = $cell;
    $vb = ($bs + 1) * $cell;
    $starMap = [
        9  => [[2,2],[2,4],[2,6],[4,2],[4,4],[4,6],[6,2],[6,4],[6,6]],
        13 => [[3,3],[3,6],[3,9],[6,3],[6,6],[6,9],[9,3],[9,6],[9,9]],
        19 => [[3,3],[3,9],[3,15],[9,3],[9,9],[9,15],[15,3],[15,9],[15,15]],
    ];
    $starPts = $starMap[$bs] ?? [];
    $lastMoveTs = $game->last_move_at?->timestamp ?? $game->finished_at?->timestamp ?? now()->timestamp;
    $moves = $game->moves->map(fn ($move) => [
        'move_number' => $move->move_number,
        'color' => $move->color,
        'coordinate' => $move->coordinate,
    ])->values();
@endphp

@section('content')
<div
    x-data='gameReplay({
        gameId: {{ $game->id }},
        boardStates: @json($boardStates),
        moves: @json($moves),
        boardSize: {{ $bs }},
        annotations: @json($annotations)
    })'
    @keydown.arrow-left.window="goPrev()"
    @keydown.arrow-right.window="goNext()"
    @keydown.home.window="goFirst()"
    @keydown.end.window="goLast()"
    style="display:grid;grid-template-columns:1fr;gap:1rem"
    id="game-root">

    <div class="card game-clock"
        x-data="clockTimer(
            {{ $game->id }},
            {{ $game->black_time_left }},
            {{ $game->white_time_left }},
            '{{ $game->clock_type }}',
            {{ $game->byoyomi_seconds ?? 30 }},
            {{ $game->black_periods_left ?? 0 }},
            {{ $game->white_periods_left ?? 0 }},
            '{{ $game->current_color }}',
            {{ $lastMoveTs }},
            'finished'
        )">
        <div class="card-body" style="padding:0.875rem">
            <div class="clock-panel">
                <div class="clock-player" :class="{ active: activeColor==='black' }">
                    <div style="display:flex;align-items:center;justify-content:center;gap:0.25rem;margin-bottom:0.375rem;flex-wrap:wrap">
                        <span style="width:12px;height:12px;background:#111;border-radius:50%;display:inline-block;border:1.5px solid #555;flex-shrink:0"></span>
                        <span style="font-size:0.75rem;font-weight:600;color:#6B6B80;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:90px">{{ $game->blackPlayer?->getDisplayName() }}</span>
                    </div>
                    <div class="clock-time" x-text="formatTime(getDisplayTime('black'))"></div>
                    <div x-show="clockType==='byoyomi'" style="font-size:0.6875rem;color:#6B6B80;margin-top:0.25rem;font-weight:600" x-text="blackPeriods + ' ×' + byoyomiSeconds + 'วิ'"></div>
                </div>

                <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;gap:0.25rem;flex-shrink:0">
                    <span style="font-size:0.9375rem;font-weight:800;color:#4C1D95;background:#F5F3FF;padding:0.25rem 0.75rem;border-radius:999px">{{ $game->result ?? 'จบเกมแล้ว' }}</span>
                </div>

                <div class="clock-player" :class="{ active: activeColor==='white' }">
                    <div style="display:flex;align-items:center;justify-content:center;gap:0.25rem;margin-bottom:0.375rem;flex-wrap:wrap">
                        <span style="font-size:0.75rem;font-weight:600;color:#6B6B80;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:90px">{{ $game->whitePlayer?->getDisplayName() }}</span>
                        <span style="width:12px;height:12px;background:#fff;border-radius:50%;display:inline-block;border:1.5px solid #ccc;flex-shrink:0"></span>
                    </div>
                    <div class="clock-time" x-text="formatTime(getDisplayTime('white'))"></div>
                    <div x-show="clockType==='byoyomi'" style="font-size:0.6875rem;color:#6B6B80;margin-top:0.25rem;font-weight:600" x-text="whitePeriods + ' ×' + byoyomiSeconds + 'วิ'"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="game-col-left">
        <div class="card game-board-card">
            <div class="board-wrap">
                <div class="board-svg-wrap">
                    <svg viewBox="0 0 {{ $vb }} {{ $vb }}" xmlns="http://www.w3.org/2000/svg">
                        <rect width="{{ $vb }}" height="{{ $vb }}" fill="#E8B862" rx="8"/>
                        <rect x="{{ $pad * 0.5 }}" y="{{ $pad * 0.5 }}"
                              width="{{ ($bs + 1) * $cell - $pad }}" height="{{ ($bs + 1) * $cell - $pad }}"
                              fill="#D4A537" rx="6" opacity="0.4"/>
                        @for($i = 1; $i <= $bs; $i++)
                        <line x1="{{ $pad }}" y1="{{ $i * $cell }}"
                              x2="{{ $bs * $cell }}" y2="{{ $i * $cell }}"
                              stroke="#7C5524" stroke-width="1" opacity="0.7"/>
                        <line x1="{{ $i * $cell }}" y1="{{ $pad }}"
                              x2="{{ $i * $cell }}" y2="{{ $bs * $cell }}"
                              stroke="#7C5524" stroke-width="1" opacity="0.7"/>
                        @endfor
                        @foreach($starPts as [$r, $c])
                        <circle cx="{{ ($c + 1) * $cell }}" cy="{{ ($r + 1) * $cell }}" r="4" fill="#5C3D11" opacity="0.8"/>
                        @endforeach
                        @php $letters = str_split('ABCDEFGHJKLMNOPQRST'); @endphp
                        @for($i = 0; $i < $bs; $i++)
                        <text x="{{ ($i + 1) * $cell }}" y="{{ $pad * 0.62 }}"
                              text-anchor="middle" font-size="{{ $cell * 0.36 }}"
                              font-family="monospace" fill="#7C5524" opacity="0.6">{{ $letters[$i] }}</text>
                        <text x="{{ $pad * 0.32 }}" y="{{ ($i + 1) * $cell + $cell * 0.13 }}"
                              text-anchor="middle" font-size="{{ $cell * 0.36 }}"
                              font-family="monospace" fill="#7C5524" opacity="0.6">{{ $bs - $i }}</text>
                        @endfor
                        <g x-ref="stonesLayer" x-effect="renderStones()"></g>
                    </svg>
                </div>
            </div>

            <div style="padding:0.75rem 1rem;border-top:1.5px solid #E2E2E7;display:flex;justify-content:space-between;align-items:center;gap:0.75rem;flex-wrap:wrap">
                <div style="display:flex;align-items:center;gap:0.5rem;flex-wrap:wrap">
                    <button @click="goFirst()" style="background:#F3F4F6;border:1.5px solid #E2E2E7;border-radius:6px;padding:0.375rem 0.625rem;cursor:pointer;font-size:0.875rem;line-height:1">⏮</button>
                    <button @click="goPrev()" :disabled="moveIndex===0" :style="moveIndex===0 ? 'opacity:0.4;cursor:not-allowed' : ''" style="background:#F3F4F6;border:1.5px solid #E2E2E7;border-radius:6px;padding:0.375rem 0.625rem;font-size:0.875rem;line-height:1">◀</button>
                    <button @click="goNext()" :disabled="moveIndex>=totalMoves" :style="moveIndex>=totalMoves ? 'opacity:0.4;cursor:not-allowed' : ''" style="background:#F3F4F6;border:1.5px solid #E2E2E7;border-radius:6px;padding:0.375rem 0.625rem;font-size:0.875rem;line-height:1">▶</button>
                    <button @click="goLast()" style="background:#F3F4F6;border:1.5px solid #E2E2E7;border-radius:6px;padding:0.375rem 0.625rem;cursor:pointer;font-size:0.875rem;line-height:1">⏭</button>
                </div>
                <div style="font-size:0.8125rem;color:#6B6B80;font-weight:600">
                    <span x-text="currentMoveLabel ?? 'ตำแหน่งเริ่มต้น'"></span>
                </div>
                <div style="display:flex;align-items:center;gap:0.375rem">
                    <input type="number" min="0" :max="totalMoves" :value="moveIndex" @change="goToMove(parseInt($event.target.value || 0, 10))"
                        style="width:58px;text-align:center;font-size:0.875rem;font-weight:700;border:1.5px solid #E2E2E7;border-radius:6px;padding:0.25rem 0.375rem">
                    <span style="font-size:0.8125rem;color:#6B6B80">/ <span x-text="totalMoves"></span></span>
                </div>
            </div>
        </div>

        {{-- Annotation Comment display --}}
        <template x-if="currentAnnotation">
            <div class="card" style="margin-top:1rem;border-left:4px solid #4F46E5">
                <div class="card-header" style="display:flex;align-items:center;justify-content:space-between">
                    <span style="font-weight:700;font-size:0.875rem;color:#4F46E5">
                        <ion-icon name="chatbubble-ellipses-outline"></ion-icon> ความเห็น: <span x-text="currentAnnotation.title"></span>
                    </span>
                    <button @click="currentAnnotation = null; renderStones()" style="background:none;border:none;color:#6B6B80;cursor:pointer;font-size:1.125rem">
                        <ion-icon name="close-outline"></ion-icon>
                    </button>
                </div>
                <div class="card-body" style="padding:0.875rem">
                    <div x-show="currentComment" style="font-size:0.875rem;color:#111118;white-space:pre-wrap" x-text="currentComment"></div>
                    <div x-show="!currentComment" style="font-size:0.8125rem;color:#9CA3AF;text-align:center;font-style:italic">ไม่มีความเห็นในตานี้</div>
                </div>
            </div>
        </template>
    </div>

    <div class="game-col-right" style="display:flex;flex-direction:column;gap:0.875rem">
        <div class="card">
            <div class="card-header" style="display:flex;align-items:center;justify-content:space-between">
                <span><ion-icon name="bookmark-outline"></ion-icon> Annotation</span>
                <a href="{{ route('games.annotation.create', $game) }}"
                    style="font-size:0.75rem;font-weight:700;padding:0.25rem 0.625rem;border-radius:6px;background:#EEF2FF;color:#4338CA;border:1.5px solid #C7D2FE;text-decoration:none">
                    + สร้างใหม่
                </a>
            </div>
            <div class="card-body" style="padding:0.75rem">
                <div x-show="annotations.length === 0">
                    <p style="font-size:0.8125rem;color:#9CA3AF;text-align:center;margin:0;padding:0.75rem 0">
                        ยังไม่มี annotation สำหรับเกมนี้
                    </p>
                </div>
                <div style="display:flex;flex-direction:column;gap:0.5rem">
                    <template x-for="ann in annotations" :key="ann.id">
                        <div style="display:flex;align-items:flex-start;gap:0.5rem">
                            <button @click="loadAnnotation(ann)"
                                :style="currentAnnotation?.id === ann.id ? 'border-color:#4F46E5;background:#EEF2FF' : 'background:#fff;border-color:#E2E2E7'"
                                style="flex:1;display:flex;align-items:flex-start;gap:0.625rem;padding:0.625rem 0.75rem;border-radius:8px;border:1.5px solid;text-decoration:none;color:inherit;text-align:left;cursor:pointer">
                                <div style="flex:1;min-width:0">
                                    <div style="font-size:0.8125rem;font-weight:700;color:#111118;white-space:nowrap;overflow:hidden;text-overflow:ellipsis" x-text="ann.title"></div>
                                    <div style="font-size:0.6875rem;color:#6B6B80" x-text="ann.user + ' · ' + ann.updated_at"></div>
                                    <div style="font-size:0.6875rem;color:#6B6B80" x-text="'ตำแหน่งที่บันทึกไว้ ' + ann.positions_count"></div>
                                </div>
                                <template x-if="ann.can_edit">
                                    <span style="font-size:0.625rem;font-weight:700;padding:0.125rem 0.375rem;border-radius:999px;background:#F5F3FF;color:#6D28D9;flex-shrink:0">ของฉัน</span>
                                </template>
                            </button>
                            <div style="display:flex;flex-direction:column;gap:0.25rem">
                                <a :href="ann.view_url" class="btn btn-secondary btn-sm" style="padding:0.25rem;min-width:0" title="ขยาย/แก้ไข">
                                    <ion-icon name="expand-outline"></ion-icon>
                                </a>
                                <template x-if="ann.can_edit">
                                    <button @click="deleteAnnotation(ann.id)" class="btn btn-secondary btn-sm" style="padding:0.25rem;min-width:0;color:#EF4444" :disabled="deleting">
                                        <ion-icon name="trash-outline"></ion-icon>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <ion-icon name="information-circle-outline"></ion-icon> ข้อมูลเกม
            </div>
            <div class="card-body">
                <dl style="display:grid;grid-template-columns:1fr 1fr;gap:0.625rem">
                    <div>
                        <dt style="font-size:0.6875rem;font-weight:600;color:#6B6B80;text-transform:uppercase;letter-spacing:0.04em;margin-bottom:2px">กระดาน</dt>
                        <dd style="font-size:0.9375rem;font-weight:700;margin:0">{{ $game->board_size }}×{{ $game->board_size }}</dd>
                    </div>
                    <div>
                        <dt style="font-size:0.6875rem;font-weight:600;color:#6B6B80;text-transform:uppercase;letter-spacing:0.04em;margin-bottom:2px">โคมิ</dt>
                        <dd style="font-size:0.9375rem;font-weight:700;margin:0">{{ $game->komi }}</dd>
                    </div>
                    <div>
                        <dt style="font-size:0.6875rem;font-weight:600;color:#6B6B80;text-transform:uppercase;letter-spacing:0.04em;margin-bottom:2px">ตาทั้งหมด</dt>
                        <dd style="font-size:0.9375rem;font-weight:700;margin:0">{{ $game->move_number }}</dd>
                    </div>
                    <div>
                        <dt style="font-size:0.6875rem;font-weight:600;color:#6B6B80;text-transform:uppercase;letter-spacing:0.04em;margin-bottom:2px">ผลเกม</dt>
                        <dd style="font-size:0.9375rem;font-weight:700;margin:0">{{ $game->result ?? '-' }}</dd>
                    </div>
                </dl>
            </div>
        </div>

        <div class="card" style="flex-shrink:0">
            <div class="card-header">
                <ion-icon name="list-outline"></ion-icon> ประวัติตา
            </div>
            <div style="max-height:240px;overflow-y:auto">
                @forelse($game->moves as $move)
                    <div @click="selectMove({{ $move->move_number }})"
                        :style="moveIndex==={{ $move->move_number }} ? 'background:#EEF2FF;' : ''"
                        style="display:flex;gap:0.625rem;padding:0.375rem 1rem;font-size:0.8125rem;border-bottom:1px solid #F5F5F7;align-items:center;cursor:pointer">
                        <span style="color:#9CA3AF;font-family:monospace;font-size:0.75rem;min-width:1.5rem">{{ $move->move_number }}</span>
                        <span>{{ $move->color === 'black' ? '⚫' : '⚪' }}</span>
                        <span style="font-weight:600;font-family:monospace">{{ $move->coordinate ?? 'ผ่าน' }}</span>
                    </div>
                @empty
                    <p style="padding:1rem;text-align:center;font-size:0.8125rem;color:#9CA3AF">ยังไม่มีตาเดิน</p>
                @endforelse
            </div>
        </div>

        @if($chatRoom)
            <div class="card game-chat-card" style="display:flex;flex-direction:column;flex:1;min-height:0" x-data="chatWindow({{ $chatRoom->id }})">
                <div class="card-header">
                    <ion-icon name="chatbubble-ellipses-outline"></ion-icon> แชท
                </div>
                <div class="game-chat-messages" style="flex:1;overflow-y:auto;padding:0.75rem;display:flex;flex-direction:column;gap:0.5rem;min-height:200px;max-height:300px" x-ref="msgContainer">
                    <template x-for="msg in messages" :key="msg.id">
                        <div style="display:flex;flex-direction:column;gap:1px">
                            <span style="font-size:0.6875rem;font-weight:700;color:#4F46E5" x-text="(msg.user?.name || '?') + ' [' + (msg.user?.rank || '?') + ']'"></span>
                            <span style="font-size:0.8125rem;color:#111118" x-text="msg.body"></span>
                        </div>
                    </template>
                    <div x-show="messages.length === 0" style="text-align:center;color:#9CA3AF;font-size:0.8125rem;padding:1rem 0">
                        ยังไม่มีข้อความ
                    </div>
                </div>
                <form @submit.prevent="sendMessage()" style="padding:0.625rem;border-top:1.5px solid #E2E2E7;display:flex;gap:0.5rem">
                    <input x-model="draft" type="text" placeholder="พิมพ์ข้อความ..." class="input" style="font-size:0.8125rem;padding:0.375rem 0.625rem">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <ion-icon name="send-outline"></ion-icon>
                    </button>
                </form>
            </div>
        @endif
    </div>
</div>
@endsection
