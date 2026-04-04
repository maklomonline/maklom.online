<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MakeMoveRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'coordinate' => ['required', 'string', 'regex:/^[A-HJ-T](1[0-9]|[1-9])$/i'],
        ];
    }

    public function messages(): array
    {
        return [
            'coordinate.required' => 'กรุณาระบุตำแหน่ง',
            'coordinate.regex' => 'รูปแบบตำแหน่งไม่ถูกต้อง (เช่น D4, Q16)',
        ];
    }
}
