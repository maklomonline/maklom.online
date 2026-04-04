<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminLog;
use App\Models\User;
use Illuminate\Http\Request;

class UserManagementController extends Controller
{
    public function index(Request $request)
    {
        $query = User::with('stats')->latest();

        if ($request->filled('search')) {
            $q = $request->search;
            $query->where(fn ($q2) => $q2->where('name', 'like', "%$q%")
                ->orWhere('username', 'like', "%$q%")
                ->orWhere('email', 'like', "%$q%"));
        }

        if ($request->filled('status')) {
            match ($request->status) {
                'banned' => $query->where('is_banned', true),
                'online' => $query->online(),
                'admin' => $query->admin(),
                default => null,
            };
        }

        $users = $query->paginate(20)->withQueryString();

        return view('admin.users.index', compact('users'));
    }

    public function show(User $user)
    {
        $user->load('stats');

        return view('admin.users.show', compact('user'));
    }

    public function ban(User $user, Request $request)
    {
        $request->validate([
            'ban_reason' => ['required', 'string', 'max:500'],
            'banned_until' => ['nullable', 'date', 'after:now'],
        ]);

        $user->update([
            'is_banned' => true,
            'ban_reason' => $request->ban_reason,
            'banned_until' => $request->banned_until,
        ]);

        AdminLog::create([
            'admin_id' => $request->user()->id,
            'action' => 'ban_user',
            'target_type' => 'user',
            'target_id' => $user->id,
            'notes' => $request->ban_reason,
            'ip_address' => $request->ip(),
        ]);

        return back()->with('success', "ระงับบัญชี {$user->name} แล้ว");
    }

    public function unban(User $user, Request $request)
    {
        $user->update(['is_banned' => false, 'ban_reason' => null, 'banned_until' => null]);

        AdminLog::create([
            'admin_id' => $request->user()->id,
            'action' => 'unban_user',
            'target_type' => 'user',
            'target_id' => $user->id,
            'ip_address' => $request->ip(),
        ]);

        return back()->with('success', "ยกเลิกการระงับบัญชี {$user->name} แล้ว");
    }

    public function delete(User $user, Request $request)
    {
        if ($user->id === $request->user()->id) {
            return back()->withErrors(['user' => 'ไม่สามารถลบบัญชีของตัวเองได้']);
        }

        AdminLog::create([
            'admin_id' => $request->user()->id,
            'action' => 'delete_user',
            'target_type' => 'user',
            'target_id' => $user->id,
            'notes' => "Deleted user: {$user->email}",
            'ip_address' => $request->ip(),
        ]);

        $user->delete();

        return redirect()->route('admin.users.index')->with('success', 'ลบบัญชีผู้ใช้แล้ว');
    }
}
