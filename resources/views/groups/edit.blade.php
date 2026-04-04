@extends('layouts.app')
@section('title', 'แก้ไขกลุ่ม')
@section('content')
<div class="max-w-lg mx-auto">
    <h1 class="text-2xl font-bold mb-6">แก้ไขกลุ่ม: {{ $group->name }}</h1>
    <form method="POST" action="{{ route('groups.update', $group) }}" class="card p-6 space-y-4">
        @csrf @method('PUT')
        <div>
            <label class="label">คำอธิบาย</label>
            <textarea name="description" class="input" rows="3">{{ old('description', $group->description) }}</textarea>
        </div>
        <div class="flex items-center gap-2">
            <input type="checkbox" name="is_public" id="is_public" value="1" {{ $group->is_public ? 'checked' : '' }}>
            <label for="is_public">กลุ่มสาธารณะ</label>
        </div>
        <div>
            <label class="label">จำนวนสมาชิกสูงสุด</label>
            <input type="number" name="max_members" class="input" value="{{ old('max_members', $group->max_members) }}" min="2" max="500">
        </div>
        <button type="submit" class="btn-primary w-full">บันทึก</button>
    </form>
    <a href="{{ route('groups.show', $group) }}" class="block mt-4 text-center text-gray-500 hover:underline">กลับ</a>
</div>
@endsection
