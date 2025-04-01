<?php

namespace App\Http\Controllers;

use App\Models\Group;
use Illuminate\Http\Request;
use Inertia\Inertia;

class GroupController extends Controller
{
    public function index()
    {
        $groups = Group::paginate(10); // Use pagination
        return Inertia::render('Groups/index', [
            'groups' => $groups,
            'perPage' => 10,
            'search' => request('search', ''),
        ]);
    }

    public function create()
    {
        return Inertia::render('Groups/Create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|unique:groups',
        ]);

        Group::create($request->only('name'));

        return redirect()->route('groups.index')
                         ->with('success', 'Group created successfully.');
    }

    public function show(Group $group)
    {
        return Inertia::render('Groups/Show', ['group' => $group]);
    }

    public function edit(Group $group)
    {
        return Inertia::render('Groups/Edit', ['group' => $group]);
    }

    public function update(Request $request, Group $group)
    {
        $request->validate([
            'name' => 'required|unique:groups,name,' . $group->id,
        ]);

        $group->update($request->only('name'));

        return redirect()->route('groups.index')
                         ->with('success', 'Group updated successfully.');
    }

    public function destroy(Group $group)
    {
        $group->delete();

        return redirect()->route('groups.index')
                         ->with('success', 'Group deleted successfully.');
    }
}
