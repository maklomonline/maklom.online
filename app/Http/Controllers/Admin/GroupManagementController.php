<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminLog;
use App\Models\Group;
use Illuminate\Http\Request;

class GroupManagementController extends Controller
{
    public function index()
    {
        $groups = Group::withCount('members')->latest()->paginate(20);

        return view('admin.groups.index', compact('groups'));
    }

    public function show(Group $group)
    {
        $group->load('owner');
        $members = $group->members()->withPivot('role')->paginate(20);

        return view('admin.groups.show', compact('group', 'members'));
    }

    public function destroy(Group $group, Request $request)
    {
        AdminLog::create([
            'admin_id' => $request->user()->id,
            'action' => 'delete_group',
            'target_type' => 'group',
            'target_id' => $group->id,
            'notes' => "Deleted group: {$group->name}",
            'ip_address' => $request->ip(),
        ]);

        $group->delete();

        return redirect()->route('admin.groups.index')->with('success', 'ลบกลุ่มแล้ว');
    }
}
