@extends('layouts.admin')
@section('title', 'จัดการผู้ใช้')
@section('content')
<div class="bg-white rounded-xl border border-gray-200">
    <div class="p-4 border-b border-gray-100 flex gap-3">
        <form method="GET" class="flex gap-2 flex-1">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="ค้นหา ชื่อ / username / อีเมล"
                class="flex-1 border border-gray-200 rounded-lg px-3 py-1.5 text-sm outline-none focus:ring-2 focus:ring-indigo-400">
            <select name="status" class="border border-gray-200 rounded-lg px-2 py-1.5 text-sm outline-none">
                <option value="">ทั้งหมด</option>
                <option value="online" {{ request('status') === 'online' ? 'selected' : '' }}>ออนไลน์</option>
                <option value="banned" {{ request('status') === 'banned' ? 'selected' : '' }}>ถูกระงับ</option>
                <option value="admin" {{ request('status') === 'admin' ? 'selected' : '' }}>แอดมิน</option>
            </select>
            <button class="bg-indigo-600 text-white text-sm px-3 py-1.5 rounded-lg hover:bg-indigo-700 transition">ค้นหา</button>
        </form>
    </div>
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
            <tr>
                <th class="px-4 py-3 text-left">ผู้ใช้</th>
                <th class="px-4 py-3 text-left">อีเมล</th>
                <th class="px-4 py-3 text-left">แรงค์</th>
                <th class="px-4 py-3 text-left">สถานะ</th>
                <th class="px-4 py-3 text-left">สมัครเมื่อ</th>
                <th class="px-4 py-3 text-left">การดำเนินการ</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @foreach($users as $user)
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-3">
                    <div class="flex items-center gap-2">
                        <img src="{{ $user->getAvatarUrl() }}" class="w-7 h-7 rounded-full">
                        <div>
                            <div class="font-medium">{{ $user->getDisplayName() }}</div>
                            <div class="text-xs text-gray-400">{{ $user->username }}</div>
                        </div>
                    </div>
                </td>
                <td class="px-4 py-3 text-gray-500">{{ $user->email }}</td>
                <td class="px-4 py-3">{{ $user->rank }}</td>
                <td class="px-4 py-3">
                    @if($user->is_banned)
                    <span class="text-xs bg-red-100 text-red-700 px-2 py-0.5 rounded">ถูกระงับ</span>
                    @elseif($user->is_admin)
                    <span class="text-xs bg-indigo-100 text-indigo-700 px-2 py-0.5 rounded">แอดมิน</span>
                    @else
                    <span class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded">ปกติ</span>
                    @endif
                </td>
                <td class="px-4 py-3 text-gray-400 text-xs">{{ $user->created_at->format('d/m/Y') }}</td>
                <td class="px-4 py-3">
                    <a href="{{ route('admin.users.show', $user) }}" class="text-indigo-600 hover:underline text-xs">จัดการ</a>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    <div class="p-4">{{ $users->links() }}</div>
</div>
@endsection
