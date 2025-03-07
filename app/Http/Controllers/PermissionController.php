<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Inertia\Inertia;

class PermissionController extends Controller
{
    // List all permissions
    public function index()
    {
        $permissions = Permission::all();
        return Inertia::render('Permissions/Index', [
            'permissions' => $permissions
        ]);
    }

    // Store a new permission
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|unique:permissions,name'
        ]);

        Permission::create(['name' => $request->name]);

        return redirect()->back()->with('success', 'Permission created successfully!');
    }

    // Update an existing permission
    public function update(Request $request, Permission $permission)
    {
        $request->validate([
            'name' => 'required|unique:permissions,name,' . $permission->id
        ]);

        $permission->update(['name' => $request->name]);

        return redirect()->back()->with('success', 'Permission updated successfully!');
    }

    // Delete a permission
    public function destroy(Permission $permission)
    {
        $permission->delete();

        return redirect()->back()->with('success', 'Permission deleted successfully!');
    }
}
