<!DOCTYPE html>
<html lang="th" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'เข้าสู่ระบบ') - Maklom</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,400;0,500;0,600;0,700;0,800;1,400&family=Noto+Sans+Thai:wght@400;500;600;700&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">

    {{-- Ionic Icons --}}
    <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-bg font-sans antialiased">
<div class="min-h-full flex flex-col justify-center py-12 sm:px-6 lg:px-8">
    <div class="sm:mx-auto sm:w-full sm:max-w-md">
        <a href="/" style="display:flex;justify-content:center;align-items:center;gap:0.625rem;font-size:1.25rem;font-weight:800;color:#111118;text-decoration:none;margin-bottom:1.5rem">
            <span style="width:32px;height:32px;background:#111118;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;flex-shrink:0">
                <span style="width:16px;height:16px;background:#fff;border-radius:50%;display:block"></span>
            </span>
            Maklom
        </a>
        <h2 style="text-align:center;font-size:1.375rem;font-weight:700;color:#111118">@yield('heading')</h2>
        @if(session('success'))
        <div style="margin-top:0.75rem;padding:0.75rem 1rem;background:#F0FDF4;border:1.5px solid #BBF7D0;color:#15803D;border-radius:0.75rem;font-size:0.875rem;text-align:center;display:flex;align-items:center;justify-content:center;gap:0.375rem">
            <ion-icon name="checkmark-circle-outline" style="font-size:1.125rem;flex-shrink:0"></ion-icon>
            {{ session('success') }}
        </div>
        @endif
    </div>
    <div class="mt-6 sm:mx-auto sm:w-full sm:max-w-md">
        <div style="background:#fff;padding:2rem 1.75rem;border-radius:1rem;border:1.5px solid #E2E2E7;box-shadow:0 4px 20px rgba(0,0,0,0.06)">
            @yield('content')
        </div>
    </div>
</div>
</body>
</html>
