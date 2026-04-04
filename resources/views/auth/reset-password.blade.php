@extends('layouts.guest')
@section('title', 'รีเซ็ตรหัสผ่าน')
@section('heading', 'ตั้งรหัสผ่านใหม่')
@section('content')
<form method="POST" action="{{ route('password.update') }}" class="space-y-4">
    @csrf
    <input type="hidden" name="token" value="{{ $token }}">
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">อีเมล</label>
        <input type="email" name="email" value="{{ $email ?? old('email') }}" required
            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none @error('email') border-red-400 @enderror">
        @error('email')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">รหัสผ่านใหม่</label>
        <input type="password" name="password" required
            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none @error('password') border-red-400 @enderror">
        @error('password')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">ยืนยันรหัสผ่าน</label>
        <input type="password" name="password_confirmation" required
            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none">
    </div>
    <button type="submit" class="w-full bg-indigo-600 text-white py-2.5 rounded-lg text-sm font-medium hover:bg-indigo-700 transition">
        ตั้งรหัสผ่านใหม่
    </button>
</form>
@endsection
