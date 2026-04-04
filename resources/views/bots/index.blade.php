@extends('layouts.app')
@section('title', 'เล่นกับคอมพิวเตอร์')

@section('content')
<div style="max-width:960px;margin:0 auto">

    <div style="display:flex;align-items:flex-end;justify-content:space-between;flex-wrap:wrap;gap:0.75rem;margin-bottom:1.5rem">
        <div>
            <h1 style="font-size:1.5rem;font-weight:800;color:#111118;margin:0 0 0.25rem">เล่นกับคอมพิวเตอร์</h1>
            <p style="font-size:0.875rem;color:#6B6B80;margin:0">เลือกบัญชีคอมพิวเตอร์ที่ต้องการเล่นด้วย</p>
        </div>
        <a href="{{ route('bot.register') }}"
            style="display:inline-flex;align-items:center;gap:0.375rem;padding:0.5rem 1rem;background:#4F46E5;color:#fff;border-radius:0.625rem;font-size:0.8125rem;font-weight:700;text-decoration:none;transition:opacity 0.15s"
            onmouseenter="this.style.opacity='0.85'" onmouseleave="this.style.opacity='1'">
            <ion-icon name="add-outline" style="font-size:1rem"></ion-icon>
            ลงทะเบียน Bot ของคุณ
        </a>
    </div>

    @if($bots->isEmpty())
    <div style="text-align:center;padding:3rem;background:#fff;border-radius:1rem;border:1.5px solid #E2E2E7;color:#6B6B80">
        <ion-icon name="hardware-chip-outline" style="font-size:2.5rem;display:block;margin:0 auto 0.75rem;color:#C7C7D0"></ion-icon>
        <p style="margin:0 0 0.5rem;font-weight:600;color:#374151">ยังไม่มีบัญชีคอมพิวเตอร์</p>
        <p style="margin:0;font-size:0.875rem">
            <a href="{{ route('bot.register') }}" style="color:#4F46E5;font-weight:600">ลงทะเบียน bot</a> ของคุณเองได้เลย
        </p>
    </div>
    @else
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:1rem">
        @foreach($bots as $bot)
        @php
            $isOnline = $bot->isBotOnline();
            $levelLabels = [
                '30k' => ['color' => '#374151', 'bg' => '#F3F4F6'],
                '25k' => ['color' => '#374151', 'bg' => '#F3F4F6'],
                '20k' => ['color' => '#374151', 'bg' => '#F3F4F6'],
                '15k' => ['color' => '#374151', 'bg' => '#F3F4F6'],
                '10k' => ['color' => '#374151', 'bg' => '#F3F4F6'],
                '8k'  => ['color' => '#059669', 'bg' => '#D1FAE5'],
                '5k'  => ['color' => '#0284C7', 'bg' => '#E0F2FE'],
                '2k'  => ['color' => '#7C3AED', 'bg' => '#EDE9FE'],
                '1k'  => ['color' => '#7C3AED', 'bg' => '#EDE9FE'],
                '1d'  => ['color' => '#B45309', 'bg' => '#FEF3C7'],
                '2d'  => ['color' => '#B45309', 'bg' => '#FEF3C7'],
                '3d'  => ['color' => '#DC2626', 'bg' => '#FEE2E2'],
                '4d'  => ['color' => '#DC2626', 'bg' => '#FEE2E2'],
                '5d'  => ['color' => '#9D174D', 'bg' => '#FCE7F3'],
                '6d'  => ['color' => '#9D174D', 'bg' => '#FCE7F3'],
            ];
            $lv = $levelLabels[$bot->rank] ?? ['color' => '#6B6B80', 'bg' => '#F5F5F7'];
        @endphp
        <div x-data="{ challengeOpen: false, sent: false, error: '' }"
             style="display:flex;flex-direction:column;padding:1.5rem 1rem;background:#fff;border-radius:1rem;border:1.5px solid #E2E2E7;text-align:center;position:relative">

            {{-- Online indicator --}}
            <div style="position:absolute;top:0.75rem;right:0.75rem;display:flex;align-items:center;gap:0.25rem;font-size:0.6875rem;font-weight:600;
                {{ $isOnline ? 'color:#16A34A' : 'color:#9CA3AF' }}">
                <span style="width:7px;height:7px;border-radius:50%;background:{{ $isOnline ? '#16A34A' : '#C7C7D0' }}{{ $isOnline ? ';animation:pulse 2s infinite' : '' }}"></span>
                {{ $isOnline ? 'ออนไลน์' : 'ออฟไลน์' }}
            </div>

            {{-- Avatar --}}
            <div style="position:relative;width:72px;height:72px;margin:0 auto 0.875rem">
                <img src="{{ $bot->getAvatarUrl() }}" style="width:72px;height:72px;border-radius:50%;object-fit:cover" alt="">
            </div>

            {{-- Name & badges --}}
            <div style="margin-bottom:0.875rem">
                <div style="display:flex;align-items:center;justify-content:center;gap:0.375rem;margin-bottom:0.375rem;flex-wrap:wrap">
                    <span style="font-weight:700;font-size:0.9375rem;color:#111118">{{ $bot->getDisplayName() }}</span>
                    <span style="font-size:0.6875rem;font-weight:700;padding:0.125rem 0.375rem;border-radius:999px;background:#111118;color:#fff;letter-spacing:0.05em">BOT</span>
                </div>
                <span style="font-size:0.75rem;font-weight:700;padding:0.1875rem 0.625rem;border-radius:999px;color:{{ $lv['color'] }};background:{{ $lv['bg'] }}">
                    {{ $bot->rank }}
                </span>
                @if($bot->bio)
                <p style="font-size:0.75rem;color:#9CA3AF;margin:0.5rem 0 0;overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical">{{ $bot->bio }}</p>
                @endif
            </div>

            {{-- CTA --}}
            @if($isOnline)
            <button @click="challengeOpen = true"
                style="width:100%;padding:0.5rem;background:#111118;color:#fff;border:none;border-radius:0.5rem;font-size:0.8125rem;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:0.375rem;font-family:inherit;transition:opacity 0.15s"
                onmouseenter="this.style.opacity='0.85'" onmouseleave="this.style.opacity='1'">
                <ion-icon name="flash-outline" style="font-size:1rem"></ion-icon>
                ท้าดวล
            </button>
            @else
            <div style="width:100%;padding:0.5rem;background:#F5F5F7;color:#9CA3AF;border-radius:0.5rem;font-size:0.8125rem;font-weight:600;display:flex;align-items:center;justify-content:center;gap:0.375rem">
                <ion-icon name="power-outline" style="font-size:1rem"></ion-icon>
                ไม่พร้อมให้เล่น
            </div>
            @endif

            {{-- Modal --}}
            <div x-show="challengeOpen" x-cloak
                 style="position:fixed;inset:0;z-index:200;display:flex;align-items:center;justify-content:center;padding:1rem"
                 @click.self="challengeOpen = false">
                <div style="position:absolute;inset:0;background:rgba(0,0,0,0.45)"></div>
                <div style="position:relative;background:#fff;border-radius:1rem;width:100%;max-width:400px;padding:1.5rem;box-shadow:0 20px 60px rgba(0,0,0,0.2)">

                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem">
                        <h2 style="font-size:1rem;font-weight:700;color:#111118;margin:0">
                            ⚔ ท้าดวล {{ $bot->getDisplayName() }}
                        </h2>
                        <button @click="challengeOpen = false; sent = false; error = ''"
                            style="background:none;border:none;cursor:pointer;color:#6B6B80;font-size:1.25rem;padding:0;line-height:1">×</button>
                    </div>

                    @if(! $bot->bot_api_token)
                    {{-- Server-side KataGo bot — เริ่มเกมทันที (form POST ปกติ) --}}
                    <form method="POST" action="{{ route('bots.play', $bot) }}">
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

                        {{-- Player color --}}
                        <div style="margin-bottom:0.875rem">
                            <label style="font-size:0.8125rem;font-weight:600;color:#374151;display:block;margin-bottom:0.375rem">สีหมากของคุณ</label>
                            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:0.5rem">
                                @foreach([['black','⚫ ดำ'],['white','⚪ ขาว'],['random','🎲 สุ่ม']] as [$val,$lbl])
                                <label style="cursor:pointer">
                                    <input type="radio" name="player_color" value="{{ $val }}" {{ $val === 'random' ? 'checked' : '' }} style="display:none" class="chal-size-radio">
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

                        {{-- Clock --}}
                        <div x-data="{ clockType: 'byoyomi' }" style="margin-bottom:0.875rem">
                            <label style="font-size:0.8125rem;font-weight:600;color:#374151;display:block;margin-bottom:0.375rem">ระบบนาฬิกา</label>
                            <select name="clock_type" x-model="clockType" class="form-select" style="width:100%;margin-bottom:0.5rem">
                                <option value="byoyomi">เบียวโยมิ</option>
                                <option value="fischer">ฟิชเชอร์</option>
                            </select>
                            <select name="main_time" class="form-select" style="width:100%;margin-bottom:0.5rem">
                                <option value="300">5 นาที</option>
                                <option value="600" selected>10 นาที</option>
                                <option value="1200">20 นาที</option>
                                <option value="1800">30 นาที</option>
                            </select>
                            <template x-if="clockType === 'byoyomi'">
                                <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.5rem">
                                    <select name="byoyomi_periods" class="form-select">
                                        @for($i = 1; $i <= 5; $i++)<option value="{{ $i }}" {{ $i === 3 ? 'selected' : '' }}>{{ $i }} ช่วง</option>@endfor
                                    </select>
                                    <select name="byoyomi_seconds" class="form-select">
                                        <option value="10">10 วิ</option>
                                        <option value="20">20 วิ</option>
                                        <option value="30" selected>30 วิ</option>
                                        <option value="60">60 วิ</option>
                                    </select>
                                </div>
                            </template>
                            <template x-if="clockType === 'fischer'">
                                <select name="fischer_increment" class="form-select" style="width:100%">
                                    <option value="5">+5 วิ/หมาก</option>
                                    <option value="10" selected>+10 วิ/หมาก</option>
                                    <option value="15">+15 วิ/หมาก</option>
                                    <option value="30">+30 วิ/หมาก</option>
                                </select>
                            </template>
                        </div>

                        <button type="submit"
                            style="width:100%;padding:0.625rem;background:#111118;color:#fff;border:none;border-radius:0.625rem;font-size:0.9rem;font-weight:700;cursor:pointer;font-family:inherit">
                            เริ่มเล่นเลย!
                        </button>
                    </form>

                    @else
                    {{-- External bot client — ส่งคำท้าดวลผ่าน AJAX --}}

                    {{-- Sent --}}
                    <div x-show="sent" style="text-align:center;padding:1.5rem 0">
                        <div style="font-size:2rem;margin-bottom:0.75rem">✅</div>
                        <p style="font-weight:700;color:#111118;margin:0 0 0.25rem">ส่งคำท้าดวลแล้ว!</p>
                        <p style="font-size:0.875rem;color:#6B6B80;margin:0">รอ bot client รับและสร้างเกม...</p>
                        <button @click="challengeOpen = false; sent = false"
                            style="margin-top:1rem;padding:0.5rem 1.5rem;background:#111118;color:#fff;border:none;border-radius:0.5rem;font-size:0.875rem;font-weight:600;cursor:pointer;font-family:inherit">
                            ปิด
                        </button>
                    </div>

                    {{-- Error --}}
                    <div x-show="error" x-cloak style="background:#FEF2F2;border:1.5px solid #FECACA;border-radius:0.5rem;padding:0.75rem;margin-bottom:1rem;font-size:0.8125rem;color:#DC2626" x-text="error"></div>

                    {{-- Form --}}
                    <form x-show="!sent"
                          @submit.prevent="
                            axios.post('{{ route('challenges.send', $bot) }}', Object.fromEntries(new FormData($el)))
                                .then(r => { sent = true; error = '' })
                                .catch(e => { error = e.response?.data?.error || 'เกิดข้อผิดพลาด' })
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

                        {{-- Clock --}}
                        <div x-data="{ clockType: 'byoyomi' }" style="margin-bottom:0.875rem">
                            <label style="font-size:0.8125rem;font-weight:600;color:#374151;display:block;margin-bottom:0.375rem">ระบบนาฬิกา</label>
                            <select name="clock_type" x-model="clockType" class="form-select" style="width:100%;margin-bottom:0.5rem">
                                <option value="byoyomi">เบียวโยมิ</option>
                                <option value="fischer">ฟิชเชอร์</option>
                            </select>
                            <select name="main_time" class="form-select" style="width:100%;margin-bottom:0.5rem">
                                <option value="300">5 นาที</option>
                                <option value="600" selected>10 นาที</option>
                                <option value="1200">20 นาที</option>
                                <option value="1800">30 นาที</option>
                            </select>
                            <template x-if="clockType === 'byoyomi'">
                                <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.5rem">
                                    <select name="byoyomi_periods" class="form-select">
                                        @for($i = 1; $i <= 5; $i++)<option value="{{ $i }}" {{ $i === 3 ? 'selected' : '' }}>{{ $i }} ช่วง</option>@endfor
                                    </select>
                                    <select name="byoyomi_seconds" class="form-select">
                                        <option value="10">10 วิ</option>
                                        <option value="20">20 วิ</option>
                                        <option value="30" selected>30 วิ</option>
                                        <option value="60">60 วิ</option>
                                    </select>
                                </div>
                            </template>
                            <template x-if="clockType === 'fischer'">
                                <select name="fischer_increment" class="form-select" style="width:100%">
                                    <option value="5">+5 วิ/หมาก</option>
                                    <option value="10" selected>+10 วิ/หมาก</option>
                                    <option value="15">+15 วิ/หมาก</option>
                                    <option value="30">+30 วิ/หมาก</option>
                                </select>
                            </template>
                        </div>

                        <button type="submit"
                            style="width:100%;padding:0.625rem;background:#111118;color:#fff;border:none;border-radius:0.625rem;font-size:0.9rem;font-weight:700;cursor:pointer;font-family:inherit">
                            ส่งคำท้าดวล
                        </button>
                    </form>
                    @endif
                </div>
            </div>
        </div>
        @endforeach
    </div>
    @endif

    {{-- How to register own bot --}}
    <div style="margin-top:2rem;padding:1.25rem 1.5rem;background:#F5F3FF;border:1.5px solid #DDD6FE;border-radius:1rem;display:flex;gap:1rem;align-items:flex-start">
        <ion-icon name="hardware-chip-outline" style="font-size:1.5rem;color:#4F46E5;flex-shrink:0;margin-top:2px"></ion-icon>
        <div>
            <p style="font-size:0.875rem;font-weight:700;color:#3730A3;margin:0 0 0.25rem">ต้องการเพิ่ม bot ของคุณเอง?</p>
            <p style="font-size:0.8125rem;color:#4338CA;margin:0 0 0.5rem">
                ลงทะเบียนบัญชีคอมพิวเตอร์ รอแอดมินอนุมัติ แล้วดาวน์โหลด bot client เพื่อเชื่อมต่อ GTP engine ของคุณ
            </p>
            <div style="display:flex;gap:0.5rem;flex-wrap:wrap">
                <a href="{{ route('bot.register') }}"
                    style="display:inline-flex;align-items:center;gap:0.25rem;padding:0.375rem 0.875rem;background:#4F46E5;color:#fff;border-radius:0.5rem;font-size:0.8125rem;font-weight:600;text-decoration:none">
                    <ion-icon name="add-outline" style="font-size:0.875rem"></ion-icon>
                    สมัครบัญชีคอมพิวเตอร์
                </a>
                <a href="{{ route('bot.download') }}"
                    style="display:inline-flex;align-items:center;gap:0.25rem;padding:0.375rem 0.875rem;background:#fff;color:#4338CA;border:1.5px solid #C4B5FD;border-radius:0.5rem;font-size:0.8125rem;font-weight:600;text-decoration:none">
                    <ion-icon name="download-outline" style="font-size:0.875rem"></ion-icon>
                    ดาวน์โหลด bot client
                </a>
            </div>
        </div>
    </div>

</div>

@push('head')
<style>
    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.4; }
    }
    .chal-size-radio:checked + .chal-size-btn {
        border-color: #4F46E5;
        background: #EEF2FF;
        color: #4F46E5;
    }
</style>
@endpush
@endsection
