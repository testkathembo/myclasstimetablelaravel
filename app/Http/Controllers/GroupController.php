<?php
namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\ClassModel;
use Illuminate\Http\Request;

class GroupController extends Controller
{
    public function index()
    {
        $groups = Group::with('class')->paginate(10);
        $classes = ClassModel::all();

        return inertia('Groups/Index', [
            'groups' => $groups,
            'classes' => $classes,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'class_id' => 'required|exists:classes,id',
            'capacity' => 'required|integer|min:1',
        ]);

        Group::create($validated);

        return redirect()->back()->with('success', 'Group created successfully!');
    }

    public function update(Request $request, Group $group)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'class_id' => 'required|exists:classes,id',
            'capacity' => 'required|integer|min:1',
        ]);

        $group->update($validated);

        return redirect()->back()->with('success', 'Group updated successfully!');
    }

    public function destroy(Group $group)
    {
        $group->delete();

        return redirect()->back()->with('success', 'Group deleted successfully!');
    }
}
