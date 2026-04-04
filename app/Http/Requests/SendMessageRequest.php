<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendMessageRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'body.required' => 'กรุณากรอกข้อความ',
            'body.max' => 'ข้อความยาวเกินไป (สูงสุด 1,000 ตัวอักษร)',
        ];
    }
}
