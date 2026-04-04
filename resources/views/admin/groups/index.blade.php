@extends('layouts.admin')
@section('title', 'จัดการกลุ่ม')
@section('content')
<div class="bg-white rounded-xl border border-gray-200">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
            <tr>
                <th class="px-4 py-3 text-left">กลุ่ม</th>
                <th class="px-4 py-3 text-left">เจ้าของ</th>
                <th class="px-4 py-3 text-left">สมาชิก</th>
                <th class="px-4 py-3 text-left">สถานะ</th>
                <th class="px-4 py-3 text-left">สร้างเมื่อ</th>
                <th class="px-4 py-3 text-left">การดำเนินการ</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @foreach($groups as $group)
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-3 font-medium">{{ $group->name }}</td>
                <td class="px-4 py-3 text-gray-500">{{ $group->owner?->username }}</td>
                <td class="px-4 py-3">{{ $group->members_count }}</td>
                <td class="px-4 py-3">
                    <span class="text-xs {{ $group->is_public ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' }} px-1.5 py-0.5 rounded">
                        {{ $group->is_public ? 'สาธารณะ' : 'ส่วนตัว' }}
                    </span>
                </td>
                <td class="px-4 py-3 text-gray-400 text-xs">{{ $group->created_at->format('d/m/Y') }}</td>
                <td class="px-4 py-3">
                    <form method="POST" action="{{ route('admin.groups.destroy', $group) }}">
                        @csrf @method('DELETE')
                        <button onclick="return confirm('ลบกลุ่ม {{ $group->name }}?')" class="text-xs text-red-600 hover:underline">ลบ</button>
                    </form>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    <div class="p-4">{{ $groups->links() }}</div>
</div>
@endsection
