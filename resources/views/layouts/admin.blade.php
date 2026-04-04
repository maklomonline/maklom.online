<!DOCTYPE html>
<html lang="th" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'แผงควบคุม') - แอดมิน Maklom</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,400;0,500;0,600;0,700;0,800;1,400&family=Noto+Sans+Thai:wght@400;500;600;700&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">

    {{-- Ionic Icons --}}
    <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-gray-100 font-sans antialiased flex">

{{-- Sidebar --}}
<aside class="w-56 bg-gray-900 text-white flex flex-col min-h-screen shrink-0">
    <div class="p-4 border-b border-gray-700">
        <a href="{{ route('admin.dashboard') }}" style="display:flex;align-items:center;gap:0.625rem;font-size:1rem;font-weight:800;color:#fff;text-decoration:none">
            <span style="width:26px;height:26px;background:#fff;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;flex-shrink:0">
                <span style="width:13px;height:13px;background:#111118;border-radius:50%;display:block"></span>
            </span>
            แอดมิน
        </a>
    </div>
    <nav class="flex-1 p-3 space-y-1 text-sm">
        @php $cur = request()->routeIs('admin.*') ? request()->route()->getName() : ''; @endphp
        <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-2 px-3 py-2 rounded-lg {{ $cur === 'admin.dashboard' ? 'bg-indigo-600' : 'hover:bg-gray-700' }} transition">
            <ion-icon name="grid-outline" style="font-size:1rem;flex-shrink:0"></ion-icon> แดชบอร์ด
        </a>
        <a href="{{ route('admin.users.index') }}" class="flex items-center gap-2 px-3 py-2 rounded-lg {{ str_contains($cur, 'admin.users') ? 'bg-indigo-600' : 'hover:bg-gray-700' }} transition">
            <ion-icon name="people-outline" style="font-size:1rem;flex-shrink:0"></ion-icon> จัดการผู้ใช้
        </a>
        <a href="{{ route('admin.games.index') }}" class="flex items-center gap-2 px-3 py-2 rounded-lg {{ str_contains($cur, 'admin.games') ? 'bg-indigo-600' : 'hover:bg-gray-700' }} transition">
            <ion-icon name="game-controller-outline" style="font-size:1rem;flex-shrink:0"></ion-icon> จัดการเกม
        </a>
        <a href="{{ route('admin.groups.index') }}" class="flex items-center gap-2 px-3 py-2 rounded-lg {{ str_contains($cur, 'admin.groups') ? 'bg-indigo-600' : 'hover:bg-gray-700' }} transition">
            <ion-icon name="albums-outline" style="font-size:1rem;flex-shrink:0"></ion-icon> จัดการกลุ่ม
        </a>
        <a href="{{ route('admin.bot-requests.index') }}" class="flex items-center gap-2 px-3 py-2 rounded-lg {{ str_contains($cur, 'admin.bot-requests') ? 'bg-indigo-600' : 'hover:bg-gray-700' }} transition">
            <ion-icon name="hardware-chip-outline" style="font-size:1rem;flex-shrink:0"></ion-icon> คำขอบัญชี Bot
            @php $pendingBots = \App\Models\BotRequest::where('status','pending')->count(); @endphp
            @if($pendingBots > 0)
            <span style="margin-left:auto;background:#EF4444;color:#fff;font-size:0.625rem;font-weight:700;border-radius:999px;min-width:16px;height:16px;display:flex;align-items:center;justify-content:center;padding:0 3px">{{ $pendingBots }}</span>
            @endif
        </a>
        <a href="{{ route('admin.logs') }}" class="flex items-center gap-2 px-3 py-2 rounded-lg {{ $cur === 'admin.logs' ? 'bg-indigo-600' : 'hover:bg-gray-700' }} transition">
            <ion-icon name="document-text-outline" style="font-size:1rem;flex-shrink:0"></ion-icon> บันทึกการดำเนินการ
        </a>
    </nav>
    <div class="p-3 border-t border-gray-700">
        <a href="{{ route('lobby') }}" style="display:flex;align-items:center;gap:0.375rem;font-size:0.75rem;color:#9CA3AF;text-decoration:none;transition:color 0.15s"
            onmouseover="this.style.color='#fff'" onmouseout="this.style.color='#9CA3AF'">
            <ion-icon name="arrow-back-outline" style="font-size:0.875rem"></ion-icon> กลับหน้าหลัก
        </a>
    </div>
</aside>

<div class="flex-1 flex flex-col overflow-hidden">
    <header style="background:#fff;border-bottom:1.5px solid #E2E2E7;padding:0.75rem 1.5rem;display:flex;align-items:center;justify-content:space-between">
        <h1 style="font-size:0.9375rem;font-weight:700;color:#111118;margin:0">@yield('title', 'แดชบอร์ด')</h1>
        <div style="display:flex;align-items:center;gap:0.375rem;font-size:0.8125rem;color:#6B6B80">
            <ion-icon name="shield-outline" style="font-size:1rem;color:#4F46E5"></ion-icon>
            {{ auth()->user()->getDisplayName() }}
        </div>
    </header>
    <main class="flex-1 overflow-y-auto p-6">
        @if(session('success'))
            <div class="flash-success mb-4">
                <ion-icon name="checkmark-circle-outline" style="font-size:1.125rem;flex-shrink:0"></ion-icon>
                {{ session('success') }}
            </div>
        @endif
        @if($errors->any())
            <div class="flash-error mb-4">
                <ul style="list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:0.25rem">
                    @foreach($errors->all() as $error)
                    <li style="display:flex;align-items:center;gap:0.375rem">
                        <ion-icon name="alert-circle-outline" style="font-size:1rem;flex-shrink:0"></ion-icon>
                        {{ $error }}
                    </li>
                    @endforeach
                </ul>
            </div>
        @endif
        @yield('content')
    </main>
</div>

</body>
</html>
