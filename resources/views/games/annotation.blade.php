@extends('layouts.app')
@section('title', ($annotationMeta['title'] ?? 'สร้าง annotation') . ' · เกม #' . $game->id)

@push('head')
<style>
@media (min-width: 1280px) {
  #game-root {
    grid-template-columns: 1fr 340px !important;
    grid-template-rows: auto auto;
    grid-template-areas: "board clock" "board sidebar";
    align-items: start;
  }

  .game-clock { grid-area: clock; }
  .game-col-left { grid-area: board; display: flex; flex-direction: column; position: sticky; top: 1rem; align-self: start; }
  .game-col-right { grid-area: sidebar; display: flex; flex-direction: column; }
  .game-board-card { display: flex; flex-direction: column; }
  .game-board-card .board-wrap { display: flex; align-items: center; justify-content: center; padding: 0.625rem; }
  .game-board-card .board-svg-wrap { width: 100%; max-width: min(calc(100vw - 400px), 80vh); aspect-ratio: 1 / 1; cursor: crosshair; }
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
    x-data='gameAnnotationEditor({
        gameId: {{ $game->id }},
        boardSize: {{ $bs }},
        komi: {{ $game->komi }},
        handicap: {{ $game->handicap }},
        moves: @json($moves),
        annotationMeta: @json($annotationMeta),
        annotationPayload: @json($annotationPayload),
        canEdit: {{ $canEdit ? 'true' : 'false' }}
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
                <div class="board-svg-wrap" @click="handleBoardClick($event)">
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
                        <g x-ref="stonesLayer" x-effect="renderBoard()"></g>
                    </svg>
                </div>
            </div>

            <div style="padding:0.75rem 1rem;border-top:1.5px solid #E2E2E7;display:flex;justify-content:space-between;align-items:center;gap:0.75rem;flex-wrap:wrap">
                <div style="display:flex;align-items:center;gap:0.5rem;flex-wrap:wrap">
                    <button @click="goFirst()" style="background:#F3F4F6;border:1.5px solid #E2E2E7;border-radius:6px;padding:0.375rem 0.625rem;cursor:pointer;font-size:0.875rem;line-height:1">⏮</button>
                    <button @click="goPrev()" :disabled="!currentState?.parentKey" :style="!currentState?.parentKey ? 'opacity:0.4;cursor:not-allowed' : ''" style="background:#F3F4F6;border:1.5px solid #E2E2E7;border-radius:6px;padding:0.375rem 0.625rem;font-size:0.875rem;line-height:1">◀</button>
                    <button @click="goNext()" :disabled="!getNextKey(currentKey)" :style="!getNextKey(currentKey) ? 'opacity:0.4;cursor:not-allowed' : ''" style="background:#F3F4F6;border:1.5px solid #E2E2E7;border-radius:6px;padding:0.375rem 0.625rem;font-size:0.875rem;line-height:1">▶</button>
                    <button @click="goLast()" style="background:#F3F4F6;border:1.5px solid #E2E2E7;border-radius:6px;padding:0.375rem 0.625rem;cursor:pointer;font-size:0.875rem;line-height:1">⏭</button>
                </div>
                <div style="font-size:0.8125rem;color:#6B6B80;font-weight:600">
                    <span x-text="currentMoveLabel"></span>
                </div>
            </div>

            <div style="padding:0.75rem 1rem;border-top:1.5px solid #E2E2E7;display:flex;justify-content:space-between;align-items:center;gap:0.75rem;flex-wrap:wrap">
                <div style="display:flex;align-items:center;gap:0.375rem;flex-wrap:wrap">
                    <template x-for="segment in currentPath" :key="segment.key">
                        <button @click="selectBranch(segment.key)"
                            :style="segment.key===currentKey ? 'background:#EEF2FF;border-color:#C7D2FE;color:#4338CA' : 'background:#fff;border-color:#E2E2E7;color:#6B7280'"
                            style="font-size:0.6875rem;font-weight:700;padding:0.1875rem 0.5rem;border-radius:999px;border:1.5px solid;cursor:pointer">
                            <span x-text="segment.label"></span>
                        </button>
                    </template>
                </div>
                <div style="font-size:0.75rem;color:#6B6B80">
                    คลิกบนกระดานเพื่อวางสัญลักษณ์หรือสร้างสาขา
                </div>
            </div>
        </div>
    </div>

    <div class="game-col-right" style="display:flex;flex-direction:column;gap:0.875rem">
        <div class="card">
            <div class="card-header" style="display:flex;align-items:center;justify-content:space-between">
                <span><ion-icon name="create-outline"></ion-icon> Annotation</span>
                @if(!$canEdit && $annotation)
                    <a href="{{ route('games.annotation.create', $game) }}"
                        style="font-size:0.75rem;font-weight:700;padding:0.25rem 0.625rem;border-radius:6px;background:#EEF2FF;color:#4338CA;border:1.5px solid #C7D2FE;text-decoration:none">
                        + สร้างของฉัน
                    </a>
                @endif
            </div>
            <div class="card-body" style="padding:0.75rem;display:flex;flex-direction:column;gap:0.625rem">
                <div>
                    <label style="font-size:0.6875rem;font-weight:600;color:#6B6B80;text-transform:uppercase;letter-spacing:0.04em;display:block;margin-bottom:0.25rem">ชื่อ annotation</label>
                    @if($canEdit)
                        <input x-model="title" type="text" class="input" placeholder="ตั้งชื่อ annotation"
                            style="font-size:0.875rem;padding:0.5rem 0.625rem">
                    @else
                        <div style="font-size:0.9375rem;font-weight:700;color:#111118">{{ $annotationMeta['title'] ?? 'Annotation' }}</div>
                    @endif
                </div>

                <div style="font-size:0.75rem;color:#6B6B80">
                    @if($annotationMeta)
                        โดย {{ $annotationMeta['user'] }} · อัปเดตล่าสุด {{ \Illuminate\Support\Carbon::parse($annotationMeta['updated_at'])->format('Y-m-d H:i') }}
                    @else
                        สร้าง annotation ใหม่จากเกมที่จบแล้ว
                    @endif
                </div>

                @if($canEdit)
                    <button @click="saveAnnotation()" :disabled="saveState==='saving'"
                        style="background:#4F46E5;color:#fff;font-size:0.8125rem;font-weight:700;padding:0.5rem 0.875rem;border-radius:6px;border:none;cursor:pointer">
                        <span x-show="saveState!=='saving'">บันทึก annotation</span>
                        <span x-show="saveState==='saving'">กำลังบันทึก...</span>
                    </button>
                @endif

                <template x-if="successMsg">
                    <div style="padding:0.5rem 0.625rem;border-radius:6px;background:#F0FDF4;border:1.5px solid #BBF7D0;font-size:0.8125rem;color:#15803D;font-weight:600" x-text="successMsg"></div>
                </template>
                <template x-if="errorMsg">
                    <div style="padding:0.5rem 0.625rem;border-radius:6px;background:#FEF2F2;border:1.5px solid #FECACA;font-size:0.8125rem;color:#DC2626;font-weight:600" x-text="errorMsg"></div>
                </template>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <ion-icon name="pricetags-outline"></ion-icon> เครื่องมือ
            </div>
            <div class="card-body" style="padding:0.75rem">
                <div style="display:flex;flex-wrap:wrap;gap:0.375rem">
                    @foreach([
                        'navigate' => 'ดูตำแหน่ง',
                        'move' => 'สร้างสาขา',
                        'triangle' => 'สามเหลี่ยม',
                        'square' => 'สี่เหลี่ยม',
                        'circle' => 'วงกลม',
                        'label' => 'A, B, C',
                        'number' => '1, 2, 3',
                        'erase' => 'ลบสัญลักษณ์',
                    ] as $toolKey => $toolLabel)
                        <button @click="setTool('{{ $toolKey }}')"
                            :style="toolMode==='{{ $toolKey }}' ? 'background:#EEF2FF;border-color:#C7D2FE;color:#4338CA' : 'background:#fff;border-color:#E2E2E7;color:#6B7280'"
                            style="font-size:0.6875rem;font-weight:700;padding:0.25rem 0.5rem;border-radius:999px;border:1.5px solid;cursor:pointer">
                            {{ $toolLabel }}
                        </button>
                    @endforeach
                </div>

                @if($canEdit)
                    <div style="margin-top:0.75rem">
                        <button @click="addPassBranch()"
                            style="width:100%;background:#fff;color:#6B7280;font-size:0.8125rem;font-weight:700;padding:0.5rem 0.75rem;border-radius:6px;border:1.5px solid #D1D5DB;cursor:pointer">
                            สร้างสาขาแบบผ่าน
                        </button>
                    </div>
                @endif
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <ion-icon name="chatbubble-ellipses-outline"></ion-icon> ความเห็นตำแหน่ง
            </div>
            <div class="card-body" style="padding:0.75rem">
                @if($canEdit)
                    <textarea :value="currentComment" @input="updateComment($event.target.value)" rows="5"
                        placeholder="เขียนความเห็นของตำแหน่งนี้"
                        style="width:100%;font-size:0.8125rem;border:1.5px solid #E2E2E7;border-radius:6px;padding:0.5rem 0.625rem;resize:vertical;background:#fff"></textarea>
                @else
                    <div style="font-size:0.8125rem;color:#111118;white-space:pre-wrap;min-height:5rem" x-text="currentComment || 'ไม่มีความเห็นในตำแหน่งนี้'"></div>
                @endif
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <ion-icon name="git-branch-outline"></ion-icon> ทางเดินถัดไป
            </div>
            <div class="card-body" style="padding:0.75rem">
                <template x-if="branchOptions.length === 0">
                    <p style="font-size:0.8125rem;color:#9CA3AF;text-align:center;margin:0">ไม่มีทางเดินถัดไป</p>
                </template>
                <template x-if="branchOptions.length > 0">
                    <div style="display:flex;flex-direction:column;gap:0.375rem">
                        <template x-for="option in branchOptions" :key="option.key">
                            <button @click="selectBranch(option.key)"
                                :style="option.key===currentKey ? 'background:#EEF2FF;border-color:#C7D2FE;color:#4338CA' : 'background:#fff;border-color:#E2E2E7;color:#111118'"
                                style="text-align:left;font-size:0.8125rem;font-weight:700;padding:0.5rem 0.625rem;border-radius:6px;border:1.5px solid;cursor:pointer">
                                <span x-text="option.label"></span>
                            </button>
                        </template>
                    </div>
                </template>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <ion-icon name="git-network-outline"></ion-icon> Tree View (สาขา)
            </div>
            <div style="max-height:280px;overflow-y:auto;font-family:monospace;font-size:0.8125rem">
                <template x-for="node in treeNodes" :key="node.key">
                    <div @click="selectBranch(node.key)"
                        :style="(node.key===currentKey ? 'background:#EEF2FF;color:#4338CA;font-weight:bold;border-left:3px solid #4338CA;' : 'color:#4B5563;border-left:3px solid transparent;') + 'padding:0.25rem 0.75rem;cursor:pointer;display:flex;align-items:center;border-bottom:1px solid #F5F5F7;'"
                    >
                        <div :style="'width:' + (node.depth * 1) + 'rem;flex-shrink:0'"></div>
                        <div x-show="node.depth > 0" style="color:#9CA3AF;margin-right:0.375rem">└─</div>
                        <div style="flex:1;display:flex;gap:0.375rem;align-items:center">
                            <span :style="node.isMainline ? 'font-weight:700;color:#111118' : ''" x-text="node.label"></span>
                            <template x-if="node.hasComment">
                                <span style="color:#D97706;font-size:0.6875rem">💬</span>
                            </template>
                            <template x-if="node.hasMarks">
                                <span style="color:#2563EB;font-size:0.6875rem">●</span>
                            </template>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        @if($chatRoom)
            <div class="card game-chat-card" style="display:flex;flex-direction:column;flex:1;min-height:0" x-data="chatWindow({{ $chatRoom->id }})">
                <div class="card-header">
                    <ion-icon name="chatbubble-ellipses-outline"></ion-icon> แชท
                </div>
                <div class="game-chat-messages" style="flex:1;overflow-y:auto;padding:0.75rem;display:flex;flex-direction:column;gap:0.5rem;min-height:200px;max-height:260px" x-ref="msgContainer">
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
