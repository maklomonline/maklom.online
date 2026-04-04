@extends('layouts.admin')
@section('title', 'แดชบอร์ด')
@section('content')
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    @foreach([
        ['label' => 'ผู้ใช้ทั้งหมด', 'value' => $stats['total_users'], 'icon' => '👥', 'color' => 'bg-blue-50 text-blue-700'],
        ['label' => 'ออนไลน์ตอนนี้', 'value' => $stats['users_online'], 'icon' => '🟢', 'color' => 'bg-green-50 text-green-700'],
        ['label' => 'เกมที่เล่นอยู่', 'value' => $stats['active_games'], 'icon' => '🎮', 'color' => 'bg-indigo-50 text-indigo-700'],
        ['label' => 'เกมวันนี้', 'value' => $stats['games_today'], 'icon' => '📈', 'color' => 'bg-purple-50 text-purple-700'],
        ['label' => 'ผู้ใช้ใหม่วันนี้', 'value' => $stats['new_users_today'], 'icon' => '✨', 'color' => 'bg-yellow-50 text-yellow-700'],
        ['label' => 'ข้อความวันนี้', 'value' => $stats['messages_today'], 'icon' => '💬', 'color' => 'bg-orange-50 text-orange-700'],
        ['label' => 'ถูกระงับ', 'value' => $stats['banned_users'], 'icon' => '🚫', 'color' => 'bg-red-50 text-red-700'],
        ['label' => 'กลุ่มทั้งหมด', 'value' => $stats['total_groups'], 'icon' => '🏘️', 'color' => 'bg-teal-50 text-teal-700'],
    ] as $stat)
    <div class="bg-white rounded-xl border border-gray-200 p-4">
        <div class="flex items-center gap-2 mb-2">
            <span class="text-xl">{{ $stat['icon'] }}</span>
            <span class="text-xs text-gray-500">{{ $stat['label'] }}</span>
        </div>
        <div class="text-2xl font-bold text-gray-900">{{ number_format($stat['value']) }}</div>
    </div>
    @endforeach
</div>
@endsection
