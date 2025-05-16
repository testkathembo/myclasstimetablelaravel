<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use App\Models\School;
use App\Models\Program;
use App\Models\Semester;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class UnitController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
        $this->authorizeResource(Unit::class, 'unit');
    }

    /**
     * Display a listing of the units.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Inertia\Response
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Unit::class); // Ensure the user has permission to view units

        $query = Unit::query()->with(['school', 'program', 'semester']);

        // Filter by school
        if ($request->has('school_id') && $request->input('school_id')) {
            $query->where('school_id', $request->input('school_id'));
        }

        // Filter by program
        if ($request->has('program_id') && $request->input('program_id')) {
            $query->where('program_id', $request->input('program_id'));
        }

        // Filter by semester
        if ($request->has('semester_id') && $request->input('semester_id')) {
            $query->where('semester_id', $request->input('semester_id'));
        }

        // Filter by active status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Search functionality
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        // Sorting
        $sortField = $request->input('sort_field', 'code');
        $sortDirection = $request->input('sort_direction', 'asc');
        $query->orderBy($sortField, $sortDirection);

        // Pagination
        $perPage = $request->input('per_page', 10);
        $units = $query->paginate($perPage)->withQueryString();

        // Get data for filter dropdowns
        $schools = School::select('id', 'name')->orderBy('name')->get();
        $programs = Program::select('id', 'name', 'school_id')->orderBy('name')->get();
        $semesters = Semester::select('id', 'name')->orderBy('name')->get();

        return Inertia::render('Units/Index', [
            'units' => $units,
            'schools' => $schools,
            'programs' => $programs,
            'semesters' => $semesters,
            'filters' => $request->only([
                'search', 'school_id', 'program_id', 'semester_id', 
                'is_active', 'sort_field', 'sort_direction', 'per_page'
            ]),
            'can' => [
                'create' => Gate::allows('create', Unit::class),
            ],
        ]);
    }

    /**
     * Show the form for creating a new unit.
     *
     * @return \Inertia\Response
     */
    public function create()
    {
        $this->authorize('create', Unit::class); // Ensure the user has permission to create units

        $schools = School::select('id', 'name')->orderBy('name')->get();
        $programs = Program::select('id', 'name', 'school_id')->orderBy('name')->get();
        $semesters = Semester::select('id', 'name')->orderBy('name')->get();

        return Inertia::render('Units/Create', [
            'schools' => $schools,
            'programs' => $programs,
            'semesters' => $semesters,
        ]);
    }

    /**
     * Store a newly created unit in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:255|unique:units,code',
            'name' => 'required|string|max:255',
            'credit_hours' => 'required|integer|min:1|max:6',
        ]);

        Unit::create($validated);

        return redirect()->route('units.index')
            ->with('success', 'Unit created successfully.');
    }

    /**
     * Display the specified unit.
     *
     * @param  \App\Models\Unit  $unit
     * @return \Inertia\Response
     */
    public function show(Unit $unit)
    {
        $unit->load(['school', 'program', 'semester']);

        // Get enrollment count
        $enrollmentCount = $unit->enrollments()->count();

        // Get class timetables
        $classTimetables = $unit->classTimetables()
            ->with('semester')
            ->orderBy('day')
            ->orderBy('start_time')
            ->get();

        // Get exam timetables
        $examTimetables = $unit->examTimetables()
            ->with('semester')
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();

        return Inertia::render('Units/Show', [
            'unit' => $unit,
            'enrollmentCount' => $enrollmentCount,
            'classTimetables' => $classTimetables,
            'examTimetables' => $examTimetables,
            'can' => [
                'update' => Gate::allows('update', $unit),
                'delete' => Gate::allows('delete', $unit),
            ],
        ]);
    }

    /**
     * Show the form for editing the specified unit.
     *
     * @param  \App\Models\Unit  $unit
     * @return \Inertia\Response
     */
    public function edit(Unit $unit)
    {
        $schools = School::select('id', 'name')->orderBy('name')->get();
        $programs = Program::select('id', 'name', 'school_id')->orderBy('name')->get();
        $semesters = Semester::select('id', 'name')->orderBy('name')->get();

        return Inertia::render('Units/Edit', [
            'unit' => $unit,
            'schools' => $schools,
            'programs' => $programs,
            'semesters' => $semesters,
        ]);
    }

    /**
     * Update the specified unit in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Unit  $unit
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, Unit $unit)
    {
        $validated = $request->validate([
            'code' => [
                'required',
                'string',
                'max:255',
                Rule::unique('units')->ignore($unit->id),
            ],
            'name' => 'required|string|max:255',
            'program_id' => 'nullable|exists:programs,id',
            'school_id' => 'nullable|exists:schools,id',
            // Removed 'semester_id' validation
            'credit_hours' => 'required|integer|min:1|max:6', // Ensure credit_hours is between 1 and 6
            'is_active' => 'boolean',
        ]);

        $unit->update($validated);

        return redirect()->route('units.index')
            ->with('success', 'Unit updated successfully.');
    }

    /**
     * Remove the specified unit from storage.
     *
     * @param  \App\Models\Unit  $unit
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(Unit $unit)
    {
        // Check if the unit has any associated data
        if (
            $unit->enrollments()->exists() ||
            $unit->classTimetables()->exists() ||
            $unit->examTimetables()->exists()
        ) {
            return redirect()->route('units.index')
                ->with('error', 'Cannot delete unit because it has associated data.');
        }

        $unit->delete();

        return redirect()->route('units.index')
            ->with('success', 'Unit deleted successfully.');
    }

    /**
     * Toggle the active status of the specified unit.
     *
     * @param  \App\Models\Unit  $unit
     * @return \Illuminate\Http\RedirectResponse
     */
    public function toggleActive(Unit $unit)
    {
        $this->authorize('update', $unit);

        $unit->update(['is_active' => !$unit->is_active]);

        $status = $unit->is_active ? 'activated' : 'deactivated';

        return redirect()->route('units.index')
            ->with('success', 'Unit "' . $unit->code . '" has been ' . $status . '.');
    }

    /**
     * Assign a unit to a semester.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function assignToSemester(Request $request)
    {
        $validated = $request->validate([
            'unit_id' => 'required|exists:units,id',
            'semester_id' => 'required|exists:semesters,id',
        ]);

        $unit = Unit::findOrFail($validated['unit_id']);
        $unit->semesters()->attach($validated['semester_id']); // Assign the unit to the semester

        return redirect()->route('units.index')
            ->with('success', 'Unit assigned to semester successfully.');
    }

    /**
     * Get units by class and semester.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUnitsByClassAndSemester(Request $request)
    {
        $validated = $request->validate([
            'semester_id' => 'required|exists:semesters,id',
            'class_id' => 'required|exists:classes,id',
        ]);

        $units = Unit::whereHas('semesters', function ($query) use ($validated) {
            $query->where('semester_id', $validated['semester_id']);
        })->whereHas('classes', function ($query) use ($validated) {
            $query->where('class_id', $validated['class_id']);
        })->get();

        return response()->json($units);
    }
}