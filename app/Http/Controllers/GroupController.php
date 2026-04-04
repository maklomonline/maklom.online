<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateGroupRequest;
use App\Models\ChatRoom;
use App\Models\Group;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class GroupController extends Controller
{
    public function index()
    {
        $groups = Group::withCount('members')->where('is_public', true)->latest()->paginate(20);

        return view('groups.index', compact('groups'));
    }

    public function create()
    {
        return view('groups.create');
    }

    private function generateUniqueSlug(string $name): string
    {
        $slug = Str::slug($name);

        if (empty($slug)) {
            $slug = 'group-'.Str::lower(Str::random(8));
        }

        $base = $slug;
        $count = 1;
        while (Group::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$count++;
        }

        return $slug;
    }

    public function store(CreateGroupRequest $request)
    {
        $group = Group::create([
            'name' => $request->name,
            'slug' => $this->generateUniqueSlug($request->name),
            'description' => $request->description,
            'owner_id' => $request->user()->id,
            'is_public' => $request->boolean('is_public', true),
            'max_members' => $request->input('max_members', 50),
        ]);

        // Add creator as owner member
        $group->members()->attach($request->user()->id, ['role' => 'owner', 'joined_at' => now()]);

        // Create group chat room
        ChatRoom::forGroup($group->id);

        return redirect()->route('groups.show', $group)->with('success', 'สร้างกลุ่มสำเร็จ');
    }

    public function show(Group $group)
    {
        $group->load('owner');
        $group->loadCount('members');
        $members = $group->members()->withPivot('role', 'joined_at')->orderByPivot('joined_at')->paginate(20);

        $chatRoom = ChatRoom::forGroup($group->id);

        return view('groups.show', compact('group', 'members', 'chatRoom'));
    }

    public function edit(Group $group, Request $request)
    {
        if ($group->owner_id !== $request->user()->id && ! $request->user()->is_admin) {
            abort(403);
        }

        return view('groups.edit', compact('group'));
    }

    public function update(Group $group, Request $request)
    {
        if ($group->owner_id !== $request->user()->id && ! $request->user()->is_admin) {
            abort(403);
        }

        $request->validate([
            'description' => ['nullable', 'string', 'max:1000'],
            'is_public' => ['boolean'],
            'max_members' => ['integer', 'min:2', 'max:500'],
        ]);

        $group->update($request->only('description', 'is_public', 'max_members'));

        return back()->with('success', 'อัปเดตข้อมูลกลุ่มแล้ว');
    }

    public function destroy(Group $group, Request $request)
    {
        if ($group->owner_id !== $request->user()->id && ! $request->user()->is_admin) {
            abort(403);
        }

        $group->delete();

        return redirect()->route('groups.index')->with('success', 'ลบกลุ่มแล้ว');
    }

    public function join(Group $group, Request $request)
    {
        $user = $request->user();

        if ($group->isMember($user)) {
            return back()->withErrors(['group' => 'คุณเป็นสมาชิกกลุ่มนี้อยู่แล้ว']);
        }

        if (! $group->is_public) {
            return back()->withErrors(['group' => 'กลุ่มนี้เป็นกลุ่มส่วนตัว']);
        }

        if ($group->getMemberCount() >= $group->max_members) {
            return back()->withErrors(['group' => 'กลุ่มเต็มแล้ว']);
        }

        $group->members()->attach($user->id, ['role' => 'member', 'joined_at' => now()]);

        return back()->with('success', "เข้าร่วมกลุ่ม {$group->name} แล้ว");
    }

    public function leave(Group $group, Request $request)
    {
        $user = $request->user();

        if ($group->owner_id === $user->id) {
            return back()->withErrors(['group' => 'เจ้าของกลุ่มไม่สามารถออกจากกลุ่มได้']);
        }

        $group->members()->detach($user->id);

        return back()->with('success', 'ออกจากกลุ่มแล้ว');
    }

    public function members(Group $group)
    {
        $members = $group->members()->withPivot('role', 'joined_at')->paginate(20);

        return view('groups.members', compact('group', 'members'));
    }

    public function kickMember(Group $group, User $user, Request $request)
    {
        if (! $request->user()->isAdminOf($group) && ! $request->user()->is_admin) {
            abort(403);
        }

        $group->members()->detach($user->id);

        return back()->with('success', 'นำสมาชิกออกจากกลุ่มแล้ว');
    }

    public function promoteMember(Group $group, User $user, Request $request)
    {
        if ($group->owner_id !== $request->user()->id && ! $request->user()->is_admin) {
            abort(403);
        }

        $group->members()->updateExistingPivot($user->id, ['role' => 'moderator']);

        return back()->with('success', 'เลื่อนสมาชิกเป็นผู้ดูแลกลุ่มแล้ว');
    }
}
