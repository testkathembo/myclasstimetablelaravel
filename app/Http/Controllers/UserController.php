<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    // ✅ Show paginated, searchable user list
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 10);
        $search = $request->get('search', '');

        $users = User::query()
            ->when($search, function ($query, $search) {
                $query->where('code', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('faculty', 'like', "%{$search}%");
            })
            ->with('roles') // Load roles with user
            ->paginate($perPage);

        return Inertia::render('Users/index', [
            'users' => $users,
            'perPage' => $perPage,
            'search' => $search,
        ]);
    }

    public function store(Request $request)
    {
        // Validate request
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'faculty' => 'required|string|max:255',
            'phone' => 'required|string|max:255',
            'code' => 'required|string|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|string|exists:roles,name',
        ]);

        // Create user
        $user = User::create([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'],
            'faculty' => $validated['faculty'],
            'phone' => $validated['phone'],
            'code' => $validated['code'],
            'password' => Hash::make($validated['password']),
        ]);

        // Assign role
        $user->assignRole($validated['role']);

        return redirect()->route('users.index')
            ->with('success', 'User created successfully');
    }

    // ✅ Show user edit form
    public function edit(User $user)
    {
        return Inertia::render('Users/Edit', ['user' => $user]);
    }

    // ✅ Update user
    public function update(Request $request, User $user)
    {
        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'faculty' => 'required|string|max:255',
            'phone' => 'required|string|max:255',
            'code' => 'required|string|max:255|unique:users,code,' . $user->id,
        ]);

        $user->update($request->only('first_name', 'last_name', 'email', 'faculty', 'phone', 'code'));

        return redirect()->route('users.index')->with('success', 'User updated successfully.');
    }

    // ✅ Delete user
    public function destroy(User $user)
    {
        $user->delete();

        return redirect()->route('users.index')->with('success', 'User deleted successfully!');
    }

    // ✅ Show form to change user's role
    public function editRole(User $user)
    {
        $roles = Role::pluck('name');
        $currentRole = $user->roles->pluck('name')->first();

        return Inertia::render('Users/EditRole', [
            'user' => $user,
            'roles' => $roles,
            'currentRole' => $currentRole,
        ]);
    }

    // ✅ Save new role for user
    public function updateRole(Request $request, User $user)
    {
        $request->validate([
            'role' => 'required|exists:roles,name',
        ]);

        $user->syncRoles([$request->role]);

        return redirect()->route('users.index')->with('success', 'User role updated successfully!');
    }
}
