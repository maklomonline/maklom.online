@extends('layouts.admin')
@section('title', 'บันทึกการดำเนินการ')
@section('content')
<div class="bg-white rounded-xl border border-gray-200">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
            <tr>
                <th class="px-4 py-3 text-left">แอดมิน</th>
                <th class="px-4 py-3 text-left">การดำเนินการ</th>
                <th class="px-4 py-3 text-left">เป้าหมาย</th>
                <th class="px-4 py-3 text-left">หมายเหตุ</th>
                <th class="px-4 py-3 text-left">เวลา</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @foreach($logs as $log)
            <tr>
                <td class="px-4 py-3 text-indigo-600">{{ $log->admin?->username ?? 'ระบบ' }}</td>
                <td class="px-4 py-3 font-mono text-xs bg-gray-50">{{ $log->action }}</td>
                <td class="px-4 py-3 text-gray-500">{{ $log->target_type }}#{{ $log->target_id }}</td>
                <td class="px-4 py-3 text-gray-500 text-xs max-w-xs truncate">{{ $log->notes }}</td>
                <td class="px-4 py-3 text-gray-400 text-xs">{{ $log->created_at->format('d/m/Y H:i') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    <div class="p-4">{{ $logs->links() }}</div>
</div>
@endsection
