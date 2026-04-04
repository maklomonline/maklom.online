@extends('layouts.guest')
@section('title', 'ลืมรหัสผ่าน')
@section('heading', 'รีเซ็ตรหัสผ่าน')
@section('content')
<p class="text-sm text-gray-600 mb-4 text-center">
    กรอกอีเมลของคุณ เราจะส่งลิงก์สำหรับรีเซ็ตรหัสผ่านไปให้
</p>
<form method="POST" action="{{ route('password.email') }}" class="space-y-4">
    @csrf
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">อีเมล</label>
        <input type="email" name="email" value="{{ old('email') }}" required autofocus
            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 outline-none @error('email') border-red-400 @enderror">
        @error('email')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>
    <button type="submit" class="w-full bg-indigo-600 text-white py-2.5 rounded-lg text-sm font-medium hover:bg-indigo-700 transition">
        ส่งลิงก์รีเซ็ต
    </button>
</form>
<p class="mt-4 text-center text-sm">
    <a href="{{ route('login') }}" class="text-indigo-600 hover:underline">← กลับเข้าสู่ระบบ</a>
</p>
@endsection
