<?php

namespace App\Http\Controllers;

use App\Models\School;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class SchoolController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Display a listing of the schools based on Spatie permissions.
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        
        // Get manageable schools using Spatie permissions
        $manageableSchools = $user->getManageableSchools();
        
        if ($manageableSchools->isEmpty()) {
            abort(403, 'You do not have permission to view any schools.');
        }

        // Filter to only manageable schools
        $schoolIds = $manageableSchools->pluck('id');
        $query = School::whereIn('id', $schoolIds);

        // Search functionality
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        // Sorting
        $sortField = $request->input('sort_field', 'name');
        $sortDirection = $request->input('sort_direction', 'asc');
        $query->orderBy($sortField, $sortDirection);

        // Pagination
        $perPage = $request->input('per_page', 10);
        $schools = $query->paginate($perPage)->withQueryString();

        Log::info('Schools index accessed', [
            'user_id' => $user->id,
            'user_roles' => $user->getRoleNames()->toArray(),
            'manageable_schools_count' => $manageableSchools->count(),
            'manageable_schools' => $manageableSchools->pluck('code')->toArray(),
        ]);

        return Inertia::render('Schools/Index', [
            'schools' => $schools,
            'filters' => $request->only(['search', 'sort_field', 'sort_direction', 'per_page']),
            'can' => [
                'create' => $user->hasRole('Admin') || $user->can('manage schools'),
            ],
            'userRole' => $user->getRoleNames()->first(),
            'manageableSchoolsCount' => $manageableSchools->count(),
        ]);
    }

    /**
     * Show the form for creating a new school.
     */
    public function create()
    {
        $user = auth()->user();
        
        // Only users with general school management permission can create
        if (!$user->hasRole('Admin') && !$user->can('manage schools')) {
            abort(403, 'You do not have permission to create schools.');
        }

        return Inertia::render('Schools/Create');
    }

    /**
     * Store a newly created school in storage.
     */
    public function store(Request $request)
    {
        $user = auth()->user();
        
        if (!$user->hasRole('Admin') && !$user->can('manage schools')) {
            abort(403, 'You do not have permission to create schools.');
        }

        $validated = $request->validate([
            'code' => 'required|string|max:255|unique:schools,code',
            'name' => 'required|string|max:255',
        ]);

        $school = School::create($validated);

        // Automatically create school-specific permissions for the new school
        \Artisan::call('permissions:create-school-specific');

        return redirect()->route('schools.index')
            ->with('success', 'School created successfully.');
    }

    /**
     * Display the specified school.
     */
    public function show(School $school)
    {
        $user = auth()->user();
        
        // Check permission using Spatie
        if (!$user->canViewSchool($school->code)) {
            abort(403, 'You do not have permission to view this school.');
        }

        $school->load(['programs' => function ($query) {
            $query->withCount('units');
        }]);

        return Inertia::render('Schools/Show', [
            'school' => $school,
            'can' => [
                'update' => $user->canManageSchool($school->code),
                'delete' => $user->hasRole('Admin') || $user->can('manage schools'),
            ],
        ]);
    }

    /**
     * Show the form for editing the specified school.
     */
    public function edit(School $school)
    {
        $user = auth()->user();
        
        if (!$user->canManageSchool($school->code)) {
            abort(403, 'You do not have permission to edit this school.');
        }

        return Inertia::render('Schools/Edit', [
            'school' => $school,
        ]);
    }

    /**
     * Update the specified school in storage.
     */
    public function update(Request $request, School $school)
    {
        $user = auth()->user();
        
        if (!$user->canManageSchool($school->code)) {
            abort(403, 'You do not have permission to update this school.');
        }

        $validated = $request->validate([
            'code' => 'required|string|max:255|unique:schools,code,' . $school->id,
            'name' => 'required|string|max:255',
        ]);

        $school->update($validated);

        return redirect()->route('schools.index')
            ->with('success', 'School updated successfully.');
    }

    /**
     * Remove the specified school from storage.
     */
    public function destroy(School $school)
    {
        $user = auth()->user();
        
        // Only super admin can delete schools
        if (!$user->hasRole('Admin') && !$user->can('manage schools')) {
            abort(403, 'You do not have permission to delete schools.');
        }

        if ($school->programs()->exists()) {
            return redirect()->route('schools.index')
                ->with('error', 'Cannot delete school because it has associated programs.');
        }

        $school->delete();

        return redirect()->route('schools.index')
            ->with('success', 'School deleted successfully.');
    }
}
