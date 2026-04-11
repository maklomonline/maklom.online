@extends('layouts.app')
@section('title', ($game->status === 'finished' ? 'ทบทวนเกม' : 'เกม') . ' #' . $game->id)

@push('head')
<style>
/* ── Desktop: 2-column, scrollable ─────────────────── */
@media (min-width: 1280px) {
  #game-root {
    grid-template-columns: 1fr 320px !important;
    grid-template-rows: auto auto;
    grid-template-areas: "board clock" "board sidebar";
    align-items: start;
  }

  /* Grid area assignments */
  .game-clock     { grid-area: clock; }
  .game-col-left  { grid-area: board; display: flex; flex-direction: column; position: sticky; top: 1rem; align-self: start; }
  .game-col-right { grid-area: sidebar; display: flex; flex-direction: column; }

  /* Board card — square board sized to left column width */
  .game-board-card {
    display: flex;
    flex-direction: column;
  }
  .game-board-card .board-wrap {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0.625rem;
  }
  .game-board-card .board-svg-wrap {
    width: 100%;
    max-width: min(calc(100vw - 380px), 80vh);
    aspect-ratio: 1 / 1;
  }
  .game-board-card .board-svg-wrap svg {
    width: 100%;
    height: 100%;
    display: block;
  }
}
</style>
@endpush

@php
    $bs   = $game->board_size;
    $cell = 36;                          // px per cell in viewBox
    $pad  = $cell;                       // padding around grid
    $vb   = ($bs + 1) * $cell;          // full viewBox size
    $starMap = [
        9  => [[2,2],[2,4],[2,6],[4,2],[4,4],[4,6],[6,2],[6,4],[6,6]],
        13 => [[3,3],[3,6],[3,9],[6,3],[6,6],[6,9],[9,3],[9,6],[9,9]],
        19 => [[3,3],[3,9],[3,15],[9,3],[9,9],[9,15],[15,3],[15,9],[15,15]],
    ];
    $starPts = $starMap[$bs] ?? [];

    // last_move_at is updated atomically with each move/pass — most reliable reference.
    $lastMoveTs = $game->last_move_at?->timestamp ?? $game->started_at?->timestamp ?? now()->timestamp;
@endphp

