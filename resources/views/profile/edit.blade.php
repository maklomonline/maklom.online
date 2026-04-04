@extends('layouts.app')
@section('title', 'ตั้งค่าบัญชี')
@section('content')
<div class="max-w-lg mx-auto space-y-6">
    <h1 class="text-xl font-bold text-gray-900">ตั้งค่าบัญชี</h1>

    {{-- Profile Info --}}
    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <h2 class="font-semibold mb-4">ข้อมูลโปรไฟล์</h2>
        <form method="POST" action="{{ route('profile.update') }}" class="space-y-4">
            @csrf @method('PUT')
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">ชื่อแสดง</label>
                <input type="text" name="display_name" value="{{ old('display_name', $user->display_name) }}"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">คำอธิบายตัวเอง</label>
                <textarea name="bio" rows="3"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-indigo-500">{{ old('bio', $user->bio) }}</textarea>
            </div>
            <button type="submit" class="bg-indigo-600 text-white text-sm px-4 py-2 rounded-lg hover:bg-indigo-700 transition">บันทึก</button>
        </form>
    </div>

    {{-- Avatar --}}
    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <h2 class="font-semibold mb-4">รูปโปรไฟล์</h2>
        <div class="flex items-center gap-4 mb-4">
            <img src="{{ $user->getAvatarUrl() }}" class="w-16 h-16 rounded-full object-cover">
            <p class="text-sm text-gray-500">อัปโหลดรูปใหม่ (สูงสุด 2MB)</p>
        </div>
        <form method="POST" action="{{ route('profile.avatar') }}" enctype="multipart/form-data">
            @csrf
            <input type="file" name="avatar" accept="image/*" class="block text-sm text-gray-500 mb-3">
            @error('avatar')<p class="text-xs text-red-600 mb-2">{{ $message }}</p>@enderror
            <button type="submit" class="bg-indigo-600 text-white text-sm px-4 py-2 rounded-lg hover:bg-indigo-700 transition">อัปโหลด</button>
        </form>
    </div>

    {{-- Game Preferences --}}
    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <h2 class="font-semibold mb-4">การตั้งค่าเกม</h2>
        <form method="POST" action="{{ route('profile.update') }}">
            @csrf @method('PUT')
            <label class="flex items-center justify-between gap-4 cursor-pointer select-none">
                <div>
                    <p class="text-sm font-medium text-gray-800">ยืนยันก่อนเดินหมาก</p>
                    <p class="text-xs text-gray-500 mt-0.5">แตะตำแหน่งเพื่อเลือก แล้วกดยืนยันอีกครั้งก่อนส่งหมาก</p>
                </div>
                <button type="submit" name="confirm_move" value="{{ $user->confirm_move ? '0' : '1' }}"
                    class="relative inline-flex h-6 w-11 shrink-0 rounded-full border-2 border-transparent transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2
                        {{ $user->confirm_move ? 'bg-indigo-600' : 'bg-gray-200' }}">
                    <span class="pointer-events-none inline-block h-5 w-5 rounded-full bg-white shadow ring-0 transition-transform duration-200
                        {{ $user->confirm_move ? 'translate-x-5' : 'translate-x-0' }}"></span>
                </button>
            </label>
        </form>
    </div>

    {{-- Password --}}
    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <h2 class="font-semibold mb-4">เปลี่ยนรหัสผ่าน</h2>
        <form method="POST" action="{{ route('profile.password') }}" class="space-y-4">
            @csrf @method('PUT')
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">รหัสผ่านปัจจุบัน</label>
                <input type="password" name="current_password"
                    class="w-full border rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-indigo-500 {{ $errors->has('current_password') ? 'border-red-400' : 'border-gray-300' }}">
                @error('current_password')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">รหัสผ่านใหม่</label>
                <input type="password" name="password"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">ยืนยันรหัสผ่านใหม่</label>
                <input type="password" name="password_confirmation"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <button type="submit" class="bg-indigo-600 text-white text-sm px-4 py-2 rounded-lg hover:bg-indigo-700 transition">เปลี่ยนรหัสผ่าน</button>
        </form>
    </div>

    {{-- Delete Account --}}
    <div class="bg-white rounded-xl border border-red-200 p-6" x-data="{ open: false }">
        <h2 class="font-semibold text-red-600 mb-1">ลบบัญชี</h2>
        <p class="text-sm text-gray-500 mb-4">เมื่อลบบัญชีแล้วจะไม่สามารถกู้คืนได้ ข้อมูลทั้งหมดจะถูกลบอย่างถาวร</p>
        <button type="button" @click="open = true"
            class="bg-red-600 text-white text-sm px-4 py-2 rounded-lg hover:bg-red-700 transition">
            ลบบัญชีนี้
        </button>

        {{-- Confirmation Modal --}}
        <div x-show="open" x-cloak
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
            @keydown.escape.window="open = false">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-md mx-4 p-6" @click.stop>
                <h3 class="text-lg font-bold text-gray-900 mb-2">ยืนยันการลบบัญชี</h3>
                <p class="text-sm text-gray-600 mb-4">
                    กรุณากรอกรหัสผ่านของคุณเพื่อยืนยันการลบบัญชีอย่างถาวร
                </p>
                <form method="POST" action="{{ route('profile.destroy') }}">
                    @csrf @method('DELETE')
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">รหัสผ่าน</label>
                        <input type="password" name="password" autofocus
                            class="w-full border rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-red-500 @error('delete_password') border-red-400 @enderror">
                        @error('delete_password')
                            <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="flex gap-3 justify-end">
                        <button type="button" @click="open = false"
                            class="text-sm px-4 py-2 rounded-lg border border-gray-300 hover:bg-gray-50 transition">
                            ยกเลิก
                        </button>
                        <button type="submit"
                            class="bg-red-600 text-white text-sm px-4 py-2 rounded-lg hover:bg-red-700 transition">
                            ยืนยันลบบัญชี
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
