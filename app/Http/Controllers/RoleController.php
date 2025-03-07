<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;
use Inertia\Inertia;

class RoleController extends Controller
{
    // ✅ List all roles
    public function index()
    {
        $roles = Role::with('permissions')->get();
        $permissions = Permission::all();
        return Inertia::render('Roles/Index', [
            'roles' => $roles,
            'permissions' => $permissions
        ]);
    }

    // ✅ Create a new role
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|unique:roles,name'
        ]);

        Role::create(['name' => $request->name]);

        return redirect()->back()->with('success', 'Role created successfully!');
    }

    // ✅ Assign permissions to a role
    public function updatePermissions(Request $request, Role $role)
    {
        $request->validate([
            'permissions' => 'required|array',
            'permissions.*' => 'exists:permissions,name',
        ]);

        $role->syncPermissions($request->permissions);

        return redirect()->back()->with('success', 'Permissions updated successfully!');
    }

    // ✅ Delete a role
    public function destroy(Role $role)
    {
        $role->delete();

        return redirect()->back()->with('success', 'Role deleted successfully!');
    }

    // ✅ Assign role to a user
    public function assignRole(Request $request, User $user)
    {
        $user->syncRoles($request->roles);

        return redirect()->back()->with('success', 'User roles updated successfully!');
    }
}
