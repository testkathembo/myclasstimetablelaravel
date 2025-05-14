<?php

namespace App\Http\Controllers;

use App\Models\Semester;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class SemesterController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
        $this->authorizeResource(Semester::class, 'semester');
    }

    /**
     * Display a listing of the semesters.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Inertia\Response
     */
    public function index(Request $request)
    {
        $query = Semester::query();

        // Filter by active status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Search functionality
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where('name', 'like', "%{$search}%");
        }

        // Sorting
        $sortField = $request->input('sort_field', 'name');
        $sortDirection = $request->input('sort_direction', 'asc');
        $query->orderBy($sortField, $sortDirection);

        // Pagination
        $perPage = $request->input('per_page', 10);
        $semesters = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Semesters/Index', [
            'semesters' => $semesters,
            'filters' => $request->only(['search', 'is_active', 'sort_field', 'sort_direction', 'per_page']),
            'can' => [
                'create' => Gate::allows('create', Semester::class),
            ],
        ]);
    }

    /**
     * Show the form for creating a new semester.
     *
     * @return \Inertia\Response
     */
    public function create()
    {
        return Inertia::render('Semesters/Create');
    }

    /**
     * Store a newly created semester in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:semesters,name',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'is_active' => 'boolean',
        ]);

        // If this semester is active, deactivate all others
        if ($request->boolean('is_active')) {
            Semester::where('is_active', true)->update(['is_active' => false]);
        }

        Semester::create($validated);

        return redirect()->route('semesters.index')
            ->with('success', 'Semester created successfully.');
    }

    /**
     * Display the specified semester.
     *
     * @param  \App\Models\Semester  $semester
     * @return \Inertia\Response
     */
    public function show(Semester $semester)
    {
        $unitCount = $semester->units()->count();
        $enrollmentCount = $semester->enrollments()->count();
        $classTimetableCount = $semester->classTimetables()->count();
        $examTimetableCount = $semester->examTimetables()->count();

        return Inertia::render('Semesters/Show', [
            'semester' => $semester,
            'stats' => [
                'unitCount' => $unitCount,
                'enrollmentCount' => $enrollmentCount,
                'classTimetableCount' => $classTimetableCount,
                'examTimetableCount' => $examTimetableCount,
            ],
            'can' => [
                'update' => Gate::allows('update', $semester),
                'delete' => Gate::allows('delete', $semester),
            ],
        ]);
    }

    /**
     * Show the form for editing the specified semester.
     *
     * @param  \App\Models\Semester  $semester
     * @return \Inertia\Response
     */
    public function edit(Semester $semester)
    {
        return Inertia::render('Semesters/Edit', [
            'semester' => $semester,
        ]);
    }

    /**
     * Update the specified semester in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Semester  $semester
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, Semester $semester)
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('semesters')->ignore($semester->id),
            ],
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'is_active' => 'boolean',
        ]);

        // If this semester is being activated, deactivate all others
        if ($request->boolean('is_active') && !$semester->is_active) {
            Semester::where('is_active', true)->update(['is_active' => false]);
        }

        $semester->update($validated);

        return redirect()->route('semesters.index')
            ->with('success', 'Semester updated successfully.');
    }

    /**
     * Remove the specified semester from storage.
     *
     * @param  \App\Models\Semester  $semester
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(Semester $semester)
    {
        // Check if the semester has any associated data
        if (
            $semester->units()->exists() ||
            $semester->enrollments()->exists() ||
            $semester->classTimetables()->exists() ||
            $semester->examTimetables()->exists()
        ) {
            return redirect()->route('semesters.index')
                ->with('error', 'Cannot delete semester because it has associated data.');
        }

        $semester->delete();

        return redirect()->route('semesters.index')
            ->with('success', 'Semester deleted successfully.');
    }

    /**
     * Set the specified semester as active.
     *
     * @param  \App\Models\Semester  $semester
     * @return \Illuminate\Http\RedirectResponse
     */
    public function setActive(Semester $semester)
    {
        $this->authorize('update', $semester);

        // Deactivate all semesters
        Semester::where('is_active', true)->update(['is_active' => false]);

        // Activate the specified semester
        $semester->update(['is_active' => true]);

        return redirect()->route('semesters.index')
            ->with('success', 'Semester "' . $semester->name . '" is now active.');
    }
}