@extends('layouts.app')
@section('title', 'ค้นหาผู้ใช้')
@section('content')
<div style="max-width:640px;margin:0 auto">
    <h1 style="font-size:1.25rem;font-weight:800;color:#111118;margin:0 0 1.25rem">ค้นหาผู้ใช้</h1>

    {{-- Search form --}}
    <form method="GET" action="{{ route('users.search') }}" style="margin-bottom:1.5rem">
        <div style="display:flex;gap:0.5rem">
            <div style="flex:1;position:relative">
                <ion-icon name="search-outline" style="position:absolute;left:0.75rem;top:50%;transform:translateY(-50%);color:#9CA3AF;font-size:1rem;pointer-events:none"></ion-icon>
                <input type="text" name="q" value="{{ $q }}" autofocus autocomplete="off"
                    placeholder="ค้นหาชื่อหรือ username..."
                    style="width:100%;padding:0.625rem 0.75rem 0.625rem 2.25rem;border:1.5px solid #E2E2E7;border-radius:0.625rem;font-size:0.9375rem;font-family:inherit;color:#111118;outline:none;box-sizing:border-box;transition:border-color 0.15s"
                    onfocus="this.style.borderColor='#4F46E5'" onblur="this.style.borderColor='#E2E2E7'">
            </div>
            <button type="submit"
                style="padding:0.625rem 1.25rem;background:#111118;color:#fff;border:none;border-radius:0.625rem;font-size:0.9375rem;font-weight:600;cursor:pointer;font-family:inherit;white-space:nowrap">
                ค้นหา
            </button>
        </div>
    </form>

    {{-- Results --}}
    @if($q !== '')
        @if($users->isEmpty())
            <div style="text-align:center;padding:3rem 0;color:#9CA3AF">
                <ion-icon name="person-outline" style="font-size:2.5rem;display:block;margin:0 auto 0.75rem;color:#D1D5DB"></ion-icon>
                ไม่พบผู้ใช้ที่ตรงกับ "<strong style="color:#374151">{{ $q }}</strong>"
            </div>
        @else
            <p style="font-size:0.8125rem;color:#9CA3AF;margin:0 0 0.75rem">พบ {{ $users->count() }} ผู้ใช้</p>
            <div style="display:flex;flex-direction:column;gap:0.5rem">
                @foreach($users as $user)
                <a href="{{ route('profile.show', $user->username) }}"
                    style="display:flex;align-items:center;gap:0.875rem;padding:0.875rem 1rem;background:#fff;border:1.5px solid #E2E2E7;border-radius:0.875rem;text-decoration:none;color:inherit;transition:border-color 0.15s,box-shadow 0.15s"
                    onmouseenter="this.style.borderColor='#4F46E5';this.style.boxShadow='0 2px 12px rgba(79,70,229,0.08)'"
                    onmouseleave="this.style.borderColor='#E2E2E7';this.style.boxShadow='none'">
                    <img src="{{ $user->getAvatarUrl() }}" alt=""
                        style="width:44px;height:44px;border-radius:50%;object-fit:cover;flex-shrink:0">
                    <div style="flex:1;min-width:0">
                        <div style="font-weight:700;font-size:0.9375rem;color:#111118">{{ $user->getDisplayName() }}</div>
                        <div style="font-size:0.8125rem;color:#6B6B80">{{ $user->username }} · {{ $user->rank }}</div>
                    </div>
                    @if($user->last_seen_at && $user->last_seen_at->gte(now()->subMinutes(5)))
                        <span style="display:inline-flex;align-items:center;gap:0.3rem;font-size:0.75rem;font-weight:600;color:#16A34A;background:#F0FDF4;padding:0.2rem 0.6rem;border-radius:999px">
                            <span style="width:6px;height:6px;border-radius:50%;background:#16A34A;flex-shrink:0"></span>ออนไลน์
                        </span>
                    @endif
                    <ion-icon name="chevron-forward-outline" style="font-size:1rem;color:#9CA3AF;flex-shrink:0"></ion-icon>
                </a>
                @endforeach
            </div>
        @endif
    @else
        <div style="text-align:center;padding:3rem 0;color:#9CA3AF">
            <ion-icon name="search-outline" style="font-size:2.5rem;display:block;margin:0 auto 0.75rem;color:#D1D5DB"></ion-icon>
            พิมพ์อย่างน้อย 2 ตัวอักษรเพื่อค้นหา
        </div>
    @endif
</div>
@endsection
