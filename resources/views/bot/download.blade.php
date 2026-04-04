@extends('layouts.app')
@section('title', 'ดาวน์โหลด Bot Client')

@section('content')
<div style="max-width:760px;margin:0 auto">

    <div style="margin-bottom:1.5rem">
        <h1 style="font-size:1.5rem;font-weight:800;color:#111118;margin:0 0 0.25rem">ดาวน์โหลด Bot Client</h1>
        <p style="font-size:0.875rem;color:#6B6B80;margin:0">
            ซอร์สโค้ดสำหรับเชื่อมต่อ GTP engine ของคุณกับ Maklom bot server
        </p>
    </div>

    {{-- Download card --}}
    <div style="background:#fff;border-radius:1rem;border:1.5px solid #E2E2E7;padding:1.75rem;margin-bottom:1.5rem">
        <div style="display:flex;align-items:flex-start;gap:1rem;margin-bottom:1.25rem">
            <div style="width:48px;height:48px;background:#F0FDF4;border-radius:0.75rem;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                <ion-icon name="logo-python" style="font-size:1.75rem;color:#16A34A"></ion-icon>
            </div>
            <div>
                <h2 style="font-size:1rem;font-weight:700;color:#111118;margin:0 0 0.25rem">maklom_bot_client.py</h2>
                <p style="font-size:0.8125rem;color:#6B6B80;margin:0">Python 3.8+ · ใช้ได้กับทุก GTP engine (KataGo, GNU Go, Leela Zero, ฯลฯ)</p>
            </div>
        </div>
        <a href="{{ asset('downloads/bot_client.py') }}" download="maklom_bot_client.py"
            style="display:inline-flex;align-items:center;gap:0.5rem;padding:0.625rem 1.25rem;background:#111118;color:#fff;border-radius:0.625rem;font-size:0.875rem;font-weight:700;text-decoration:none;transition:opacity 0.15s"
            onmouseenter="this.style.opacity='0.85'" onmouseleave="this.style.opacity='1'">
            <ion-icon name="download-outline" style="font-size:1rem"></ion-icon>
            ดาวน์โหลด bot_client.py
        </a>
    </div>

    {{-- Requirements --}}
    <div style="background:#fff;border-radius:1rem;border:1.5px solid #E2E2E7;padding:1.75rem;margin-bottom:1.5rem">
        <h2 style="font-size:1rem;font-weight:700;color:#111118;margin:0 0 1rem;display:flex;align-items:center;gap:0.5rem">
            <ion-icon name="list-outline" style="font-size:1.1rem;color:#4F46E5"></ion-icon>
            ความต้องการของระบบ
        </h2>
        <ul style="margin:0;padding-left:1.25rem;font-size:0.875rem;color:#374151;line-height:2">
            <li>Python 3.8 ขึ้นไป</li>
            <li>ไลบรารี: <code style="background:#F5F5F7;padding:0.1rem 0.35rem;border-radius:0.25rem;font-size:0.8125rem">requests</code> (ติดตั้งด้วย <code style="background:#F5F5F7;padding:0.1rem 0.35rem;border-radius:0.25rem;font-size:0.8125rem">pip install requests</code>)</li>
            <li>GTP engine ที่รองรับ (เช่น KataGo, GNU Go, Leela Zero)</li>
            <li>บัญชีคอมพิวเตอร์ที่แอดมินอนุมัติแล้ว (<a href="{{ route('bot.register') }}" style="color:#4F46E5">สมัครที่นี่</a>)</li>
        </ul>
    </div>

    {{-- Usage instructions --}}
    <div style="background:#fff;border-radius:1rem;border:1.5px solid #E2E2E7;padding:1.75rem;margin-bottom:1.5rem">
        <h2 style="font-size:1rem;font-weight:700;color:#111118;margin:0 0 1rem;display:flex;align-items:center;gap:0.5rem">
            <ion-icon name="terminal-outline" style="font-size:1.1rem;color:#4F46E5"></ion-icon>
            วิธีใช้งาน
        </h2>

        <div style="margin-bottom:1rem">
            <p style="font-size:0.875rem;font-weight:600;color:#374151;margin:0 0 0.5rem">1. ติดตั้ง dependencies</p>
            <pre style="background:#1E1E2E;color:#CDD6F4;padding:0.875rem 1rem;border-radius:0.5rem;font-size:0.8125rem;overflow-x:auto;margin:0;font-family:'JetBrains Mono',monospace;line-height:1.5">pip install requests</pre>
        </div>

        <div style="margin-bottom:1rem">
            <p style="font-size:0.875rem;font-weight:600;color:#374151;margin:0 0 0.5rem">2. รันด้วย KataGo</p>
            <pre style="background:#1E1E2E;color:#CDD6F4;padding:0.875rem 1rem;border-radius:0.5rem;font-size:0.8125rem;overflow-x:auto;margin:0;font-family:'JetBrains Mono',monospace;line-height:1.5">python bot_client.py \
  --server {{ rtrim(config('app.url'), '/') }} \
  --username YOUR_BOT_USERNAME \
  --password YOUR_BOT_PASSWORD \
  --engine /path/to/katago \
  --engine-args "gtp -config /path/to/default_gtp.cfg -model /path/to/model.bin.gz"</pre>
        </div>

        <div style="margin-bottom:1rem">
            <p style="font-size:0.875rem;font-weight:600;color:#374151;margin:0 0 0.5rem">3. รันด้วย GNU Go (ตัวอย่างอื่น)</p>
            <pre style="background:#1E1E2E;color:#CDD6F4;padding:0.875rem 1rem;border-radius:0.5rem;font-size:0.8125rem;overflow-x:auto;margin:0;font-family:'JetBrains Mono',monospace;line-height:1.5">python bot_client.py \
  --server {{ rtrim(config('app.url'), '/') }} \
  --username YOUR_BOT_USERNAME \
  --password YOUR_BOT_PASSWORD \
  --engine gnugo \
  --engine-args "--mode gtp --level 10"</pre>
        </div>

        <div>
            <p style="font-size:0.875rem;font-weight:600;color:#374151;margin:0 0 0.5rem">4. ตัวเลือกทั้งหมด</p>
            <pre style="background:#1E1E2E;color:#CDD6F4;padding:0.875rem 1rem;border-radius:0.5rem;font-size:0.8125rem;overflow-x:auto;margin:0;font-family:'JetBrains Mono',monospace;line-height:1.5">python bot_client.py --help

  --server        URL ของ Maklom server
  --username      ชื่อผู้ใช้บัญชีคอมพิวเตอร์
  --password      รหัสผ่านบัญชีคอมพิวเตอร์
  --engine        path ของ GTP engine executable
  --engine-args   arguments สำหรับ GTP engine (string)
  --poll-interval ช่วงเวลา polling (วินาที, default: 2)
  --heartbeat     ช่วงเวลา heartbeat (วินาที, default: 30)</pre>
        </div>
    </div>

    {{-- How it works --}}
    <div style="background:#fff;border-radius:1rem;border:1.5px solid #E2E2E7;padding:1.75rem">
        <h2 style="font-size:1rem;font-weight:700;color:#111118;margin:0 0 1rem;display:flex;align-items:center;gap:0.5rem">
            <ion-icon name="hardware-chip-outline" style="font-size:1.1rem;color:#4F46E5"></ion-icon>
            การทำงานของ Bot Client
        </h2>
        <div style="display:flex;flex-direction:column;gap:0.75rem">
            @foreach([
                ['icon' => 'log-in-outline', 'title' => 'เข้าสู่ระบบ', 'desc' => 'bot client ยืนยันตัวตนกับ server และได้รับ API token'],
                ['icon' => 'pulse-outline', 'title' => 'ส่ง Heartbeat', 'desc' => 'ทุก 30 วินาที ส่งสัญญาณให้ server รู้ว่า bot client ยังออนไลน์'],
                ['icon' => 'notifications-outline', 'title' => 'รับคำท้าดวล', 'desc' => 'ตรวจสอบคำท้าดวลที่รอรับ และรับโดยอัตโนมัติ สร้างเกมใหม่'],
                ['icon' => 'game-controller-outline', 'title' => 'เล่นหมาก', 'desc' => 'เมื่อถึงตา ส่ง board state ให้ GTP engine คำนวณหมาก แล้วส่งกลับ server'],
                ['icon' => 'checkmark-circle-outline', 'title' => 'นับคะแนน', 'desc' => 'เมื่อเกมเข้าสู่ช่วงนับคะแนน ยืนยันคะแนนโดยอัตโนมัติ'],
            ] as $step)
            <div style="display:flex;align-items:flex-start;gap:0.75rem">
                <div style="width:32px;height:32px;background:#F0F0FF;border-radius:0.5rem;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                    <ion-icon name="{{ $step['icon'] }}" style="font-size:1rem;color:#4F46E5"></ion-icon>
                </div>
                <div>
                    <p style="font-size:0.875rem;font-weight:600;color:#111118;margin:0 0 0.125rem">{{ $step['title'] }}</p>
                    <p style="font-size:0.8125rem;color:#6B6B80;margin:0">{{ $step['desc'] }}</p>
                </div>
            </div>
            @endforeach
        </div>
    </div>

</div>
@endsection
