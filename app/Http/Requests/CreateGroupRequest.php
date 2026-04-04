<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateGroupRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80', 'unique:groups,name'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_public' => ['boolean'],
            'max_members' => ['integer', 'min:2', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'กรุณากรอกชื่อกลุ่ม',
            'name.unique' => 'ชื่อกลุ่มนี้ถูกใช้งานแล้ว',
        ];
    }
}
