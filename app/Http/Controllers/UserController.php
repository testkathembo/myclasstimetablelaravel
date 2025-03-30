<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index()
    {
        $users = User::all(); // Fetch all users
        return Inertia::render('Users/index', ['users' => $users]); // Pass users to the frontend
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'faculty' => 'required|string|max:255',
            'email' => 'required|string|email|unique:users,email',
            'phone' => 'required|string|min:6',
            'code' => 'required|string|min:4',
            'password' => 'required|string|min:6',
        ]);

        User::create([
            'name' => $request->name,
            'faculty' => $request->faculty,
            'email' => $request->email,
            'phone' => $request->phone,
            'code' => $request->code,
            'password' => Hash::make($request->password),
        ]);

        return redirect()->back()->with('success', 'User created successfully!');
    }

    public function update(Request $request, User $user)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'faculty' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'phone' => 'required|string|min:6',
            'code' => 'required|string|min:4',
            'password' => 'nullable|string|min:6',
        ]);

        $user->update([
            'name' => $request->name,
            'faculty' => $request->faculty,
            'email' => $request->email,
            'phone' => $request->phone,
            'code' => $request->code,
            'password' => $request->password ? Hash::make($request->password) : $user->password,
        ]);

        return redirect()->back()->with('success', 'User updated successfully!');
    }
}
