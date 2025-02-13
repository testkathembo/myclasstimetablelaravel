<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Providers\RouteServiceProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(Request $request)
    {
        $request->validate([
            'code' => ['required', 'string', 'exists:users,code'], // ✅ Ensure 'code' exists in users table
            'password' => ['required', 'string'],
        ]);
    
        if (!Auth::attempt(['code' => $request->code, 'password' => $request->password], $request->filled('remember'))) {
            return back()->withErrors(['code' => 'The provided credentials do not match our records.']);
        }
    
        $request->session()->regenerate();
    
        return redirect()->intended(route('dashboard'));
    }



    public function destroy(Request $request)
    {
        Auth::guard('web')->logout(); // ✅ Logs out the user

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/'); // ✅ Redirects to homepage after logout
    }

}    