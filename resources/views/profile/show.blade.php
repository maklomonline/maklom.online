@extends('layouts.app')
@section('title', $user->getDisplayName())
@section('content')
<div x-data="{ challengeOpen: false, botPlayOpen: false, clockType: 'byoyomi', challengeSent: false }">
<div class="max-w-2xl mx-auto">
    <div class="bg-white rounded-xl border border-gray-200 p-6 mb-6">
        <div class="flex items-start gap-5">
            <img src="{{ $user->getAvatarUrl() }}" class="w-20 h-20 rounded-full object-cover">
            <div class="flex-1">
                <div class="flex items-center gap-3 flex-wrap">
                    <h1 class="text-2xl font-bold text-gray-900">{{ $user->getDisplayName() }}</h1>
                    @if($user->is_bot)
                    <span style="font-size:0.75rem;font-weight:700;padding:0.125rem 0.5rem;border-radius:999px;background:#111118;color:#fff;letter-spacing:0.05em">BOT</span>
                    @endif
                    <span class="text-sm bg-indigo-100 text-indigo-700 px-2 py-0.5 rounded font-medium">{{ $user->rank }}</span>
                    @if(!$user->is_bot)
                    <span class="text-xs text-gray-400 font-mono">{{ $user->rank_points }} คะแนน</span>
                    @endif
                </div>
                <p class="text-sm text-gray-400">{{ $user->username }}</p>
                @if($user->bio)<p class="text-sm text-gray-600 mt-2">{{ $user->bio }}</p>@endif
                <p class="text-xs text-gray-400 mt-1">
                    ออนไลน์ล่าสุด: {{ $user->last_seen_at?->diffForHumans() ?? 'ไม่ทราบ' }}
                </p>
            </div>
            @auth
            @if(auth()->id() !== $user->id)
            <div class="flex gap-2 flex-col">
                @if($user->is_bot)
                <button @click="botPlayOpen = true"
                   class="text-sm bg-indigo-600 text-white px-3 py-1.5 rounded-lg hover:bg-indigo-700 transition text-center cursor-pointer border-none font-medium">
                    <ion-icon name="hardware-chip-outline" style="vertical-align:-2px"></ion-icon> เล่นด้วย
                </button>
                @else
                <button @click="challengeOpen = true"
                   class="text-sm bg-gray-900 text-white px-3 py-1.5 rounded-lg hover:bg-gray-700 transition text-center cursor-pointer border-none font-medium">
                    ⚔ ท้าดวล
                </button>
                @if(auth()->user()->isFriendWith($user))
                <span class="text-xs text-green-600 font-medium text-center">✓ เป็นเพื่อนกัน</span>
                @elseif($user->hasPendingFriendRequestFrom(auth()->user()))
                <span class="text-xs text-gray-500 font-medium text-center">✉ ส่งคำขอเป็นเพื่อนแล้ว</span>
                @else
                <form method="POST" action="{{ route('friends.request', $user) }}">
                    @csrf
                    <button class="text-sm bg-indigo-600 text-white px-3 py-1.5 rounded-lg hover:bg-indigo-700 transition w-full">+ เพิ่มเพื่อน</button>
                </form>
                @endif
                @endif
            </div>
            @else
            <a href="{{ route('profile.edit') }}" class="text-sm border border-gray-200 text-gray-600 px-3 py-1.5 rounded-lg hover:bg-gray-50 transition">แก้ไขโปรไฟล์</a>
            @endif
            @endauth
        </div>
    </div>

    {{-- Stats --}}
    @if($user->stats)
    <div class="grid grid-cols-4 gap-3 mb-6">
        @foreach(['games_played' => 'เกมทั้งหมด', 'games_won' => 'ชนะ', 'games_lost' => 'แพ้', 'win_streak' => 'สตรีก'] as $key => $label)
        <div class="bg-white rounded-lg border border-gray-200 p-3 text-center">
            <div class="text-xl font-bold text-gray-900">{{ $user->stats->$key }}</div>
            <div class="text-xs text-gray-400">{{ $label }}</div>
        </div>
        @endforeach
    </div>
    @endif

    {{-- Recent Games --}}
    <h2 class="font-semibold text-gray-900 mb-3">เกมล่าสุด</h2>
    <div class="space-y-2">
        @forelse($recentGames as $game)
        <a href="{{ route('games.show', $game) }}" class="block bg-white rounded-lg border border-gray-200 p-3 hover:shadow-sm transition">
            <div class="flex justify-between text-sm">
                <span>⚫ {{ $game->blackPlayer?->getDisplayName() }} VS ⚪ {{ $game->whitePlayer?->getDisplayName() }}</span>
                <span class="{{ $game->winner_id === $user->id ? 'text-green-600' : 'text-red-600' }} font-medium">{{ $game->result }}</span>
            </div>
            <div class="text-xs text-gray-400 mt-0.5">{{ $game->board_size }}×{{ $game->board_size }} · {{ $game->finished_at?->format('d/m/Y') }}</div>
        </a>
        @empty
        <div class="text-center text-gray-400 py-4">ยังไม่มีประวัติเกม</div>
        @endforelse
    </div>
