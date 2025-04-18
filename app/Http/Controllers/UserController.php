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

    // ✅ Create new user
    public function store(Request $request)
    {
        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'faculty' => 'required|string|max:255',
            'phone' => 'required|string|max:15',
            'code' => 'required|string|max:10',
            'password' => 'required|string|min:8',
        ]);

        $data = $request->all();
        $data['password'] = Hash::make($data['password']);

        $user = User::create($data);

        // You can optionally assign a default role here if needed
        // $user->assignRole('Student');

        return redirect()->route('users.index')->with('success', 'User created successfully!');
    }

    // ✅ Update user info
    public function update(Request $request, User $user)
    {
        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'faculty' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'phone' => 'required|string|min:6',
            'code' => 'required|string|min:4',
            'password' => 'nullable|string|min:6',
        ]);

        $user->update([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'faculty' => $request->faculty,
            'email' => $request->email,
            'phone' => $request->phone,
            'code' => $request->code,
            'password' => $request->password ? Hash::make($request->password) : $user->password,
        ]);

        return redirect()->back()->with('success', 'User updated successfully!');
    }

    // ✅ Show user edit form
    public function edit(User $user)
    {
        return Inertia::render('Users/Edit', ['user' => $user]);
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
