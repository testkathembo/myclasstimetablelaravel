<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class UserController extends Controller
{
    public function index()
    {
        $users = User::all();
        return Inertia::render('Users/index', ['users' => $users]);
    }

    public function store(Request $request)
{
    $validatedData = $request->validate([
        'code' => 'required|string|max:255|unique:users',
        'first_name' => 'required|string|max:255',
        'last_name' => 'required|string|max:255',
        'faculty' => 'required|string|max:255',
        'email' => 'required|string|email|max:255|unique:users',
        'phone' => 'required|string|max:255|unique:users',
        'role' => 'required|string|max:255',
        'password' => 'required|string|min:8',
    ]);

    // Hash the password
    $validatedData['password'] = bcrypt($validatedData['password']);

    User::create($validatedData);

    return redirect()->route('users.index');
}

    public function update(Request $request, User $user)
    {
        $request->validate([
            'code' => 'required|string|max:255|unique:users,code,' . $user->id,
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'faculty' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'phone' => 'required|string|max:255|unique:users,phone,' . $user->id,
            'role' => 'required|string|max:255',
        ]);

        $user->update($request->all());
        return redirect()->route('users.index');
    }

    public function destroy(User $user)
    {
        $user->delete();
        return redirect()->route('users.index');
    }
}