</div>

{{-- ── Modal: ท้าดวล ─────────────────────────────── --}}
@auth
@if(auth()->id() !== $user->id && !$user->is_bot)
<div x-show="challengeOpen" x-cloak
     style="position:fixed;inset:0;z-index:200;display:flex;align-items:center;justify-content:center;padding:1rem"
     @click.self="challengeOpen = false">
    <div style="position:absolute;inset:0;background:rgba(0,0,0,0.45)"></div>
    <div style="position:relative;background:#fff;border-radius:1rem;width:100%;max-width:420px;padding:1.5rem;box-shadow:0 20px 60px rgba(0,0,0,0.2)">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem">
            <h2 style="font-size:1rem;font-weight:700;color:#111118;margin:0">⚔ ท้าดวล {{ $user->getDisplayName() }}</h2>
            <button @click="challengeOpen = false" style="background:none;border:none;cursor:pointer;color:#6B6B80;font-size:1.25rem;padding:0;line-height:1">×</button>
        </div>

        <div x-show="challengeSent" style="text-align:center;padding:1.5rem 0">
            <div style="font-size:2rem;margin-bottom:0.75rem">✅</div>
            <p style="font-weight:700;color:#111118;margin:0 0 0.25rem">ส่งคำท้าดวลแล้ว!</p>
            <p style="font-size:0.875rem;color:#6B6B80;margin:0">รอ {{ $user->getDisplayName() }} ตอบรับ</p>
            <button @click="challengeOpen = false; challengeSent = false" style="margin-top:1rem;padding:0.5rem 1.5rem;background:#111118;color:#fff;border:none;border-radius:0.5rem;font-size:0.875rem;font-weight:600;cursor:pointer;font-family:inherit">ปิด</button>
        </div>

        <form x-show="!challengeSent"
              @submit.prevent="
                axios.post('{{ route('challenges.send', $user) }}', Object.fromEntries(new FormData($el)))
                    .then(() => challengeSent = true)
                    .catch(e => alert(e.response?.data?.message || 'เกิดข้อผิดพลาด'))
              ">
            @csrf

            {{-- Board size --}}
            <div style="margin-bottom:0.875rem">
                <label style="font-size:0.8125rem;font-weight:600;color:#374151;display:block;margin-bottom:0.375rem">ขนาดกระดาน</label>
                <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:0.5rem">
                    @foreach([['9','9×9'],['13','13×13'],['19','19×19']] as [$val,$lbl])
                    <label style="cursor:pointer">
                        <input type="radio" name="board_size" value="{{ $val }}" {{ $val === '19' ? 'checked' : '' }} style="display:none" class="chal-size-radio">
                        <div class="chal-size-btn" style="text-align:center;padding:0.5rem;border-radius:0.5rem;border:1.5px solid #E2E2E7;font-size:0.75rem;font-weight:600;cursor:pointer;transition:all 0.15s;color:#6B6B80">{{ $lbl }}</div>
                    </label>
                    @endforeach
                </div>
            </div>

            {{-- Handicap --}}
            <div style="margin-bottom:0.875rem">
                <label style="font-size:0.8125rem;font-weight:600;color:#374151;display:block;margin-bottom:0.375rem">ฮันดิแคป</label>
                <select name="handicap" class="form-select" style="width:100%">
                    @for($i = 0; $i <= 9; $i++)
                    <option value="{{ $i }}">{{ $i === 0 ? 'ไม่มี' : $i . ' หมาก' }}</option>
                    @endfor
                </select>
            </div>

            {{-- Clock type --}}
            <div style="margin-bottom:0.875rem">
                <label style="font-size:0.8125rem;font-weight:600;color:#374151;display:block;margin-bottom:0.375rem">ระบบนาฬิกา</label>
                <select name="clock_type" x-model="clockType" class="form-select" style="width:100%">
                    <option value="byoyomi">เบียวโยมิ</option>
                    <option value="fischer">ฟิชเชอร์</option>
                </select>
            </div>

            {{-- Main time --}}
            <div style="margin-bottom:0.875rem">
                <label style="font-size:0.8125rem;font-weight:600;color:#374151;display:block;margin-bottom:0.375rem">เวลาหลัก</label>
                <select name="main_time" class="form-select" style="width:100%">
                    <option value="300">5 นาที</option>
                    <option value="600" selected>10 นาที</option>
                    <option value="1200">20 นาที</option>
                    <option value="1800">30 นาที</option>
                </select>
            </div>

            <div x-show="clockType === 'byoyomi'" style="display:grid;grid-template-columns:1fr 1fr;gap:0.5rem;margin-bottom:0.875rem">
                <div>
                    <label style="font-size:0.8125rem;font-weight:600;color:#374151;display:block;margin-bottom:0.375rem">Period</label>
                    <select name="byoyomi_periods" class="form-select" style="width:100%">
                        <option value="1">1</option>
                        <option value="3" selected>3</option>
                        <option value="5">5</option>
                    </select>
                </div>
                <div>
                    <label style="font-size:0.8125rem;font-weight:600;color:#374151;display:block;margin-bottom:0.375rem">วินาที/Period</label>
                    <select name="byoyomi_seconds" class="form-select" style="width:100%">
                        <option value="10">10</option>
                        <option value="30" selected>30</option>
                        <option value="60">60</option>
                    </select>
                </div>
            </div>

            <div x-show="clockType === 'fischer'" style="margin-bottom:0.875rem">
                <label style="font-size:0.8125rem;font-weight:600;color:#374151;display:block;margin-bottom:0.375rem">เพิ่มเวลา/ตา (วินาที)</label>
                <select name="fischer_increment" class="form-select" style="width:100%">
                    <option value="5">5</option>
                    <option value="10" selected>10</option>
                    <option value="30">30</option>
                </select>
            </div>

            <button type="submit"
                style="width:100%;padding:0.75rem;background:#111118;color:#fff;border:none;border-radius:0.625rem;font-size:0.9375rem;font-weight:700;cursor:pointer;font-family:inherit">
                ส่งคำท้าดวล
            </button>
        </form>
    </div>
