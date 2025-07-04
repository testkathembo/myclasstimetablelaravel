<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SchoolBasedAccess
{
    /**
     * Handle school-based access using Spatie permissions
     */
    public function handle(Request $request, Closure $next, $schoolCode = null)
    {
        $user = Auth::user();
        
        if (!$user) {
            return redirect()->route('login');
        }

        // Super Admin can access everything
        if ($user->hasRole('Super Admin') || $user->hasRole('Admin')) {
            return $next($request);
        }

        // Get user's school from their specific faculty admin role
        $userSchoolCode = $this->getUserSchoolFromRole($user);
        
        if (!$userSchoolCode) {
            abort(403, 'No faculty assignment found. Please contact administrator.');
        }

        // If school code is provided in middleware parameter, check it matches user's school
        if ($schoolCode) {
            $schoolCode = strtoupper($schoolCode);
            
            if ($userSchoolCode !== $schoolCode) {
                abort(403, "Access denied: You can only access {$userSchoolCode} data, not {$schoolCode}");
            }
        }

        // Set school context for the request
        $request->merge(['current_school_code' => $userSchoolCode]);
        
        return $next($request);
    }

    /**
     * Get user's school code from their faculty admin role
     */
    private function getUserSchoolFromRole($user)
    {
        $roles = $user->getRoleNames();
        
        foreach ($roles as $role) {
            if (str_starts_with($role, 'Faculty Admin - ')) {
                return str_replace('Faculty Admin - ', '', $role);
            }
        }
        
        return null;
    }
}
