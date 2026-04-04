<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function search(Request $request)
    {
        $q = trim($request->get('q', ''));
        $users = collect();

        if (mb_strlen($q) >= 2) {
            $users = User::where('is_bot', false)
                ->where(function ($query) use ($q) {
                    $query->where('username', 'like', "%{$q}%")
                        ->orWhere('display_name', 'like', "%{$q}%")
                        ->orWhere('name', 'like', "%{$q}%");
                })
                ->orderBy('username')
                ->limit(20)
                ->get(['id', 'username', 'display_name', 'name', 'avatar', 'rank', 'last_seen_at']);
        }

        if ($request->wantsJson()) {
            return response()->json($users->map(fn ($u) => [
                'username'     => $u->username,
                'display_name' => $u->getDisplayName(),
                'avatar_url'   => $u->getAvatarUrl(),
                'rank'         => $u->rank,
                'profile_url'  => route('profile.show', $u->username),
            ]));
        }

        return view('users.search', compact('users', 'q'));
    }

    public function show(User $user)
    {
        $user->load('stats');
        $recentGames = Game::where('black_player_id', $user->id)
            ->orWhere('white_player_id', $user->id)
            ->with('blackPlayer', 'whitePlayer')
            ->where('status', 'finished')
            ->latest()
            ->take(10)
            ->get();

        return view('profile.show', compact('user', 'recentGames'));
    }

    public function edit(Request $request)
    {
        return view('profile.edit', ['user' => $request->user()]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'display_name' => ['nullable', 'string', 'max:60'],
            'bio' => ['nullable', 'string', 'max:500'],
            'confirm_move' => ['sometimes', 'boolean'],
        ]);

        $request->user()->update([
            ...$request->only('display_name', 'bio'),
            'confirm_move' => $request->boolean('confirm_move'),
        ]);

        return back()->with('success', 'อัปเดตโปรไฟล์แล้ว');
    }

    public function updateAvatar(Request $request)
    {
        $request->validate([
            'avatar' => ['required', 'image', 'max:2048'],
        ]);

        $user = $request->user();

        if ($user->avatar) {
            Storage::disk('public')->delete($user->avatar);
        }

        $path = $request->file('avatar')->store('avatars', 'public');
        $user->update(['avatar' => $path]);

        return back()->with('success', 'อัปเดตรูปโปรไฟล์แล้ว');
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'password.min' => 'รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร',
            'password.confirmed' => 'รหัสผ่านไม่ตรงกัน',
        ]);

        $user = $request->user();

        if (! Hash::check($request->current_password, $user->password)) {
            return back()->withErrors(['current_password' => 'รหัสผ่านปัจจุบันไม่ถูกต้อง']);
        }

        $user->update(['password' => Hash::make($request->password)]);

        return back()->with('success', 'เปลี่ยนรหัสผ่านแล้ว');
    }

    public function destroy(Request $request)
    {
        $request->validate([
            'password' => ['required', 'string'],
        ], [
            'password.required' => 'กรุณากรอกรหัสผ่านเพื่อยืนยัน',
        ]);

        $user = $request->user();

        if (! Hash::check($request->password, $user->password)) {
            return back()->withErrors(['delete_password' => 'รหัสผ่านไม่ถูกต้อง']);
        }

        if ($user->avatar) {
            Storage::disk('public')->delete($user->avatar);
        }

        Auth::logout();
        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/')->with('success', 'ลบบัญชีเรียบร้อยแล้ว');
    }
}
