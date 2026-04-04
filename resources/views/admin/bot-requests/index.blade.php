@extends('layouts.admin')
@section('title', 'คำขอบัญชีคอมพิวเตอร์')

@section('content')
<div>
    {{-- Tabs --}}
    <div style="display:flex;gap:0.5rem;margin-bottom:1.5rem">
        @foreach(['pending' => 'รอพิจารณา', 'approved' => 'อนุมัติแล้ว', 'rejected' => 'ปฏิเสธแล้ว'] as $tab => $label)
        <a href="{{ route('admin.bot-requests.index', ['status' => $tab]) }}"
            style="padding:0.4375rem 1rem;border-radius:0.5rem;font-size:0.8125rem;font-weight:600;text-decoration:none;transition:all 0.15s;
            {{ $status === $tab ? 'background:#4F46E5;color:#fff' : 'background:#F5F5F7;color:#6B6B80' }}">
            {{ $label }}
            @if($status === $tab)
            <span style="background:rgba(255,255,255,0.2);padding:0.0625rem 0.4rem;border-radius:999px;font-size:0.6875rem;margin-left:0.25rem">
                {{ $requests->total() }}
            </span>
            @endif
        </a>
        @endforeach
    </div>

    @if($requests->isEmpty())
    <div style="background:#fff;border-radius:1rem;border:1.5px solid #E2E2E7;padding:3rem;text-align:center;color:#6B6B80">
        <ion-icon name="hardware-chip-outline" style="font-size:2.5rem;display:block;margin:0 auto 0.75rem;color:#C7C7D0"></ion-icon>
        ไม่มีคำขอ{{ $status === 'pending' ? 'ที่รอพิจารณา' : ($status === 'approved' ? 'ที่อนุมัติแล้ว' : 'ที่ปฏิเสธแล้ว') }}
    </div>
    @else
    <div style="display:flex;flex-direction:column;gap:0.75rem">
        @foreach($requests as $req)
        <div style="background:#fff;border-radius:0.875rem;border:1.5px solid #E2E2E7;padding:1.25rem">
            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;flex-wrap:wrap">

                {{-- Info --}}
                <div style="flex:1;min-width:0">
                    <div style="display:flex;align-items:center;gap:0.5rem;flex-wrap:wrap;margin-bottom:0.375rem">
                        <span style="font-size:0.9375rem;font-weight:700;color:#111118">{{ $req->display_name }}</span>
                        <span style="font-size:0.6875rem;font-weight:700;padding:0.125rem 0.4rem;border-radius:999px;background:#111118;color:#fff;letter-spacing:0.05em">BOT</span>
                        <span style="font-size:0.75rem;font-weight:600;padding:0.15rem 0.5rem;border-radius:999px;background:#EDE9FE;color:#5B21B6">{{ $req->rank }}</span>
                        <span style="font-size:0.75rem;color:#9CA3AF;font-family:'JetBrains Mono',monospace">@{{ $req->username }}</span>
                    </div>
                    @if($req->bio)
                    <p style="font-size:0.8125rem;color:#6B6B80;margin:0 0 0.375rem">{{ $req->bio }}</p>
                    @endif
                    <div style="font-size:0.75rem;color:#9CA3AF;display:flex;flex-wrap:wrap;gap:0.75rem">
                        <span>
                            <ion-icon name="person-outline" style="vertical-align:-2px"></ion-icon>
                            ขอโดย: <strong style="color:#374151">{{ $req->requester?->getDisplayName() }}</strong>
                            ({{ $req->requester?->username }})
                        </span>
                        <span>
                            <ion-icon name="time-outline" style="vertical-align:-2px"></ion-icon>
                            {{ $req->created_at->format('d/m/Y H:i') }}
                        </span>
                        @if($req->reviewed_by)
                        <span>
                            <ion-icon name="shield-outline" style="vertical-align:-2px"></ion-icon>
                            พิจารณาโดย: {{ $req->reviewer?->getDisplayName() }}
                        </span>
                        @endif
                    </div>
                    @if($req->rejection_reason)
                    <div style="margin-top:0.5rem;padding:0.5rem 0.75rem;background:#FEF2F2;border-radius:0.375rem;font-size:0.8125rem;color:#DC2626">
                        <ion-icon name="close-circle-outline" style="vertical-align:-2px"></ion-icon>
                        เหตุผล: {{ $req->rejection_reason }}
                    </div>
                    @endif
                </div>

                {{-- Actions --}}
                @if($req->isPending())
                <div style="display:flex;flex-direction:column;gap:0.5rem;min-width:140px">
                    <form method="POST" action="{{ route('admin.bot-requests.approve', $req) }}">
                        @csrf
                        <button type="submit"
                            style="width:100%;padding:0.4375rem 1rem;background:#16A34A;color:#fff;border:none;border-radius:0.5rem;font-size:0.8125rem;font-weight:600;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:0.375rem;font-family:inherit"
                            onclick="return confirm('อนุมัติบัญชีคอมพิวเตอร์ {{ $req->username }} ?')">
                            <ion-icon name="checkmark-outline" style="font-size:0.9rem"></ion-icon>
                            อนุมัติ
                        </button>
                    </form>

                    <div x-data="{ open: false }">
                        <button @click="open = !open"
                            style="width:100%;padding:0.4375rem 1rem;background:#FEF2F2;color:#DC2626;border:1.5px solid #FECACA;border-radius:0.5rem;font-size:0.8125rem;font-weight:600;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:0.375rem;font-family:inherit">
                            <ion-icon name="close-outline" style="font-size:0.9rem"></ion-icon>
                            ปฏิเสธ
                        </button>
                        <div x-show="open" x-cloak style="margin-top:0.5rem">
                            <form method="POST" action="{{ route('admin.bot-requests.reject', $req) }}">
                                @csrf
                                <textarea name="rejection_reason" rows="2" placeholder="เหตุผล (ไม่บังคับ)"
                                    style="width:100%;padding:0.4375rem 0.5rem;border-radius:0.375rem;border:1.5px solid #E2E2E7;font-size:0.75rem;font-family:inherit;resize:none;box-sizing:border-box"></textarea>
                                <button type="submit"
                                    style="width:100%;padding:0.375rem;background:#DC2626;color:#fff;border:none;border-radius:0.375rem;font-size:0.75rem;font-weight:600;cursor:pointer;font-family:inherit;margin-top:0.25rem">
                                    ยืนยันปฏิเสธ
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                @else
                <div style="padding:0.3125rem 0.75rem;border-radius:0.5rem;font-size:0.8125rem;font-weight:600;
                    {{ $req->status === 'approved' ? 'background:#F0FDF4;color:#16A34A' : 'background:#FEF2F2;color:#DC2626' }}">
                    {{ $req->status === 'approved' ? '✓ อนุมัติแล้ว' : '✗ ปฏิเสธแล้ว' }}
                </div>
                @endif
            </div>
        </div>
        @endforeach
    </div>

    <div style="margin-top:1.25rem">
        {{ $requests->withQueryString()->links() }}
    </div>
    @endif
</div>
@endsection
