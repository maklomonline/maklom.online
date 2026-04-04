@extends('layouts.app')
@section('title', 'สร้างห้อง')
@section('content')
<div class="max-w-lg mx-auto">
    <h1 class="text-xl font-bold text-gray-900 mb-6">สร้างห้องหมากล้อม</h1>

    <form method="POST" action="{{ route('rooms.store') }}" class="bg-white rounded-xl border border-gray-200 p-6 space-y-5"
        x-data="{ clockType: 'byoyomi' }">
        @csrf

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">ชื่อห้อง</label>
            <input type="text" name="name" value="{{ old('name') }}" required placeholder="เช่น ท้าสู้ คนไหนก็ได้"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-indigo-500 @error('name') border-red-400 @enderror">
            @error('name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">ขนาดกระดาน</label>
            <div class="flex gap-3">
                @foreach([9, 13, 19] as $size)
                <label class="flex-1">
                    <input type="radio" name="board_size" value="{{ $size }}" {{ old('board_size', 19) == $size ? 'checked' : '' }} class="sr-only peer">
                    <div class="peer-checked:bg-indigo-600 peer-checked:text-white border border-gray-200 peer-checked:border-indigo-600 rounded-lg p-3 text-center cursor-pointer text-sm hover:border-indigo-400 transition">
                        {{ $size }}×{{ $size }}
                    </div>
                </label>
                @endforeach
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">โคมิ</label>
                <input type="number" name="komi" value="{{ old('komi', 6.5) }}" step="0.5" min="0" max="15"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">หมากต่อ</label>
                <select name="handicap" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                    @for($i = 0; $i <= 9; $i++)
                    <option value="{{ $i }}" {{ old('handicap', 0) == $i ? 'selected' : '' }}>{{ $i }}</option>
                    @endfor
                </select>
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">ประเภทนาฬิกา</label>
            <div class="flex gap-3">
                <label class="flex-1">
                    <input type="radio" name="clock_type" value="byoyomi" x-model="clockType" {{ old('clock_type', 'byoyomi') === 'byoyomi' ? 'checked' : '' }} class="sr-only peer">
                    <div class="peer-checked:bg-indigo-600 peer-checked:text-white border border-gray-200 peer-checked:border-indigo-600 rounded-lg p-3 text-center cursor-pointer text-sm hover:border-indigo-400 transition">
                        เบียวโยมิ
                    </div>
                </label>
                <label class="flex-1">
                    <input type="radio" name="clock_type" value="fischer" x-model="clockType" {{ old('clock_type') === 'fischer' ? 'checked' : '' }} class="sr-only peer">
                    <div class="peer-checked:bg-indigo-600 peer-checked:text-white border border-gray-200 peer-checked:border-indigo-600 rounded-lg p-3 text-center cursor-pointer text-sm hover:border-indigo-400 transition">
                        ฟิชเชอร์
                    </div>
                </label>
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">เวลาหลัก</label>
            <select name="main_time" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                @php
                    $presets = [
                        30   => '30 วินาที',
                        60   => '1 นาที',
                        120  => '2 นาที',
                        180  => '3 นาที',
                        300  => '5 นาที',
                        600  => '10 นาที',
                        900  => '15 นาที',
                        1200 => '20 นาที',
                        1800 => '30 นาที',
                        2700 => '45 นาที',
                        3600 => '1 ชั่วโมง',
                        5400 => '1 ชั่วโมง 30 นาที',
                        7200 => '2 ชั่วโมง',
                    ];
                @endphp
                @foreach($presets as $seconds => $label)
                <option value="{{ $seconds }}" {{ old('main_time', 600) == $seconds ? 'selected' : '' }}>
                    {{ $label }}
                </option>
                @endforeach
            </select>
        </div>

        <div x-show="clockType === 'byoyomi'" class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">จำนวนรอบเบียวโยมิ</label>
                <input type="number" name="byoyomi_periods" value="{{ old('byoyomi_periods', 5) }}" min="1" max="10"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">วินาทีต่อรอบ</label>
                <input type="number" name="byoyomi_seconds" value="{{ old('byoyomi_seconds', 30) }}" min="5" max="300"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
        </div>

        <div x-show="clockType === 'fischer'">
            <label class="block text-sm font-medium text-gray-700 mb-1">เพิ่มเวลาต่อตา (วินาที)</label>
            <input type="number" name="fischer_increment" value="{{ old('fischer_increment', 10) }}" min="0" max="120"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
        </div>

        <div class="flex items-center gap-2">
            <input type="checkbox" name="is_private" id="is_private" value="1" {{ old('is_private') ? 'checked' : '' }} class="rounded">
            <label for="is_private" class="text-sm text-gray-700">ห้องส่วนตัว (ต้องใช้รหัสผ่าน)</label>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">รหัสผ่านห้อง (ถ้ามี)</label>
            <input type="password" name="password" placeholder="ว่างเปล่า = ไม่มีรหัสผ่าน"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
        </div>

        <button type="submit" class="w-full bg-indigo-600 text-white py-2.5 rounded-lg text-sm font-medium hover:bg-indigo-700 transition">
            สร้างห้อง
        </button>
    </form>
</div>
@endsection
