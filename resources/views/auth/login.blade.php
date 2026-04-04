@extends('layouts.guest')
@section('title', 'เข้าสู่ระบบ')
@section('heading', 'เข้าสู่ระบบ')
@section('content')
<form method="POST" action="{{ route('login') }}" class="space-y-4">
    @csrf
    <div class="form-group">
        <label class="label">อีเมล</label>
        <div style="position:relative">
            <ion-icon name="mail-outline" style="position:absolute;left:0.75rem;top:50%;transform:translateY(-50%);color:#6B6B80;font-size:1rem;pointer-events:none"></ion-icon>
            <input type="email" name="email" value="{{ old('email') }}" required autofocus
                class="input @error('email') border-red-400 @enderror"
                style="padding-left:2.375rem">
        </div>
        @error('email')<p class="mt-1 text-xs" style="color:#DC2626">{{ $message }}</p>@enderror
    </div>
    <div class="form-group">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.375rem">
            <label class="label" style="margin-bottom:0">รหัสผ่าน</label>
            <a href="{{ route('password.request') }}" style="font-size:0.75rem;color:#4F46E5;font-weight:600;text-decoration:none">ลืมรหัสผ่าน?</a>
        </div>
        <div style="position:relative">
            <ion-icon name="lock-closed-outline" style="position:absolute;left:0.75rem;top:50%;transform:translateY(-50%);color:#6B6B80;font-size:1rem;pointer-events:none"></ion-icon>
            <input type="password" name="password" required
                class="input"
                style="padding-left:2.375rem">
        </div>
    </div>
    <div style="display:flex;align-items:center;gap:0.5rem">
        <input type="checkbox" name="remember" id="remember"
            style="width:1rem;height:1rem;accent-color:#4F46E5;cursor:pointer;border-radius:0.25rem">
        <label for="remember" style="font-size:0.875rem;color:#6B6B80;cursor:pointer">จดจำฉัน</label>
    </div>
    <button type="submit" class="btn btn-primary btn-block">
        <ion-icon name="log-in-outline"></ion-icon>
        เข้าสู่ระบบ
    </button>
</form>
<div style="margin-top:1.25rem;text-align:center;font-size:0.875rem;color:#6B6B80">
    ยังไม่มีบัญชี?
    <a href="{{ route('register') }}" style="color:#4F46E5;font-weight:600;text-decoration:none">ลงทะเบียน</a>
</div>
@endsection
