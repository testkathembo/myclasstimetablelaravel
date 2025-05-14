<?php

namespace App\Http\Controllers;

use App\Models\School;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class SchoolController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
        $this->authorizeResource(School::class, 'school');
    }

    /**
     * Display a listing of the schools.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Inertia\Response
     */
    public function index(Request $request)
    {
        $query = School::query();

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

        return Inertia::render('Schools/Index', [
            'schools' => $schools,
            'filters' => $request->only(['search', 'sort_field', 'sort_direction', 'per_page']),
            'can' => [
                'create' => Gate::allows('create', School::class),
            ],
        ]);
    }

    /**
     * Show the form for creating a new school.
     *
     * @return \Inertia\Response
     */
    public function create()
    {
        return Inertia::render('Schools/Create');
    }

    /**
     * Store a newly created school in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:255|unique:schools,code',
            'name' => 'required|string|max:255',
        ]);

        School::create($validated);

        return redirect()->route('schools.index')
            ->with('success', 'School created successfully.');
    }

    /**
     * Display the specified school.
     *
     * @param  \App\Models\School  $school
     * @return \Inertia\Response
     */
    public function show(School $school)
    {
        $school->load(['programs' => function ($query) {
            $query->withCount('units');
        }]);

        return Inertia::render('Schools/Show', [
            'school' => $school,
            'can' => [
                'update' => Gate::allows('update', $school),
                'delete' => Gate::allows('delete', $school),
            ],
        ]);
    }

    /**
     * Show the form for editing the specified school.
     *
     * @param  \App\Models\School  $school
     * @return \Inertia\Response
     */
    public function edit(School $school)
    {
        return Inertia::render('Schools/Edit', [
            'school' => $school,
        ]);
    }

    /**
     * Update the specified school in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\School  $school
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, School $school)
    {
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
     *
     * @param  \App\Models\School  $school
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(School $school)
    {
        // Check if the school has any programs
        if ($school->programs()->exists()) {
            return redirect()->route('schools.index')
                ->with('error', 'Cannot delete school because it has associated programs.');
        }

        $school->delete();

        return redirect()->route('schools.index')
            ->with('success', 'School deleted successfully.');
    }

    /**
     * Display the school dashboard with statistics.
     *
     * @param  \App\Models\School  $school
     * @return \Inertia\Response
     */
    public function dashboard(School $school)
    {
        $programCount = $school->programs()->count();
        $unitCount = $school->units()->count();
        $enrollmentCount = $school->enrollments()->count();
        
        $programsWithStudentCounts = $school->programs()
            ->withCount(['enrollments as student_count' => function ($query) {
                $query->select(\DB::raw('count(distinct student_code)'));
            }])
            ->get();

        return Inertia::render('Schools/Dashboard', [
            'school' => $school,
            'stats' => [
                'programCount' => $programCount,
                'unitCount' => $unitCount,
                'enrollmentCount' => $enrollmentCount,
            ],
            'programs' => $programsWithStudentCounts,
        ]);
    }
}