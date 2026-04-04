<!DOCTYPE html>
<html lang="th" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Maklom') — Maklom</title>

    {{-- Fonts --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,400;0,500;0,600;0,700;0,800;1,400&family=Noto+Sans+Thai:wght@400;500;600;700&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">

    {{-- Ionic Icons --}}
    <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="h-full" x-data>

{{-- ── Navigation ────────────────────────────────── --}}
<nav x-data="{ mobileOpen: false }" style="background:#fff;border-bottom:1.5px solid #E2E2E7;position:sticky;top:0;z-index:50">
    <div style="max-width:1280px;margin:0 auto;padding:0 1rem">
        <div style="display:flex;justify-content:space-between;align-items:center;height:56px">

            {{-- Left: Logo + Nav links --}}
            <div style="display:flex;align-items:center;gap:0.25rem">
                <a href="{{ route('lobby') }}" style="display:flex;align-items:center;gap:0.5rem;font-size:1.125rem;font-weight:800;color:#111118;text-decoration:none;padding:0.375rem 0.625rem;border-radius:0.625rem;margin-right:0.5rem">
                    <span style="width:28px;height:28px;background:#111118;border-radius:50%;display:inline-flex;align-items:center;justify-content:center">
                        <span style="width:14px;height:14px;background:#fff;border-radius:50%;display:block"></span>
                    </span>
                    Maklom
                </a>
                <div class="hidden lg:flex" style="align-items:center;gap:0.125rem">
                    <a href="{{ route('lobby') }}" class="nav-link">
                        <ion-icon name="grid-outline"></ion-icon> ล็อบบี้
                    </a>
                    @auth
                    <a href="{{ route('bots.index') }}" class="nav-link" style="color:#4F46E5;font-weight:700">
                        <ion-icon name="hardware-chip-outline"></ion-icon> เล่นกับคอม
                    </a>
                    @endauth
                    <a href="{{ route('chat.global') }}" class="nav-link">
                        <ion-icon name="chatbubbles-outline"></ion-icon> แชท
                    </a>
                    <a href="{{ route('groups.index') }}" class="nav-link">
                        <ion-icon name="people-outline"></ion-icon> กลุ่ม
                    </a>
                    @auth
                    <a href="{{ route('friends.index') }}" class="nav-link">
                        <ion-icon name="person-add-outline"></ion-icon> เพื่อน
                    </a>
                    @endauth
                </div>
            </div>

            {{-- Center: Search --}}
            @auth
            <div x-data="userSearch()" style="position:relative;width:220px" class="hidden lg:block">
                <div style="position:relative">
                    <ion-icon name="search-outline" style="position:absolute;left:0.625rem;top:50%;transform:translateY(-50%);color:#9CA3AF;font-size:0.9375rem;pointer-events:none"></ion-icon>
                    <input type="text" x-model="q" @input.debounce.300ms="search()" @keydown.escape="close()"
                        @keydown.enter.prevent="goToSearch()"
                        placeholder="ค้นหาผู้ใช้..."
                        style="width:100%;padding:0.4375rem 0.625rem 0.4375rem 2rem;border:1.5px solid #E2E2E7;border-radius:0.625rem;font-size:0.8125rem;font-family:inherit;color:#111118;outline:none;box-sizing:border-box;background:#F9F9FB;transition:border-color 0.15s,background 0.15s"
                        onfocus="this.style.borderColor='#4F46E5';this.style.background='#fff'" onblur="this.style.borderColor='#E2E2E7';this.style.background='#F9F9FB'">
                </div>
                <div x-show="open && (results.length > 0 || (q.length >= 2 && !loading))" x-cloak @click.outside="close()"
                    style="position:absolute;top:calc(100% + 6px);left:0;right:0;background:#fff;border:1.5px solid #E2E2E7;border-radius:0.875rem;z-index:200;overflow:hidden;box-shadow:0 8px 30px rgba(0,0,0,0.12)">
                    <template x-if="results.length > 0">
                        <div>
                            <template x-for="u in results" :key="u.username">
                                <a :href="u.profile_url"
                                    style="display:flex;align-items:center;gap:0.625rem;padding:0.625rem 0.75rem;text-decoration:none;color:inherit;transition:background 0.1s"
                                    @mouseenter="$el.style.background='#F5F5F7'" @mouseleave="$el.style.background=''">
                                    <img :src="u.avatar_url" style="width:32px;height:32px;border-radius:50%;object-fit:cover;flex-shrink:0">
                                    <div style="flex:1;min-width:0">
                                        <div style="font-size:0.8125rem;font-weight:600;color:#111118;white-space:nowrap;overflow:hidden;text-overflow:ellipsis" x-text="u.display_name"></div>
                                        <div style="font-size:0.6875rem;color:#6B6B80" x-text="'@' + u.username + ' · ' + u.rank"></div>
                                    </div>
                                </a>
                            </template>
                            <a :href="'{{ route('users.search') }}?q=' + encodeURIComponent(q)"
                                style="display:block;padding:0.5rem 0.75rem;text-align:center;font-size:0.75rem;font-weight:600;color:#4F46E5;border-top:1px solid #F0F0F5;text-decoration:none;background:#FAFAFA">
                                ดูผลลัพธ์ทั้งหมด →
                            </a>
                        </div>
                    </template>
                    <template x-if="results.length === 0 && q.length >= 2 && !loading">
                        <div style="padding:1rem;text-align:center;font-size:0.8125rem;color:#9CA3AF">ไม่พบผู้ใช้</div>
                    </template>
                </div>
            </div>
            @endauth

            {{-- Right: Auth --}}
            <div style="display:flex;align-items:center;gap:0.5rem">
                @auth
                    {{-- Notification Bell --}}
                    <div x-data="notificationBell()" style="position:relative">
                        <button @click="open = !open"
                            style="display:flex;align-items:center;justify-content:center;width:36px;height:36px;border-radius:0.625rem;border:1.5px solid #E2E2E7;background:#fff;cursor:pointer;color:#6B6B80;position:relative;transition:all 0.15s"
                            @mouseenter="$el.style.background='#F5F5F7'"
                            @mouseleave="$el.style.background='#fff'">
                            <ion-icon name="notifications-outline" style="font-size:1.125rem"></ion-icon>
                            <span x-show="unreadCount > 0" x-text="unreadCount > 9 ? '9+' : unreadCount"
                                style="position:absolute;top:-4px;right:-4px;background:#EF4444;color:#fff;font-size:0.625rem;font-weight:700;border-radius:999px;min-width:16px;height:16px;display:flex;align-items:center;justify-content:center;padding:0 3px;border:2px solid #fff"></span>
                        </button>
                        <div x-show="open" @click.outside="open = false" x-cloak
                            style="position:absolute;right:0;top:calc(100% + 6px);width:320px;background:#fff;border-radius:0.875rem;border:1.5px solid #E2E2E7;z-index:100;overflow:hidden;box-shadow:0 8px 30px rgba(0,0,0,0.12)">
                            <div style="padding:0.75rem 1rem;border-bottom:1.5px solid #E2E2E7;display:flex;justify-content:space-between;align-items:center">
                                <span style="font-weight:700;font-size:0.875rem">การแจ้งเตือน</span>
                                <button @click="markAllRead()" style="font-size:0.75rem;color:#4F46E5;font-weight:600;background:none;border:none;cursor:pointer;padding:0">อ่านทั้งหมด</button>
                            </div>
                            <div style="max-height:280px;overflow-y:auto">
                                <template x-for="n in notifications.slice(0,6)" :key="n.id">
                                    <div style="padding:0.75rem 1rem;border-bottom:1px solid #F5F5F7;transition:background 0.1s"
                                        :style="!n.read_at ? 'background:#F5F3FF' : ''">
                                        <p style="font-size:0.8125rem;font-weight:600;margin:0 0 2px" x-text="n.title"></p>
                                        <p style="font-size:0.75rem;color:#6B6B80;margin:0" x-text="n.body"></p>
                                        {{-- ปุ่มรับ/ปฏิเสธสำหรับคำท้าดวล --}}
                                        <template x-if="n.type === 'challenge' && n.data && n.data.challenge_id && !n.data._resolved">
                                            <div style="display:flex;gap:0.375rem;margin-top:0.5rem">
                                                <button @click.stop="acceptChallenge(n)"
                                                    style="flex:1;padding:0.25rem 0.5rem;background:#111118;color:#fff;border:none;border-radius:0.375rem;font-size:0.75rem;font-weight:600;cursor:pointer;font-family:inherit">
                                                    รับ
                                                </button>
                                                <button @click.stop="declineChallenge(n)"
                                                    style="flex:1;padding:0.25rem 0.5rem;background:#F5F5F7;color:#374151;border:none;border-radius:0.375rem;font-size:0.75rem;font-weight:600;cursor:pointer;font-family:inherit">
                                                    ปฏิเสธ
                                                </button>
                                            </div>
                                        </template>
                                        {{-- แจ้งเตือน challenge_accepted มีลิงก์ไปเกม --}}
                                        <template x-if="n.type === 'challenge_accepted' && n.data && n.data.game_url">
                                            <a :href="n.data.game_url"
                                               style="display:inline-block;margin-top:0.375rem;font-size:0.75rem;color:#4F46E5;font-weight:600;text-decoration:none">
                                                ไปยังเกม →
                                            </a>
                                        </template>
                                    </div>
                                </template>
                                <div x-show="notifications.length === 0" style="padding:2rem;text-align:center;color:#6B6B80;font-size:0.875rem">
                                    <ion-icon name="notifications-off-outline" style="font-size:2rem;display:block;margin:0 auto 0.5rem;color:#C7C7D0"></ion-icon>
                                    ไม่มีการแจ้งเตือน
                                </div>
                            </div>
                            <div style="padding:0.625rem 1rem;border-top:1.5px solid #E2E2E7">
                                <a href="{{ route('notifications.index') }}" style="display:block;text-align:center;font-size:0.8125rem;font-weight:600;color:#4F46E5;text-decoration:none">ดูทั้งหมด</a>
                            </div>
                        </div>
                    </div>

                    {{-- User Menu --}}
                    <div x-data="{ open: false }" style="position:relative">
                        <button @click="open = !open"
                            style="display:flex;align-items:center;gap:0.5rem;padding:0.3125rem 0.625rem 0.3125rem 0.375rem;border-radius:0.625rem;border:1.5px solid #E2E2E7;background:#fff;cursor:pointer;transition:all 0.15s"
                            @mouseenter="$el.style.background='#F5F5F7'"
                            @mouseleave="$el.style.background='#fff'">
                            <img src="{{ auth()->user()->getAvatarUrl() }}" class="avatar avatar-sm" alt="">
                            <span class="hidden lg:block" style="font-size:0.875rem;font-weight:600;color:#111118">{{ auth()->user()->getDisplayName() }}</span>
                            <span class="hidden lg:block" style="font-size:0.75rem;color:#6B6B80">[{{ auth()->user()->rank }}]</span>
                            <ion-icon name="chevron-down-outline" style="font-size:0.875rem;color:#6B6B80"></ion-icon>
                        </button>
                        <div x-show="open" @click.outside="open = false" x-cloak
                            style="position:absolute;right:0;top:calc(100% + 6px);width:200px;background:#fff;border-radius:0.875rem;border:1.5px solid #E2E2E7;z-index:100;overflow:hidden;box-shadow:0 8px 30px rgba(0,0,0,0.1);padding:0.375rem">
                            <a href="{{ route('profile.show', auth()->user()->username) }}"
                                style="display:flex;align-items:center;gap:0.5rem;padding:0.5rem 0.75rem;border-radius:0.5rem;font-size:0.875rem;color:#111118;text-decoration:none;transition:background 0.1s"
                                @mouseenter="$el.style.background='#F5F5F7'" @mouseleave="$el.style.background=''">
                                <ion-icon name="person-outline"></ion-icon> โปรไฟล์
                            </a>
                            <a href="{{ route('profile.edit') }}"
                                style="display:flex;align-items:center;gap:0.5rem;padding:0.5rem 0.75rem;border-radius:0.5rem;font-size:0.875rem;color:#111118;text-decoration:none;transition:background 0.1s"
                                @mouseenter="$el.style.background='#F5F5F7'" @mouseleave="$el.style.background=''">
                                <ion-icon name="settings-outline"></ion-icon> ตั้งค่า
                            </a>
                            @if(auth()->user()->is_admin)
                            <a href="{{ route('admin.dashboard') }}"
                                style="display:flex;align-items:center;gap:0.5rem;padding:0.5rem 0.75rem;border-radius:0.5rem;font-size:0.875rem;color:#4F46E5;text-decoration:none;transition:background 0.1s"
                                @mouseenter="$el.style.background='#F5F3FF'" @mouseleave="$el.style.background=''">
                                <ion-icon name="shield-outline"></ion-icon> แอดมิน
                            </a>
                            @endif
                            <div style="height:1.5px;background:#E2E2E7;margin:0.375rem 0.5rem"></div>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit"
                                    style="display:flex;align-items:center;gap:0.5rem;width:100%;padding:0.5rem 0.75rem;border-radius:0.5rem;font-size:0.875rem;color:#DC2626;background:none;border:none;cursor:pointer;transition:background 0.1s;text-align:left;font-family:inherit"
                                    @mouseenter="$el.style.background='#FEF2F2'" @mouseleave="$el.style.background=''">
                                    <ion-icon name="log-out-outline"></ion-icon> ออกจากระบบ
                                </button>
                            </form>
                        </div>
                    </div>
                @else
                    <a href="{{ route('login') }}" class="btn btn-secondary btn-sm hidden lg:inline-flex">เข้าสู่ระบบ</a>
                    <a href="{{ route('register') }}" class="btn btn-primary btn-sm hidden lg:inline-flex">ลงทะเบียน</a>
                @endauth

                {{-- Hamburger button (mobile only) --}}
                <button @click="mobileOpen = !mobileOpen" class="lg:hidden flex"
                    style="align-items:center;justify-content:center;width:36px;height:36px;border-radius:0.625rem;border:1.5px solid #E2E2E7;background:#fff;cursor:pointer;color:#111118"
                    :aria-expanded="mobileOpen">
                    <ion-icon :name="mobileOpen ? 'close-outline' : 'menu-outline'" style="font-size:1.25rem"></ion-icon>
                </button>
            </div>
        </div>
    </div>

    {{-- Mobile menu --}}
    <div x-show="mobileOpen" x-cloak @click.outside="mobileOpen = false"
        style="background:#fff;border-top:1.5px solid #E2E2E7" class="lg:hidden">
        <div style="padding:0.5rem 1rem;display:flex;flex-direction:column;gap:0.125rem">
            <a href="{{ route('lobby') }}" class="nav-link" style="padding:0.625rem 0.75rem">
                <ion-icon name="grid-outline"></ion-icon> ล็อบบี้
            </a>
            @auth
            <a href="{{ route('bots.index') }}" class="nav-link" style="padding:0.625rem 0.75rem;color:#4F46E5;font-weight:700">
                <ion-icon name="hardware-chip-outline"></ion-icon> เล่นกับคอม
            </a>
            @endauth
            <a href="{{ route('chat.global') }}" class="nav-link" style="padding:0.625rem 0.75rem">
                <ion-icon name="chatbubbles-outline"></ion-icon> แชท
            </a>
            <a href="{{ route('groups.index') }}" class="nav-link" style="padding:0.625rem 0.75rem">
                <ion-icon name="people-outline"></ion-icon> กลุ่ม
            </a>
            @auth
            <a href="{{ route('friends.index') }}" class="nav-link" style="padding:0.625rem 0.75rem">
                <ion-icon name="person-add-outline"></ion-icon> เพื่อน
            </a>
            <a href="{{ route('users.search') }}" class="nav-link" style="padding:0.625rem 0.75rem">
                <ion-icon name="search-outline"></ion-icon> ค้นหาผู้ใช้
            </a>
            @endauth
            @guest
            <div style="padding:0.5rem 0;margin-top:0.25rem;border-top:1.5px solid #E2E2E7;display:flex;gap:0.5rem">
                <a href="{{ route('login') }}" class="btn btn-secondary btn-sm" style="flex:1;text-align:center">เข้าสู่ระบบ</a>
                <a href="{{ route('register') }}" class="btn btn-primary btn-sm" style="flex:1;text-align:center">ลงทะเบียน</a>
            </div>
            @endguest
        </div>
    </div>
</nav>

{{-- ── Main Content ─────────────────────────────── --}}
<main style="max-width:1280px;margin:0 auto;padding:1.5rem 1rem">

    @if(session('success'))
    <div class="flash-success" style="margin-bottom:1rem">
        <ion-icon name="checkmark-circle-outline" style="font-size:1.125rem;flex-shrink:0"></ion-icon>
        {{ session('success') }}
    </div>
    @endif

    @if($errors->any())
    <div class="flash-error" style="margin-bottom:1rem">
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

<script>
    window.__AUTH_USER_ID__ = {{ auth()->id() ?? 'null' }};

    function userSearch() {
        return {
            q: '',
            results: [],
            open: false,
            loading: false,
            async search() {
                if (this.q.length < 2) { this.results = []; this.open = false; return; }
                this.loading = true;
                try {
                    const res = await fetch('{{ route('users.search') }}?q=' + encodeURIComponent(this.q), {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                    });
                    this.results = await res.json();
                    this.open = true;
                } finally {
                    this.loading = false;
                }
            },
            close() { this.open = false; },
            goToSearch() {
                if (this.q.length >= 2) {
                    window.location.href = '{{ route('users.search') }}?q=' + encodeURIComponent(this.q);
                }
            }
        };
    }
</script>
</body>
</html>
