@extends('layouts.admin')
@section('title', 'กลุ่ม: ' . $group->name)
@section('content')
<div class="max-w-4xl mx-auto">
    <h1 class="text-2xl font-bold mb-6">{{ $group->name }}</h1>
    <div class="card p-6 mb-6">
        <dl class="grid grid-cols-2 gap-4">
            <div><dt class="text-sm text-gray-500">เจ้าของ</dt><dd>{{ $group->owner?->getDisplayName() }}</dd></div>
            <div><dt class="text-sm text-gray-500">สมาชิก</dt><dd>{{ $group->getMemberCount() }}</dd></div>
            <div><dt class="text-sm text-gray-500">สาธารณะ</dt><dd>{{ $group->is_public ? 'ใช่' : 'ไม่' }}</dd></div>
        </dl>
        @if($group->description)
        <p class="mt-4 text-gray-600">{{ $group->description }}</p>
        @endif
    </div>
    <form method="POST" action="{{ route('admin.groups.destroy', $group) }}">
        @csrf @method('DELETE')
        <button type="submit" class="btn-danger" onclick="return confirm('ลบกลุ่มนี้?')">ลบกลุ่ม</button>
    </form>
    <a href="{{ route('admin.groups.index') }}" class="btn-secondary mt-4 inline-block">กลับ</a>
</div>
@endsection
