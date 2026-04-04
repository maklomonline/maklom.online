@extends('layouts.app')
@section('title', 'สมัครบัญชีคอมพิวเตอร์')

@section('content')
<div style="max-width:560px;margin:0 auto">

    <div style="margin-bottom:1.5rem">
        <h1 style="font-size:1.5rem;font-weight:800;color:#111118;margin:0 0 0.25rem">สมัครบัญชีคอมพิวเตอร์ (Bot)</h1>
        <p style="font-size:0.875rem;color:#6B6B80;margin:0">
            สร้างบัญชีสำหรับให้ bot client ของคุณเชื่อมต่อและเล่นหมากล้อมกับผู้เล่นคนอื่นได้
        </p>
    </div>

    {{-- Info box --}}
    <div style="background:#EFF6FF;border:1.5px solid #BFDBFE;border-radius:0.75rem;padding:1rem 1.25rem;margin-bottom:1.5rem;display:flex;gap:0.75rem">
        <ion-icon name="information-circle-outline" style="font-size:1.25rem;color:#3B82F6;flex-shrink:0;margin-top:1px"></ion-icon>
        <div style="font-size:0.8125rem;color:#1E40AF;line-height:1.6">
            <strong>ขั้นตอน:</strong>
            <ol style="margin:0.375rem 0 0;padding-left:1.25rem">
                <li>กรอกแบบฟอร์มด้านล่าง → ส่งคำขอ</li>
                <li>รอแอดมินอนุมัติ (จะได้รับการแจ้งเตือน)</li>
                <li>ดาวน์โหลด <a href="{{ route('bot.download') }}" style="color:#1D4ED8;font-weight:600">bot client</a> และตั้งค่า GTP engine</li>
                <li>เชื่อมต่อกับ bot server และเปิดทิ้งไว้</li>
            </ol>
        </div>
    </div>

    <div style="background:#fff;border-radius:1rem;border:1.5px solid #E2E2E7;padding:1.75rem">

        @if(session('success'))
        <div class="flash-success" style="margin-bottom:1.25rem">
            <ion-icon name="checkmark-circle-outline" style="font-size:1.125rem;flex-shrink:0"></ion-icon>
            {{ session('success') }}
        </div>
        @endif

        <form method="POST" action="{{ route('bot.register.store') }}">
            @csrf

            {{-- Username --}}
            <div style="margin-bottom:1rem">
                <label style="font-size:0.8125rem;font-weight:600;color:#374151;display:block;margin-bottom:0.375rem">
                    ชื่อผู้ใช้ (username) <span style="color:#EF4444">*</span>
                </label>
                <input type="text" name="username" value="{{ old('username') }}"
                    placeholder="เช่น my_katago_bot"
                    style="width:100%;padding:0.5625rem 0.875rem;border-radius:0.5rem;border:1.5px solid {{ $errors->has('username') ? '#EF4444' : '#E2E2E7' }};font-size:0.875rem;color:#111118;outline:none;box-sizing:border-box;font-family:inherit"
                    onfocus="this.style.borderColor='#4F46E5'" onblur="this.style.borderColor='{{ $errors->has('username') ? '#EF4444' : '#E2E2E7' }}'">
                @error('username')
                <p style="font-size:0.75rem;color:#EF4444;margin:0.25rem 0 0">{{ $message }}</p>
                @enderror
                <p style="font-size:0.75rem;color:#9CA3AF;margin:0.25rem 0 0">ใช้ได้เฉพาะตัวอักษร A-Z, a-z, 0-9, _ (3–30 ตัวอักษร)</p>
            </div>

            {{-- Display name --}}
            <div style="margin-bottom:1rem">
                <label style="font-size:0.8125rem;font-weight:600;color:#374151;display:block;margin-bottom:0.375rem">
                    ชื่อแสดง <span style="color:#EF4444">*</span>
                </label>
                <input type="text" name="display_name" value="{{ old('display_name') }}"
                    placeholder="เช่น KataGo ของฉัน"
                    style="width:100%;padding:0.5625rem 0.875rem;border-radius:0.5rem;border:1.5px solid {{ $errors->has('display_name') ? '#EF4444' : '#E2E2E7' }};font-size:0.875rem;color:#111118;outline:none;box-sizing:border-box;font-family:inherit"
                    onfocus="this.style.borderColor='#4F46E5'" onblur="this.style.borderColor='{{ $errors->has('display_name') ? '#EF4444' : '#E2E2E7' }}'">
                @error('display_name')
                <p style="font-size:0.75rem;color:#EF4444;margin:0.25rem 0 0">{{ $message }}</p>
                @enderror
            </div>

            {{-- Rank --}}
            <div style="margin-bottom:1rem">
                <label style="font-size:0.8125rem;font-weight:600;color:#374151;display:block;margin-bottom:0.375rem">
                    ระดับฝีมือ <span style="color:#EF4444">*</span>
                </label>
                <select name="rank"
                    style="width:100%;padding:0.5625rem 0.875rem;border-radius:0.5rem;border:1.5px solid {{ $errors->has('rank') ? '#EF4444' : '#E2E2E7' }};font-size:0.875rem;color:#111118;outline:none;box-sizing:border-box;font-family:inherit;background:#fff"
                    onfocus="this.style.borderColor='#4F46E5'" onblur="this.style.borderColor='{{ $errors->has('rank') ? '#EF4444' : '#E2E2E7' }}'">
                    <option value="">— เลือกระดับ —</option>
                    @foreach($botRanks as $rank)
                    <option value="{{ $rank }}" {{ old('rank') === $rank ? 'selected' : '' }}>
                        {{ $rank }}
                    </option>
                    @endforeach
                </select>
                @error('rank')
                <p style="font-size:0.75rem;color:#EF4444;margin:0.25rem 0 0">{{ $message }}</p>
                @enderror
                <p style="font-size:0.75rem;color:#9CA3AF;margin:0.25rem 0 0">บัญชีคอมพิวเตอร์สามารถเลือกระดับได้ถึง 6d</p>
            </div>

            {{-- Bio --}}
            <div style="margin-bottom:1rem">
                <label style="font-size:0.8125rem;font-weight:600;color:#374151;display:block;margin-bottom:0.375rem">
                    คำอธิบาย (ไม่บังคับ)
                </label>
                <textarea name="bio" rows="3" maxlength="300"
                    placeholder="เช่น KataGo b28 เล่นด้วย 1000 visits"
                    style="width:100%;padding:0.5625rem 0.875rem;border-radius:0.5rem;border:1.5px solid #E2E2E7;font-size:0.875rem;color:#111118;outline:none;box-sizing:border-box;font-family:inherit;resize:vertical"
                    onfocus="this.style.borderColor='#4F46E5'" onblur="this.style.borderColor='#E2E2E7'">{{ old('bio') }}</textarea>
                @error('bio')
                <p style="font-size:0.75rem;color:#EF4444;margin:0.25rem 0 0">{{ $message }}</p>
                @enderror
            </div>

            {{-- Password --}}
            <div style="margin-bottom:1rem">
                <label style="font-size:0.8125rem;font-weight:600;color:#374151;display:block;margin-bottom:0.375rem">
                    รหัสผ่านสำหรับ bot client <span style="color:#EF4444">*</span>
                </label>
                <input type="password" name="password"
                    placeholder="อย่างน้อย 8 ตัวอักษร"
                    style="width:100%;padding:0.5625rem 0.875rem;border-radius:0.5rem;border:1.5px solid {{ $errors->has('password') ? '#EF4444' : '#E2E2E7' }};font-size:0.875rem;color:#111118;outline:none;box-sizing:border-box;font-family:inherit"
                    onfocus="this.style.borderColor='#4F46E5'" onblur="this.style.borderColor='{{ $errors->has('password') ? '#EF4444' : '#E2E2E7' }}'">
                @error('password')
                <p style="font-size:0.75rem;color:#EF4444;margin:0.25rem 0 0">{{ $message }}</p>
                @enderror
            </div>

            {{-- Confirm password --}}
            <div style="margin-bottom:1.5rem">
                <label style="font-size:0.8125rem;font-weight:600;color:#374151;display:block;margin-bottom:0.375rem">
                    ยืนยันรหัสผ่าน <span style="color:#EF4444">*</span>
                </label>
                <input type="password" name="password_confirmation"
                    placeholder="ยืนยันรหัสผ่านอีกครั้ง"
                    style="width:100%;padding:0.5625rem 0.875rem;border-radius:0.5rem;border:1.5px solid #E2E2E7;font-size:0.875rem;color:#111118;outline:none;box-sizing:border-box;font-family:inherit"
                    onfocus="this.style.borderColor='#4F46E5'" onblur="this.style.borderColor='#E2E2E7'">
            </div>

            <button type="submit"
                style="width:100%;padding:0.6875rem;background:#111118;color:#fff;border:none;border-radius:0.625rem;font-size:0.9375rem;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:0.5rem;font-family:inherit;transition:opacity 0.15s"
                onmouseenter="this.style.opacity='0.85'" onmouseleave="this.style.opacity='1'">
                <ion-icon name="hardware-chip-outline" style="font-size:1.1rem"></ion-icon>
                ส่งคำขอสร้างบัญชีคอมพิวเตอร์
            </button>
        </form>
    </div>

    <div style="margin-top:1rem;text-align:center">
        <a href="{{ route('bot.download') }}" style="font-size:0.8125rem;color:#4F46E5;text-decoration:none;font-weight:500">
            <ion-icon name="download-outline" style="vertical-align:-2px"></ion-icon>
            ดาวน์โหลด bot client
        </a>
    </div>
</div>
@endsection
