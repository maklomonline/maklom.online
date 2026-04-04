@extends('layouts.app')
@section('title', 'สร้างกลุ่ม')
@section('content')
<div class="max-w-lg mx-auto">
    <h1 class="text-xl font-bold text-gray-900 mb-6">สร้างกลุ่มใหม่</h1>
    <form method="POST" action="{{ route('groups.store') }}" class="bg-white rounded-xl border border-gray-200 p-6 space-y-4">
        @csrf
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">ชื่อกลุ่ม</label>
            <input type="text" name="name" value="{{ old('name') }}" required
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-indigo-500 @error('name') border-red-400 @enderror">
            @error('name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">คำอธิบาย</label>
            <textarea name="description" rows="3"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-indigo-500">{{ old('description') }}</textarea>
        </div>
        <div class="flex items-center gap-2">
            <input type="checkbox" name="is_public" id="is_public" value="1" checked class="rounded">
            <label for="is_public" class="text-sm text-gray-700">กลุ่มสาธารณะ</label>
        </div>
        <button type="submit" class="w-full bg-indigo-600 text-white py-2.5 rounded-lg text-sm font-medium hover:bg-indigo-700 transition">
            สร้างกลุ่ม
        </button>
    </form>
</div>
@endsection
