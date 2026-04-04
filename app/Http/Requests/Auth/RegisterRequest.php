<?php

namespace App\Http\Requests\Auth;

use App\Services\RatingService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:60'],
            'username' => ['required', 'string', 'min:3', 'max:30', 'unique:users,username', 'regex:/^[a-zA-Z0-9_]+$/'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'initial_rank' => ['required', Rule::in(RatingService::registrableRanks())],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'กรุณากรอกชื่อ',
            'username.required' => 'กรุณากรอกชื่อผู้ใช้',
            'username.unique' => 'ชื่อผู้ใช้นี้ถูกใช้งานแล้ว',
            'username.regex' => 'ชื่อผู้ใช้ใช้ได้เฉพาะตัวอักษร ตัวเลข และ _ เท่านั้น',
            'email.required' => 'กรุณากรอกอีเมล',
            'email.unique' => 'อีเมลนี้ถูกใช้งานแล้ว',
            'password.required' => 'กรุณากรอกรหัสผ่าน',
            'password.min' => 'รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร',
            'password.confirmed' => 'รหัสผ่านไม่ตรงกัน',
            'initial_rank.required' => 'กรุณาเลือกระดับของคุณ',
            'initial_rank.in' => 'ระดับที่เลือกไม่ถูกต้อง',
        ];
    }
}
