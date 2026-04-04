<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateRoomRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'board_size' => ['required', 'integer', 'in:9,13,19'],
            'clock_type' => ['required', 'in:byoyomi,fischer'],
            'main_time' => ['required', 'integer', 'min:30', 'max:7200'],
            'byoyomi_periods' => ['required_if:clock_type,byoyomi', 'nullable', 'integer', 'min:1', 'max:10'],
            'byoyomi_seconds' => ['required_if:clock_type,byoyomi', 'nullable', 'integer', 'min:5', 'max:300'],
            'fischer_increment' => ['required_if:clock_type,fischer', 'nullable', 'integer', 'min:0', 'max:120'],
            'komi' => ['required', 'numeric', 'min:0', 'max:15'],
            'handicap' => ['required', 'integer', 'min:0', 'max:9'],
            'is_private' => ['boolean'],
            'password' => ['nullable', 'string', 'min:4', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'กรุณากรอกชื่อห้อง',
            'board_size.in' => 'ขนาดกระดานต้องเป็น 9, 13, หรือ 19',
            'clock_type.in' => 'ประเภทนาฬิกาต้องเป็น byoyomi หรือ fischer',
            'main_time.required' => 'กรุณากรอกเวลาหลัก',
        ];
    }
}