</div>
@endif

{{-- ── Modal: เล่นกับบอท ─────────────────────────── --}}
@if($user->is_bot)
<div x-show="botPlayOpen" x-cloak
     style="position:fixed;inset:0;z-index:200;display:flex;align-items:center;justify-content:center;padding:1rem"
     @click.self="botPlayOpen = false">
    <div style="position:absolute;inset:0;background:rgba(0,0,0,0.45)"></div>
    <div style="position:relative;background:#fff;border-radius:1rem;width:100%;max-width:420px;padding:1.5rem;box-shadow:0 20px 60px rgba(0,0,0,0.2)">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem">
            <h2 style="font-size:1rem;font-weight:700;color:#111118;margin:0">
                <ion-icon name="hardware-chip-outline" style="vertical-align:-2px"></ion-icon>
                เล่นกับ {{ $user->getDisplayName() }}
            </h2>
            <button @click="botPlayOpen = false" style="background:none;border:none;cursor:pointer;color:#6B6B80;font-size:1.25rem;padding:0;line-height:1">×</button>
        </div>

        <form action="{{ route('bots.play', $user) }}" method="POST">
            @csrf

            {{-- Board size --}}
            <div style="margin-bottom:0.875rem">
                <label style="font-size:0.8125rem;font-weight:600;color:#374151;display:block;margin-bottom:0.375rem">ขนาดกระดาน</label>
                <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:0.5rem">
                    @foreach([['9','9×9'],['13','13×13'],['19','19×19 (มาตรฐาน)']] as [$val,$lbl])
                    <label style="cursor:pointer">
                        <input type="radio" name="board_size" value="{{ $val }}" {{ $val === '19' ? 'checked' : '' }} style="display:none" class="bot-size-radio">
                        <div class="bot-size-btn" style="text-align:center;padding:0.5rem;border-radius:0.5rem;border:1.5px solid #E2E2E7;font-size:0.75rem;font-weight:600;cursor:pointer;transition:all 0.15s;color:#6B6B80">{{ $lbl }}</div>
                    </label>
                    @endforeach
                </div>
            </div>

            {{-- Player color --}}
            <div style="margin-bottom:0.875rem">
                <label style="font-size:0.8125rem;font-weight:600;color:#374151;display:block;margin-bottom:0.375rem">สีหมากของคุณ</label>
                <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:0.5rem">
                    @foreach([['black','⚫ ดำ'],['white','⚪ ขาว'],['random','🎲 สุ่ม']] as [$val,$lbl])
                    <label style="cursor:pointer">
                        <input type="radio" name="player_color" value="{{ $val }}" {{ $val === 'black' ? 'checked' : '' }} style="display:none" class="bot-color-radio">
                        <div class="bot-color-btn" style="text-align:center;padding:0.5rem;border-radius:0.5rem;border:1.5px solid #E2E2E7;font-size:0.75rem;font-weight:600;cursor:pointer;transition:all 0.15s;color:#6B6B80">{{ $lbl }}</div>
                    </label>
                    @endforeach
                </div>
            </div>

            {{-- Handicap --}}
            <div style="margin-bottom:0.875rem">
                <label style="font-size:0.8125rem;font-weight:600;color:#374151;display:block;margin-bottom:0.375rem">ฮันดิแคป</label>
                <select name="handicap" class="form-select" style="width:100%">
                    @for($i = 0; $i <= 9; $i++)
                    <option value="{{ $i }}">{{ $i === 0 ? 'ไม่มี' : $i . ' หมาก' }}</option>
                    @endfor
                </select>
            </div>

            {{-- Clock type --}}
            <div style="margin-bottom:0.875rem">
                <label style="font-size:0.8125rem;font-weight:600;color:#374151;display:block;margin-bottom:0.375rem">ระบบนาฬิกา</label>
                <select name="clock_type" x-model="clockType" class="form-select" style="width:100%">
                    <option value="byoyomi">เบียวโยมิ</option>
                    <option value="fischer">ฟิชเชอร์</option>
                </select>
            </div>

            {{-- Main time --}}
            <div style="margin-bottom:0.875rem">
                <label style="font-size:0.8125rem;font-weight:600;color:#374151;display:block;margin-bottom:0.375rem">เวลาหลัก</label>
                <select name="main_time" class="form-select" style="width:100%">
                    <option value="300">5 นาที</option>
                    <option value="600" selected>10 นาที</option>
                    <option value="1200">20 นาที</option>
                    <option value="1800">30 นาที</option>
                </select>
            </div>

            <div x-show="clockType === 'byoyomi'" style="display:grid;grid-template-columns:1fr 1fr;gap:0.5rem;margin-bottom:0.875rem">
                <div>
                    <label style="font-size:0.8125rem;font-weight:600;color:#374151;display:block;margin-bottom:0.375rem">Period</label>
                    <select name="byoyomi_periods" class="form-select" style="width:100%">
                        <option value="1">1</option>
                        <option value="3" selected>3</option>
                        <option value="5">5</option>
                    </select>
                </div>
                <div>
                    <label style="font-size:0.8125rem;font-weight:600;color:#374151;display:block;margin-bottom:0.375rem">วินาที/Period</label>
                    <select name="byoyomi_seconds" class="form-select" style="width:100%">
                        <option value="10">10</option>
                        <option value="30" selected>30</option>
                        <option value="60">60</option>
                    </select>
                </div>
            </div>

            <div x-show="clockType === 'fischer'" style="margin-bottom:0.875rem">
                <label style="font-size:0.8125rem;font-weight:600;color:#374151;display:block;margin-bottom:0.375rem">เพิ่มเวลา/ตา (วินาที)</label>
                <select name="fischer_increment" class="form-select" style="width:100%">
                    <option value="5">5</option>
                    <option value="10" selected>10</option>
                    <option value="30">30</option>
                </select>
            </div>

            <button type="submit"
                style="width:100%;padding:0.75rem;background:#111118;color:#fff;border:none;border-radius:0.625rem;font-size:0.9375rem;font-weight:700;cursor:pointer;font-family:inherit">
                <ion-icon name="play-circle-outline" style="font-size:1.125rem;vertical-align:-3px;margin-right:0.375rem"></ion-icon>
                เริ่มเกม
            </button>
        </form>
    </div>
</div>
@endif
@endauth

</div>

<style>
input[type="radio"]:checked + .chal-size-btn,
input[type="radio"]:checked + .bot-size-btn,
input[type="radio"]:checked + .bot-color-btn {
    border-color: #4F46E5 !important;
    background: #F5F3FF !important;
    color: #4F46E5 !important;
}
</style>
@endsection