@section('content')
@if($game->status === 'finished')
{{-- ══════════════════════════════════════════════════════════════════════════
     REVIEW MODE — finished game
══════════════════════════════════════════════════════════════════════════ --}}
<div
    x-data='gameReview(
        {{ $game->id }},
        @json($boardStates),
        @json($game->moves->map(fn($m) => ['move_number'=>$m->move_number,'color'=>$m->color,'coordinate'=>$m->coordinate])->values()),
        {{ $bs }},
        {{ $game->komi }},
        @json($annotations->values()),
        @json(auth()->id())
    )'
    @keydown.arrow-left.window="goPrev()"
    @keydown.arrow-right.window="goNext()"
    @keydown.home.window="goFirst()"
    @keydown.end.window="goLast()"
    style="display:grid;grid-template-columns:1fr;gap:1rem"
    id="game-root">

    {{-- ── Result + Navigation header ── --}}
    <div class="card game-clock">
        <div class="card-body" style="padding:0.875rem">
            {{-- Players row --}}
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.625rem">
                <div style="display:flex;align-items:center;gap:0.375rem">
                    <span style="width:12px;height:12px;background:#111;border-radius:50%;display:inline-block;border:1.5px solid #555;flex-shrink:0"></span>
                    <span style="font-size:0.8125rem;font-weight:700;color:#111118">{{ $game->blackPlayer?->getDisplayName() }}</span>
                </div>
                <div style="font-size:0.875rem;font-weight:800;color:#4C1D95;text-align:center;padding:0.25rem 0.75rem;background:#F5F3FF;border-radius:999px">
                    {{ $game->result ?? 'จบแล้ว' }}
                </div>
                <div style="display:flex;align-items:center;gap:0.375rem">
                    <span style="font-size:0.8125rem;font-weight:700;color:#111118">{{ $game->whitePlayer?->getDisplayName() }}</span>
                    <span style="width:12px;height:12px;background:#fff;border-radius:50%;display:inline-block;border:1.5px solid #ccc;flex-shrink:0"></span>
                </div>
            </div>
            {{-- Navigation controls --}}
            <div style="display:flex;align-items:center;justify-content:center;gap:0.5rem">
                <button @click="goFirst()" title="ตาแรก"
                    style="background:#F3F4F6;border:1.5px solid #E2E2E7;border-radius:6px;padding:0.375rem 0.625rem;cursor:pointer;font-size:0.875rem;line-height:1">⏮</button>
                <button @click="goPrev()" :disabled="moveIndex===0" title="ถอยหลัง"
                    style="background:#F3F4F6;border:1.5px solid #E2E2E7;border-radius:6px;padding:0.375rem 0.625rem;cursor:pointer;font-size:0.875rem;line-height:1;opacity:moveIndex===0?0.4:1">◀</button>
                <div style="display:flex;align-items:center;gap:0.25rem">
                    <input type="number" min="0" :max="totalMoves" :value="moveIndex"
                        @change="goToMove(parseInt($event.target.value))"
                        style="width:52px;text-align:center;font-size:0.875rem;font-weight:700;border:1.5px solid #E2E2E7;border-radius:6px;padding:0.25rem 0.375rem">
                    <span style="font-size:0.8125rem;color:#6B6B80">/ <span x-text="totalMoves"></span></span>
                </div>
                <button @click="goNext()" :disabled="moveIndex>=totalMoves" title="ไปข้างหน้า"
                    style="background:#F3F4F6;border:1.5px solid #E2E2E7;border-radius:6px;padding:0.375rem 0.625rem;cursor:pointer;font-size:0.875rem;line-height:1">▶</button>
                <button @click="goLast()" title="ตาสุดท้าย"
                    style="background:#F3F4F6;border:1.5px solid #E2E2E7;border-radius:6px;padding:0.375rem 0.625rem;cursor:pointer;font-size:0.875rem;line-height:1">⏭</button>
            </div>
            {{-- Current move info --}}
            <div style="text-align:center;margin-top:0.375rem;font-size:0.75rem;color:#6B6B80;min-height:1.1em">
                <template x-if="currentMoveInfo">
                    <span x-text="(currentMoveInfo.color==='black'?'⚫':'⚪') + ' ตาที่ ' + currentMoveInfo.move_number + ': ' + (currentMoveInfo.coordinate ?? 'ผ่าน')"></span>
                </template>
                <template x-if="!currentMoveInfo && moveIndex===0">
                    <span>ตำแหน่งเริ่มต้น</span>
                </template>
            </div>
        </div>
    </div>

    {{-- ── Left Column: Board ── --}}
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
                        <circle cx="{{ ($c + 1) * $cell }}" cy="{{ ($r + 1) * $cell }}"
                                r="4" fill="#5C3D11" opacity="0.8"/>
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

            {{-- Comment display (read mode) --}}
            <template x-if="!editMode && currentComment">
                <div style="padding:0.625rem 1rem;border-top:1.5px solid #FEF9C3;background:#FEFCE8">
                    <p style="font-size:0.8125rem;color:#854D0E;margin:0;display:flex;gap:0.375rem;align-items:flex-start">
                        <ion-icon name="chatbubble-ellipses-outline" style="flex-shrink:0;margin-top:1px"></ion-icon>
                        <span x-text="currentComment"></span>
                    </p>
                </div>
            </template>

            {{-- Move annotation badge (read mode) --}}
            @php
            $annLabels = ['good_black'=>['text'=>'ดีสำหรับดำ','color'=>'#15803D','bg'=>'#F0FDF4'],
                          'good_white'=>['text'=>'ดีสำหรับขาว','color'=>'#1D4ED8','bg'=>'#EFF6FF'],
                          'tesuji'    =>['text'=>'เทสุจิ','color'=>'#B45309','bg'=>'#FFFBEB'],
                          'bad'       =>['text'=>'หมากเสีย','color'=>'#DC2626','bg'=>'#FEF2F2'],
                          'interesting'=>['text'=>'น่าสนใจ','color'=>'#2563EB','bg'=>'#EFF6FF'],
                          'doubtful'  =>['text'=>'น่าสงสัย','color'=>'#7C3AED','bg'=>'#F5F3FF']];
            @endphp
            <template x-if="!editMode && currentMoveAnnotation">
                <div style="padding:0.375rem 1rem;border-top:1.5px solid #E2E2E7;text-align:center">
                    <template x-if="currentMoveAnnotation==='good_black'">
                        <span style="font-size:0.75rem;font-weight:700;color:#15803D;background:#F0FDF4;padding:0.125rem 0.5rem;border-radius:999px">ดีสำหรับดำ</span>
                    </template>
                    <template x-if="currentMoveAnnotation==='good_white'">
                        <span style="font-size:0.75rem;font-weight:700;color:#1D4ED8;background:#EFF6FF;padding:0.125rem 0.5rem;border-radius:999px">ดีสำหรับขาว</span>
                    </template>
                    <template x-if="currentMoveAnnotation==='tesuji'">
                        <span style="font-size:0.75rem;font-weight:700;color:#B45309;background:#FFFBEB;padding:0.125rem 0.5rem;border-radius:999px">เทสุจิ</span>
                    </template>
                    <template x-if="currentMoveAnnotation==='bad'">
                        <span style="font-size:0.75rem;font-weight:700;color:#DC2626;background:#FEF2F2;padding:0.125rem 0.5rem;border-radius:999px">หมากเสีย</span>
                    </template>
                    <template x-if="currentMoveAnnotation==='interesting'">
                        <span style="font-size:0.75rem;font-weight:700;color:#2563EB;background:#EFF6FF;padding:0.125rem 0.5rem;border-radius:999px">น่าสนใจ (!?)</span>
                    </template>
                    <template x-if="currentMoveAnnotation==='doubtful'">
                        <span style="font-size:0.75rem;font-weight:700;color:#7C3AED;background:#F5F3FF;padding:0.125rem 0.5rem;border-radius:999px">น่าสงสัย (?)</span>
                    </template>
                </div>
            </template>

            {{-- Edit mode tools --}}
            <template x-if="editMode">
                <div style="border-top:2px solid #4F46E5;background:#F5F3FF">
                    {{-- Comment textarea --}}
                    <div style="padding:0.625rem 1rem">
                        <label style="font-size:0.6875rem;font-weight:700;color:#4338CA;text-transform:uppercase;letter-spacing:0.04em;display:block;margin-bottom:0.25rem">
                            <ion-icon name="chatbubble-ellipses-outline"></ion-icon> ความเห็น (ตาที่ <span x-text="moveIndex"></span>)
                        </label>
                        <textarea
                            :value="currentComment"
                            @input="setComment($event.target.value)"
                            rows="2"
                            placeholder="ใส่ความเห็นสำหรับตำแหน่งนี้..."
                            style="width:100%;font-size:0.8125rem;border:1.5px solid #C7D2FE;border-radius:6px;padding:0.375rem 0.5rem;resize:vertical;background:#fff"></textarea>
                    </div>

                    {{-- Move annotation toolbar --}}
                    <template x-if="moveIndex > 0">
                    <div style="padding:0 1rem 0.625rem;display:flex;flex-wrap:wrap;gap:0.375rem">
                        <span style="font-size:0.6875rem;font-weight:600;color:#6B6B80;width:100%;margin-bottom:0.125rem">เครื่องหมายหมาก:</span>
                        <template x-for="[key, label, color] in [['good_black','! ดำ','#15803D'],['good_white','! ขาว','#1D4ED8'],['tesuji','เทสุจิ','#B45309'],['bad','? เสีย','#DC2626'],['interesting','!? น่าสนใจ','#2563EB'],['doubtful','? สงสัย','#7C3AED']]" :key="key">
                            <button @click="toggleMoveAnnotation(key)"
                                :style="'font-size:0.6875rem;font-weight:700;padding:0.25rem 0.5rem;border-radius:999px;cursor:pointer;border:1.5px solid ' + color + ';color:' + (currentMoveAnnotation===key ? '#fff' : color) + ';background:' + (currentMoveAnnotation===key ? color : '#fff')"
                                x-text="label">
                            </button>
                        </template>
                    </div>
                    </template>

                    {{-- SGF raw toggle --}}
                    <div style="padding:0 1rem 0.625rem;display:flex;align-items:center;gap:0.5rem">
                        <label style="display:flex;align-items:center;gap:0.375rem;font-size:0.75rem;color:#6B6B80;cursor:pointer">
                            <input type="checkbox" x-model="showSgfRaw" @change="showSgfRaw && _syncSgfText()">
                            แก้ไข SGF โดยตรง
                        </label>
                    </div>
                    <template x-if="showSgfRaw">
                        <div style="padding:0 1rem 0.625rem">
                            <textarea x-model="editSgfText" rows="4"
                                style="width:100%;font-size:0.6875rem;font-family:monospace;border:1.5px solid #C7D2FE;border-radius:6px;padding:0.375rem 0.5rem;resize:vertical;background:#fff"></textarea>
                        </div>
                    </template>

                    {{-- Save / Discard --}}
                    <div style="padding:0.625rem 1rem;border-top:1.5px solid #C7D2FE;display:flex;gap:0.5rem;align-items:center">
                        <input x-model="editTitle" type="text" placeholder="ชื่อ annotation..."
                            style="flex:1;font-size:0.8125rem;border:1.5px solid #C7D2FE;border-radius:6px;padding:0.375rem 0.5rem">
                        <button @click="saveAnnotation()" :disabled="saving"
                            style="background:#4F46E5;color:#fff;font-size:0.8125rem;font-weight:600;padding:0.375rem 0.875rem;border-radius:6px;border:none;cursor:pointer;white-space:nowrap">
                            <template x-if="!saving"><span>บันทึก</span></template>
                            <template x-if="saving"><span>กำลังบันทึก...</span></template>
                        </button>
                        <button @click="discardEdit()"
                            style="background:#fff;color:#6B7280;font-size:0.8125rem;font-weight:600;padding:0.375rem 0.75rem;border-radius:6px;border:1.5px solid #D1D5DB;cursor:pointer;white-space:nowrap">
                            ยกเลิก
                        </button>
                    </div>
                </div>
            </template>

            {{-- Error / success messages --}}
            <template x-if="errorMsg">
                <div style="padding:0.5rem 1rem;background:#FEF2F2;border-top:1.5px solid #FECACA">
                    <p style="font-size:0.8125rem;color:#DC2626;margin:0" x-text="errorMsg"></p>
                </div>
            </template>
            <template x-if="successMsg">
                <div style="padding:0.5rem 1rem;background:#F0FDF4;border-top:1.5px solid #BBF7D0">
                    <p style="font-size:0.8125rem;color:#15803D;margin:0;font-weight:600" x-text="successMsg"></p>
                </div>
            </template>
        </div>
    </div>

    {{-- ── Right Sidebar ── --}}
    <div class="game-col-right" style="display:flex;flex-direction:column;gap:0.875rem">

        {{-- Annotation selector widget --}}
        <div class="card" style="flex-shrink:0">
            <div class="card-header" style="display:flex;align-items:center;justify-content:space-between">
                <span><ion-icon name="bookmark-outline"></ion-icon> Annotation</span>
                @auth
                <button x-show="!editMode" @click="enterEditMode()"
                     style="font-size:0.75rem;font-weight:700;padding:0.25rem 0.625rem;border-radius:6px;background:#EEF2FF;color:#4338CA;border:1.5px solid #C7D2FE;cursor:pointer"
                     x-init="console.log('Create button initialized, editMode:', editMode)"
                     x-effect="console.log('Create button x-effect, editMode:', editMode, 'shouldShow:', !editMode)">
                     + สร้างใหม่
                 </button>
                 {{-- Debug button that's always visible --}}
                 <button @click="enterEditMode()" style="font-size:0.75rem;font-weight:700;padding:0.25rem 0.625rem;border-radius:6px;background:#ff0000;color:#fff;border:1.5px solid #cc0000;cursor:pointer">
                     DEBUG: สร้างใหม่
                 </button>
                 @endauth
             </div>
            <div class="card-body" style="padding:0.75rem">

                {{-- Debug info --}}
                <div style="margin-bottom:0.625rem;padding:0.5rem;background:#f0f0f0;border-radius:4px;font-size:0.75rem;">
                    <div>Debug Info:</div>
                    <div>editMode: <span x-text="editMode"></span></div>
                    <div>currentUserId: <span x-text="@json(auth()->id())"></span></div>
                    <div>annotations length: <span x-text="annotations.length"></span></div>
                    <div>activeAnnotationId: <span x-text="activeAnnotationId ?? null"></span></div>
                </div>

                {{-- Active annotation badge --}}
                <template x-if="activeAnnotationId">
                    <div style="margin-bottom:0.625rem;padding:0.5rem 0.625rem;background:#EEF2FF;border-radius:8px;border:1.5px solid #C7D2FE">
                        <div style="font-size:0.6875rem;font-weight:600;color:#6B6B80;margin-bottom:2px">กำลังดู:</div>
                        <div style="display:flex;align-items:center;justify-content:space-between;gap:0.5rem">
                            <span style="font-size:0.875rem;font-weight:700;color:#3730A3" x-text="annotations.find(a=>a.id===activeAnnotationId)?.title ?? ''"></span>
                            <div style="display:flex;gap:0.375rem">
                                <template x-if="isOwnerOfActive && !editMode">
                                    <button @click="enterEditMode()"
                                        style="font-size:0.6875rem;padding:0.1875rem 0.5rem;border-radius:4px;background:#fff;border:1.5px solid #C7D2FE;color:#4338CA;cursor:pointer">
                                        แก้ไข
                                    </button>
                                </template>
                                <button @click="clearAnnotation()"
                                    style="font-size:0.6875rem;padding:0.1875rem 0.5rem;border-radius:4px;background:#fff;border:1.5px solid #E2E2E7;color:#6B7280;cursor:pointer">
                                    ✕
                                </button>
                            </div>
                        </div>
                        <div style="font-size:0.6875rem;color:#6B6B80;margin-top:2px" x-text="'โดย ' + (annotations.find(a=>a.id===activeAnnotationId)?.user ?? '')"></div>
                    </div>
                </template>

                {{-- Annotation list --}}
                <template x-if="annotations.length > 0">
                    <div>
                        <p style="font-size:0.6875rem;font-weight:600;color:#6B6B80;text-transform:uppercase;letter-spacing:0.04em;margin:0 0 0.375rem">
                            Annotations ทั้งหมด (<span x-text="annotations.length"></span>)
                        </p>
                        <div style="display:flex;flex-direction:column;gap:0.375rem;max-height:220px;overflow-y:auto">
                            <template x-for="ann in annotations" :key="ann.id">
                                <div style="display:flex;align-items:center;gap:0.375rem;padding:0.375rem 0.5rem;border-radius:6px;border:1.5px solid #E2E2E7;cursor:pointer"
                                    :style="activeAnnotationId===ann.id ? 'border-color:#6366F1;background:#EEF2FF' : 'background:#fff'"
                                    @click="loadAnnotation(ann.id)">
                                    <div style="flex:1;min-width:0">
                                        <div style="font-size:0.8125rem;font-weight:600;color:#111118;white-space:nowrap;overflow:hidden;text-overflow:ellipsis" x-text="ann.title"></div>
                                        <div style="font-size:0.6875rem;color:#6B6B80" x-text="ann.user + ' · ' + ann.created_at.slice(0,10)"></div>
                                    </div>
                                    <template x-if="ann.user_id === {{ auth()->id() ?? 'null' }}">
                                        <button @click.stop="deleteAnnotation(ann.id)"
                                            style="font-size:0.6875rem;padding:0.125rem 0.375rem;border-radius:4px;background:#FEF2F2;border:1.5px solid #FECACA;color:#DC2626;cursor:pointer;flex-shrink:0">
                                            ลบ
                                        </button>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>
                <template x-if="annotations.length === 0">
                    <p style="font-size:0.8125rem;color:#9CA3AF;text-align:center;margin:0;padding:0.5rem 0">
                        ยังไม่มี annotation — กด <strong>+ สร้างใหม่</strong> เพื่อเริ่ม
                    </p>
                </template>
            </div>
        </div>

        {{-- Game Info --}}
        <div class="card" style="flex-shrink:0">
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
                    @if($game->handicap > 0)
                    <div>
                        <dt style="font-size:0.6875rem;font-weight:600;color:#6B6B80;text-transform:uppercase;letter-spacing:0.04em;margin-bottom:2px">หมากต่อ</dt>
                        <dd style="font-size:0.9375rem;font-weight:700;margin:0">{{ $game->handicap }}</dd>
                    </div>
                    @endif
                    <div>
                        <dt style="font-size:0.6875rem;font-weight:600;color:#6B6B80;text-transform:uppercase;letter-spacing:0.04em;margin-bottom:2px">ตาทั้งหมด</dt>
                        <dd style="font-size:0.9375rem;font-weight:700;margin:0">{{ $game->move_number }}</dd>
                    </div>
                </dl>
            </div>
        </div>

        {{-- Move History --}}
        <div class="card" style="flex-shrink:0">
            <div class="card-header">
                <ion-icon name="list-outline"></ion-icon> ประวัติตา
            </div>
            <div style="max-height:240px;overflow-y:auto" id="move-history">
                @forelse($game->moves as $move)
                <div @click="handleMoveListClick({{ $move->move_number }})"
                    :style="moveIndex==={{ $move->move_number }} ? 'background:#EEF2FF;' : ''"
                    style="display:flex;gap:0.625rem;padding:0.375rem 1rem;font-size:0.8125rem;border-bottom:1px solid #F5F5F7;align-items:center;cursor:pointer">
                    <span style="color:#9CA3AF;font-family:monospace;font-size:0.75rem;min-width:1.5rem">{{ $move->move_number }}</span>
                    <span>{{ $move->color === 'black' ? '⚫' : '⚪' }}</span>
                    <span style="font-weight:600;font-family:monospace">{{ $move->coordinate ?? 'ผ่าน' }}</span>
                    {{-- annotation indicator --}}
                    <template x-if="editMoveAnnotations[{{ $move->move_number }}]">
                        <span style="margin-left:auto;font-size:0.6875rem;color:#6366F1">●</span>
                    </template>
                    <template x-if="editComments[{{ $move->move_number }}]">
                        <span style="margin-left:auto;font-size:0.6875rem;color:#D97706">💬</span>
                    </template>
                </div>
                @empty
                <p style="padding:1rem;text-align:center;font-size:0.8125rem;color:#9CA3AF">ยังไม่มีตาเดิน</p>
                @endforelse
            </div>
        </div>

        {{-- Chat --}}
        @if($chatRoom)
        <div class="card game-chat-card" style="display:flex;flex-direction:column;flex:1;min-height:0"
            x-data="chatWindow({{ $chatRoom->id }})">
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
                <input x-model="draft" type="text" placeholder="พิมพ์ข้อความ..."
                    class="input" style="font-size:0.8125rem;padding:0.375rem 0.625rem">
                <button type="submit" class="btn btn-primary btn-sm">
                    <ion-icon name="send-outline"></ion-icon>
                </button>
            </form>
        </div>
        @endif

    </div>
</div>

@else
{{-- ══════════════════════════════════════════════════════════════════════════
     LIVE GAME MODE — active / scoring / aborted
══════════════════════════════════════════════════════════════════════════ --}}
<div
    x-data='goBoard(
        {{ $game->id }},
        @json($game->board_state ?? array_fill(0, $bs*$bs, 0)),
        "{{ $myColor ?? '' }}",
        {{ $bs }},
        "{{ $game->current_color }}",
        "{{ $game->ko_point ?? '' }}",
        {{ $game->captures_black }},
        {{ $game->captures_white }},
        "{{ $game->status }}",
        {{ $game->move_number }},
        {{ $game->komi }},
        "{{ $game->result ?? '' }}",
        {{ auth()->check() && auth()->user()->confirm_move ? 'true' : 'false' }},
        @json($game->dead_stones ?? []),
        {{ $game->score_confirmed_black ? 'true' : 'false' }},
        {{ $game->score_confirmed_white ? 'true' : 'false' }}
    )'
    style="display:grid;grid-template-columns:1fr;gap:1rem"
    id="game-root">

    {{-- ── Clock (mobile: top; desktop: top-right) ── --}}
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
            '{{ $game->status }}'
        )">
        <div class="card-body" style="padding:0.875rem">
            <div class="clock-panel">
                {{-- Black --}}
                <div class="clock-player"
                    :class="{ active: activeColor==='black', 'low-time': activeColor==='black' && (blackTime < 30 || (blackTime === 0 && blackPeriods > 0)) }">
                    <div style="display:flex;align-items:center;justify-content:center;gap:0.25rem;margin-bottom:0.375rem;flex-wrap:wrap">
                        <span style="width:12px;height:12px;background:#111;border-radius:50%;display:inline-block;border:1.5px solid #555;flex-shrink:0"></span>
                        <span style="font-size:0.75rem;font-weight:600;color:#6B6B80;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:80px">{{ $game->blackPlayer?->getDisplayName() }}</span>
                        @if($game->blackPlayer?->is_bot)
                        <span style="font-size:0.5625rem;font-weight:700;padding:0.0625rem 0.3125rem;border-radius:999px;background:#111118;color:#fff;letter-spacing:0.04em;flex-shrink:0">BOT</span>
                        @endif
                    </div>
                    <div class="clock-time" x-text="formatTime(getDisplayTime('black'))"></div>
                    <div x-show="clockType==='byoyomi'" style="font-size:0.6875rem;color:#6B6B80;margin-top:0.25rem;font-weight:600" x-text="blackPeriods + ' ×' + byoyomiSeconds + 'วิ'"></div>
                </div>

                {{-- Move counter --}}
                <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;gap:0.25rem;flex-shrink:0">
                    <span style="font-size:1rem;font-weight:800;color:#111118" x-text="moveNumber"></span>
                    <span style="font-size:0.625rem;font-weight:600;color:#9CA3AF;text-transform:uppercase;letter-spacing:0.05em">ตา</span>
                </div>

                {{-- White --}}
                <div class="clock-player"
                    :class="{ active: activeColor==='white', 'low-time': activeColor==='white' && (whiteTime < 30 || (whiteTime === 0 && whitePeriods > 0)) }">
                    <div style="display:flex;align-items:center;justify-content:center;gap:0.25rem;margin-bottom:0.375rem;flex-wrap:wrap">
                        <span style="width:12px;height:12px;background:#fff;border-radius:50%;display:inline-block;border:1.5px solid #ccc;flex-shrink:0"></span>
                        <span style="font-size:0.75rem;font-weight:600;color:#6B6B80;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:80px">{{ $game->whitePlayer?->getDisplayName() }}</span>
                        @if($game->whitePlayer?->is_bot)
                        <span style="font-size:0.5625rem;font-weight:700;padding:0.0625rem 0.3125rem;border-radius:999px;background:#111118;color:#fff;letter-spacing:0.04em;flex-shrink:0">BOT</span>
                        @endif
                    </div>
                    <div class="clock-time" x-text="formatTime(getDisplayTime('white'))"></div>
                    <div x-show="clockType==='byoyomi'" style="font-size:0.6875rem;color:#6B6B80;margin-top:0.25rem;font-weight:600" x-text="whitePeriods + ' ×' + byoyomiSeconds + 'วิ'"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Left Column: Board (mobile: 2nd; desktop: full-height left) ── --}}
    <div class="game-col-left">

        <div class="card game-board-card">
            <div class="board-wrap">
                <div class="board-svg-wrap" @click="handleBoardClick($event)"
                    @mousemove="handleBoardHover($event)"
                    @mouseleave="hoverIdx = null">
                    <svg viewBox="0 0 {{ $vb }} {{ $vb }}" xmlns="http://www.w3.org/2000/svg">

                        {{-- Board background --}}
                        <rect width="{{ $vb }}" height="{{ $vb }}" fill="#E8B862" rx="8"/>
                        <rect x="{{ $pad * 0.5 }}" y="{{ $pad * 0.5 }}"
                              width="{{ ($bs + 1) * $cell - $pad }}" height="{{ ($bs + 1) * $cell - $pad }}"
                              fill="#D4A537" rx="6" opacity="0.4"/>

                        {{-- Grid lines --}}
                        @for($i = 1; $i <= $bs; $i++)
                        <line x1="{{ $pad }}" y1="{{ $i * $cell }}"
                              x2="{{ $bs * $cell }}" y2="{{ $i * $cell }}"
                              stroke="#7C5524" stroke-width="1" opacity="0.7"/>
                        <line x1="{{ $i * $cell }}" y1="{{ $pad }}"
                              x2="{{ $i * $cell }}" y2="{{ $bs * $cell }}"
                              stroke="#7C5524" stroke-width="1" opacity="0.7"/>
                        @endfor

                        {{-- Star points --}}
                        @foreach($starPts as [$r, $c])
                        <circle cx="{{ ($c + 1) * $cell }}" cy="{{ ($r + 1) * $cell }}"
                                r="4" fill="#5C3D11" opacity="0.8"/>
                        @endforeach

                        {{-- Row/column labels --}}
                        @php $letters = str_split('ABCDEFGHJKLMNOPQRST'); @endphp
                        @for($i = 0; $i < $bs; $i++)
                        <text x="{{ ($i + 1) * $cell }}" y="{{ $pad * 0.62 }}"
                              text-anchor="middle" font-size="{{ $cell * 0.36 }}"
                              font-family="monospace" fill="#7C5524" opacity="0.6">{{ $letters[$i] }}</text>
                        <text x="{{ $pad * 0.32 }}" y="{{ ($i + 1) * $cell + $cell * 0.13 }}"
                              text-anchor="middle" font-size="{{ $cell * 0.36 }}"
                              font-family="monospace" fill="#7C5524" opacity="0.6">{{ $bs - $i }}</text>
                        @endfor

                        {{-- Hover hint --}}
                        <circle x-show="hoverIdx !== null && isMyTurn && board[hoverIdx] === 0"
                            :cx="hoverIdx !== null ? (hoverIdx % boardSize + 1)*{{ $cell }} : 0"
                            :cy="hoverIdx !== null ? (Math.floor(hoverIdx / boardSize) + 1)*{{ $cell }} : 0"
                            r="{{ $cell * 0.44 }}"
                            :fill="myColor === 'black' ? '#111' : '#f5f0e0'"
                            opacity="0.45"
                            style="pointer-events:none"/>

                        <g x-ref="stonesLayer" x-effect="renderStones()"></g>

                    </svg>
                </div>
            </div>

            {{-- Captures + Turn indicator --}}
            <div style="padding:0.75rem 1rem;display:flex;justify-content:space-between;align-items:center;border-top:1.5px solid #E2E2E7">
                <div style="display:flex;gap:1.25rem;font-size:0.8125rem;color:#6B6B80;font-weight:500">
                    <span>⚫ จับ: <strong x-text="capturesBlack" style="color:#111118"></strong></span>
                    <span>⚪ จับ: <strong x-text="capturesWhite" style="color:#111118"></strong></span>
                </div>
                <div>
                    <span x-show="isMyTurn" style="font-size:0.8125rem;font-weight:700;color:#4F46E5;display:flex;align-items:center;gap:0.25rem">
                        <ion-icon name="radio-button-on" style="color:#22C55E"></ion-icon> ถึงตาคุณ
                    </span>
                    <span x-show="!isMyTurn && myColor !== ''" style="font-size:0.8125rem;color:#6B6B80">รอฝ่ายตรงข้าม...</span>
                    <span x-show="myColor === ''" style="font-size:0.8125rem;color:#6B6B80">
                        <ion-icon name="eye-outline"></ion-icon> ดูเกม
                    </span>
                </div>
            </div>

            {{-- Confirm move bar --}}
            @if($myColor)
            <div x-show="pendingCoord !== null"
                style="padding:0.625rem 1rem;border-top:2px solid #4F46E5;background:#EEF2FF;display:flex;align-items:center;gap:0.625rem">
                <span style="font-size:0.8125rem;font-weight:600;color:#4338CA;flex:1">
                    <ion-icon name="location-outline"></ion-icon>
                    เดินหมากที่ <strong x-text="pendingCoord"></strong> — ยืนยัน?
                </span>
                <button @click="confirmPendingMove()"
                    style="background:#4F46E5;color:#fff;font-size:0.8125rem;font-weight:600;padding:0.375rem 0.875rem;border-radius:6px;border:none;cursor:pointer">
                    ยืนยัน
                </button>
                <button @click="cancelPendingMove()"
                    style="background:#fff;color:#6B7280;font-size:0.8125rem;font-weight:600;padding:0.375rem 0.75rem;border-radius:6px;border:1.5px solid #D1D5DB;cursor:pointer">
                    ยกเลิก
                </button>
            </div>
            @endif

            {{-- Controls --}}
            @if($myColor)
            <div style="padding:0.75rem 1rem;border-top:1.5px solid #E2E2E7;display:flex;gap:0.625rem">
                {{-- Normal play buttons --}}
                <template x-if="!scoringProposal && !gameOver">
                    <button @click="pass()" :disabled="!isMyTurn"
                        class="btn btn-secondary" style="flex:1">
                        <ion-icon name="remove-circle-outline"></ion-icon> ผ่าน
                    </button>
                </template>
                <template x-if="!scoringProposal && !gameOver">
                    <button @click="resignConfirm()"
                        class="btn btn-danger" style="flex:1">
                        <ion-icon name="flag-outline"></ion-icon> ยอมแพ้
                    </button>
                </template>

                {{-- Scoring proposal buttons --}}
                <template x-if="scoringProposal && !gameOver && !myConfirmed()">
                    <button @click="confirmScore()"
                        class="btn btn-primary" style="flex:1">
                        <ion-icon name="checkmark-circle-outline"></ion-icon> ยืนยันผลคะแนน
                    </button>
                </template>
                <template x-if="scoringProposal && !gameOver && myConfirmed()">
                    <button disabled
                        class="btn btn-primary" style="flex:1;opacity:0.55;cursor:not-allowed">
                        <ion-icon name="hourglass-outline"></ion-icon> รอฝ่ายตรงข้าม...
                    </button>
                </template>
                <template x-if="scoringProposal && !gameOver">
                    <button @click="cancelScoring()"
                        class="btn btn-secondary" style="flex:1">
                        <ion-icon name="close-circle-outline"></ion-icon> เล่นต่อ
                    </button>
                </template>
            </div>
            @endif

            {{-- Scoring proposal panel --}}
            <div x-show="scoringProposal && !gameOver" style="border-top:1.5px solid #BBF7D0;background:#F0FDF4">

                {{-- Instruction --}}
                <div style="padding:0.625rem 1rem 0;display:flex;align-items:center;gap:0.375rem">
                    <ion-icon name="hand-left-outline" style="color:#15803D;font-size:1rem;flex-shrink:0"></ion-icon>
                    <p style="font-size:0.8125rem;font-weight:700;color:#15803D;margin:0">โปรดเลือกกลุ่มหมากที่ตายแล้ว</p>
                </div>
                <p style="font-size:0.75rem;color:#4B7C59;margin:0;padding:0.125rem 1rem 0.5rem">กดบนหมากเพื่อ toggle กลุ่มที่ตาย แล้วกดยืนยันผลคะแนน</p>

                {{-- Score display --}}
                <div style="padding:0 1rem 0.5rem;display:grid;grid-template-columns:1fr 1fr;gap:0.5rem">
                    <div style="text-align:center;background:#fff;border-radius:6px;padding:0.375rem 0.5rem;border:1.5px solid #E2E2E7">
                        <div style="font-size:0.6875rem;color:#6B6B80;font-weight:600;margin-bottom:2px">⚫ ดำ</div>
                        <div style="font-size:1.125rem;font-weight:800;color:#111118" x-text="scoreBlack.toFixed(1)"></div>
                    </div>
                    <div style="text-align:center;background:#fff;border-radius:6px;padding:0.375rem 0.5rem;border:1.5px solid #E2E2E7">
                        <div style="font-size:0.6875rem;color:#6B6B80;font-weight:600;margin-bottom:2px">⚪ ขาว</div>
                        <div style="font-size:1.125rem;font-weight:800;color:#111118" x-text="scoreWhite.toFixed(1)"></div>
                    </div>
                </div>

                {{-- Score lead --}}
                <p style="font-size:0.8125rem;font-weight:700;text-align:center;margin:0;padding:0 1rem 0.5rem"
                    :style="scoreBlack > scoreWhite ? 'color:#15803D' : (scoreWhite > scoreBlack ? 'color:#1D4ED8' : 'color:#6B6B80')"
                    x-text="scoreBlack > scoreWhite ? ('ดำนำ +' + (scoreBlack - scoreWhite).toFixed(1)) : (scoreWhite > scoreBlack ? ('ขาวนำ +' + (scoreWhite - scoreBlack).toFixed(1)) : 'เสมอ')">
                </p>

                {{-- Confirmation status --}}
                <div style="padding:0.375rem 1rem 0.625rem;display:grid;grid-template-columns:1fr 1fr;gap:0.5rem">
                    <div :style="scoreConfirmedBlack ? 'background:#DCFCE7;border:1.5px solid #86EFAC' : 'background:#fff;border:1.5px solid #E2E2E7'"
                        style="text-align:center;border-radius:6px;padding:0.3rem 0.5rem;font-size:0.75rem;font-weight:700">
                        <span x-text="scoreConfirmedBlack ? '⚫ ✓ ยืนยันแล้ว' : '⚫ รอยืนยัน'"
                            :style="scoreConfirmedBlack ? 'color:#15803D' : 'color:#9CA3AF'"></span>
                    </div>
                    <div :style="scoreConfirmedWhite ? 'background:#DCFCE7;border:1.5px solid #86EFAC' : 'background:#fff;border:1.5px solid #E2E2E7'"
                        style="text-align:center;border-radius:6px;padding:0.3rem 0.5rem;font-size:0.75rem;font-weight:700">
                        <span x-text="scoreConfirmedWhite ? '⚪ ✓ ยืนยันแล้ว' : '⚪ รอยืนยัน'"
                            :style="scoreConfirmedWhite ? 'color:#15803D' : 'color:#9CA3AF'"></span>
                    </div>
                </div>
            </div>

            {{-- Error / Result messages --}}
            <div x-show="errorMsg" style="padding:0.625rem 1rem;border-top:1.5px solid #FECACA;background:#FEF2F2">
                <p style="font-size:0.8125rem;color:#DC2626;margin:0;display:flex;align-items:center;gap:0.375rem">
                    <ion-icon name="warning-outline"></ion-icon>
                    <span x-text="errorMsg"></span>
                </p>
            </div>
            <div x-show="gameOver" style="padding:1rem;border-top:1.5px solid #DDD6FE;background:#F5F3FF">
                <p style="font-size:1rem;font-weight:800;color:#4C1D95;text-align:center;margin:0" x-text="gameResult"></p>
            </div>
        </div>

    </div>

    {{-- ── Right Sidebar (mobile: 3rd; desktop: bottom-right) ── --}}
    <div class="game-col-right" style="display:flex;flex-direction:column;gap:0.875rem">

        {{-- Game Info --}}
        <div class="card" style="flex-shrink:0">
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
                    @if($game->handicap > 0)
                    <div>
                        <dt style="font-size:0.6875rem;font-weight:600;color:#6B6B80;text-transform:uppercase;letter-spacing:0.04em;margin-bottom:2px">หมากต่อ</dt>
                        <dd style="font-size:0.9375rem;font-weight:700;margin:0">{{ $game->handicap }}</dd>
                    </div>
                    @endif
                    <div>
                        <dt style="font-size:0.6875rem;font-weight:600;color:#6B6B80;text-transform:uppercase;letter-spacing:0.04em;margin-bottom:2px">นาฬิกา</dt>
                        <dd style="font-size:0.9375rem;font-weight:700;margin:0">{{ $game->clock_type === 'byoyomi' ? 'เบียวโยมิ' : 'ฟิชเชอร์' }}</dd>
                    </div>
                </dl>
            </div>
        </div>

        {{-- Chat --}}
        @if($chatRoom)
        <div class="card game-chat-card" style="display:flex;flex-direction:column;flex:1;min-height:0"
            x-data="chatWindow({{ $chatRoom->id }})">
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
                <input x-model="draft" type="text" placeholder="พิมพ์ข้อความ..."
                    class="input" style="font-size:0.8125rem;padding:0.375rem 0.625rem">
                <button type="submit" class="btn btn-primary btn-sm">
                    <ion-icon name="send-outline"></ion-icon>
                </button>
            </form>
        </div>
        @endif

        {{-- Move History --}}
        <div class="card" style="flex-shrink:0">
            <div class="card-header">
                <ion-icon name="list-outline"></ion-icon> ประวัติตา
            </div>
            <div style="max-height:200px;overflow-y:auto" id="move-history">
                @forelse($game->moves as $move)
                <div style="display:flex;gap:0.625rem;padding:0.375rem 1rem;font-size:0.8125rem;border-bottom:1px solid #F5F5F7;align-items:center">
                    <span style="color:#9CA3AF;font-family:monospace;font-size:0.75rem;min-width:1.5rem">{{ $move->move_number }}</span>
                    <span>{{ $move->color === 'black' ? '⚫' : '⚪' }}</span>
                    <span style="font-weight:600;font-family:monospace">{{ $move->coordinate ?? 'ผ่าน' }}</span>
                </div>
                @empty
                <p style="padding:1rem;text-align:center;font-size:0.8125rem;color:#9CA3AF">ยังไม่มีตาเดิน</p>
                @endforelse
            </div>
        </div>

    </div>
</div>
@endif
@endsection
