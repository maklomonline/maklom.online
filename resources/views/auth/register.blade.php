@extends('layouts.guest')
@section('title', 'ลงทะเบียน')
@section('heading', 'สร้างบัญชีใหม่')
@section('content')
<form method="POST" action="{{ route('register') }}" class="space-y-4">
    @csrf
    <div class="form-group">
        <label class="label">ชื่อแสดง</label>
        <div style="position:relative">
            <ion-icon name="person-outline" style="position:absolute;left:0.75rem;top:50%;transform:translateY(-50%);color:#6B6B80;font-size:1rem;pointer-events:none"></ion-icon>
            <input type="text" name="name" value="{{ old('name') }}" required
                class="input @error('name') border-red-400 @enderror"
                style="padding-left:2.375rem">
        </div>
        @error('name')<p class="mt-1 text-xs" style="color:#DC2626">{{ $message }}</p>@enderror
    </div>
    <div class="form-group">
        <label class="label">ชื่อผู้ใช้ (username)</label>
        <div style="position:relative">
            <ion-icon name="at-outline" style="position:absolute;left:0.75rem;top:50%;transform:translateY(-50%);color:#6B6B80;font-size:1rem;pointer-events:none"></ion-icon>
            <input type="text" name="username" value="{{ old('username') }}" required
                placeholder="เช่น player_thai"
                class="input @error('username') border-red-400 @enderror"
                style="padding-left:2.375rem">
        </div>
        <p style="margin-top:0.25rem;font-size:0.75rem;color:#6B6B80">ใช้ได้เฉพาะตัวอักษร ตัวเลข และ _ (3-30 ตัวอักษร)</p>
        @error('username')<p class="mt-1 text-xs" style="color:#DC2626">{{ $message }}</p>@enderror
    </div>
    <div class="form-group">
        <label class="label">อีเมล</label>
        <div style="position:relative">
            <ion-icon name="mail-outline" style="position:absolute;left:0.75rem;top:50%;transform:translateY(-50%);color:#6B6B80;font-size:1rem;pointer-events:none"></ion-icon>
            <input type="email" name="email" value="{{ old('email') }}" required
                class="input @error('email') border-red-400 @enderror"
                style="padding-left:2.375rem">
        </div>
        @error('email')<p class="mt-1 text-xs" style="color:#DC2626">{{ $message }}</p>@enderror
    </div>
    <div class="form-group">
        <label class="label">รหัสผ่าน</label>
        <div style="position:relative">
            <ion-icon name="lock-closed-outline" style="position:absolute;left:0.75rem;top:50%;transform:translateY(-50%);color:#6B6B80;font-size:1rem;pointer-events:none"></ion-icon>
            <input type="password" name="password" required
                class="input @error('password') border-red-400 @enderror"
                style="padding-left:2.375rem">
        </div>
        @error('password')<p class="mt-1 text-xs" style="color:#DC2626">{{ $message }}</p>@enderror
    </div>
    <div class="form-group">
        <label class="label">ยืนยันรหัสผ่าน</label>
        <div style="position:relative">
            <ion-icon name="shield-checkmark-outline" style="position:absolute;left:0.75rem;top:50%;transform:translateY(-50%);color:#6B6B80;font-size:1rem;pointer-events:none"></ion-icon>
            <input type="password" name="password_confirmation" required
                class="input"
                style="padding-left:2.375rem">
        </div>
    </div>
    <div class="form-group">
        <label class="label">ระดับของคุณ</label>
        <div style="position:relative">
            <ion-icon name="trophy-outline" style="position:absolute;left:0.75rem;top:50%;transform:translateY(-50%);color:#6B6B80;font-size:1rem;pointer-events:none;z-index:1"></ion-icon>
            <select name="initial_rank" required
                class="input @error('initial_rank') border-red-400 @enderror"
                style="padding-left:2.375rem;appearance:none;cursor:pointer">
                <option value="" disabled {{ old('initial_rank') ? '' : 'selected' }}>-- เลือกระดับของคุณ --</option>
                @foreach($registrableRanks as $rank)
                    @php
                        $num = (int)$rank;
                        $label = str_ends_with($rank, 'k') ? "{$num} คิว ({$rank})" : "{$num} ดั้ง ({$rank})";
                    @endphp
                    <option value="{{ $rank }}" {{ old('initial_rank') === $rank ? 'selected' : '' }}>
                        {{ $label }}
                    </option>
                @endforeach
            </select>
            <ion-icon name="chevron-down-outline" style="position:absolute;right:0.75rem;top:50%;transform:translateY(-50%);color:#6B6B80;font-size:1rem;pointer-events:none"></ion-icon>
        </div>
        <p style="margin-top:0.25rem;font-size:0.75rem;color:#6B6B80">ประเมินตามความสามารถจริงของคุณ (เริ่มจาก 30 คิว ถึง 1 ดั้ง)</p>
        @error('initial_rank')<p class="mt-1 text-xs" style="color:#DC2626">{{ $message }}</p>@enderror
    </div>
    <button type="submit" class="btn btn-primary btn-block">
        <ion-icon name="person-add-outline"></ion-icon>
        ลงทะเบียน
    </button>
</form>
<div style="margin-top:1.25rem;text-align:center;font-size:0.875rem;color:#6B6B80">
    มีบัญชีแล้ว?
    <a href="{{ route('login') }}" style="color:#4F46E5;font-weight:600;text-decoration:none">เข้าสู่ระบบ</a>
</div>
@endsection
