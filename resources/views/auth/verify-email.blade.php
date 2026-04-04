@extends('layouts.guest')
@section('title', 'ยืนยันอีเมล')
@section('heading', 'ยืนยันอีเมล')
@section('content')
<div class="text-center space-y-4">
    <div class="text-5xl">📧</div>
    <p class="text-sm text-gray-600">
        เราส่งลิงก์ยืนยันไปยังอีเมลของคุณแล้ว<br>
        กรุณาตรวจสอบกล่องจดหมาย
    </p>
    <form method="POST" action="{{ route('verification.send') }}">
        @csrf
        <button type="submit" class="w-full bg-indigo-600 text-white py-2.5 rounded-lg text-sm font-medium hover:bg-indigo-700 transition">
            ส่งลิงก์ยืนยันอีกครั้ง
        </button>
    </form>
    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit" class="text-sm text-gray-500 hover:text-gray-700">ออกจากระบบ</button>
    </form>
</div>
@endsection
