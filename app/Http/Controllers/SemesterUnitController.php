<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use App\Models\Semester;
use App\Models\School;
use App\Models\Program;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class SemesterUnitController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('can:manage-semester-units');
    }

    /**
     * Display a listing of units assigned to semesters.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Inertia\Response
     */
    public function index(Request $request)
    {
        $query = Unit::query()->with(['semester', 'program', 'school']);

        // Filter by semester
        if ($request->has('semester_id') && $request->input('semester_id')) {
            $query->where('semester_id', $request->input('semester_id'));
        }

        // Filter by program
        if ($request->has('program_id') && $request->input('program_id')) {
            $query->where('program_id', $request->input('program_id'));
        }

        // Filter by school
        if ($request->has('school_id') && $request->input('school_id')) {
            $query->where('school_id', $request->input('school_id'));
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
        $sortField = $request->input('sort_field', 'name');
        $sortDirection = $request->input('sort_direction', 'asc');
        $query->orderBy($sortField, $sortDirection);

        // Pagination
        $perPage = $request->input('per_page', 10);
        $units = $query->paginate($perPage)->withQueryString();

        // Get data for filter dropdowns
        $semesters = Semester::select('id', 'name')->orderBy('name')->get();
        $programs = Program::select('id', 'name')->orderBy('name')->get();
        $schools = School::select('id', 'name')->orderBy('name')->get();

        return Inertia::render('SemesterUnits/Index', [
            'units' => $units,
            'semesters' => $semesters,
            'programs' => $programs,
            'schools' => $schools,
            'filters' => $request->only([
                'search', 'semester_id', 'program_id', 'school_id', 
                'sort_field', 'sort_direction', 'per_page'
            ]),
            'can' => [
                'create' => Gate::allows('create', Unit::class),
                'assign' => Gate::allows('manage-semester-units'),
            ],
        ]);
    }

    /**
     * Show the form for assigning units to a semester.
     *
     * @return \Inertia\Response
     */
    public function create()
    {
        $semesters = Semester::select('id', 'name')->orderBy('name')->get();
        $units = Unit::where('semester_id', null)
            ->orWhere('is_active', false)
            ->select('id', 'code', 'name', 'program_id', 'school_id')
            ->with(['program:id,name', 'school:id,name'])
            ->orderBy('code')
            ->get();

        return Inertia::render('SemesterUnits/Create', [
            'semesters' => $semesters,
            'units' => $units,
        ]);
    }

    /**
     * Assign units to a semester.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'semester_id' => 'required|exists:semesters,id',
            'unit_ids' => 'required|array',
            'unit_ids.*' => 'exists:units,id',
        ]);

        // Update all selected units to be assigned to the semester
        Unit::whereIn('id', $validated['unit_ids'])->update([
            'semester_id' => $validated['semester_id'],
            'is_active' => true,
        ]);

        return redirect()->route('semester-units.index')
            ->with('success', count($validated['unit_ids']) . ' units assigned to semester successfully.');
    }

    /**
     * Remove a unit from a semester.
     *
     * @param  int  $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy($id)
    {
        $unit = Unit::findOrFail($id);
        
        // Check if there are any enrollments for this unit in this semester
        if ($unit->enrollments()->where('semester_id', $unit->semester_id)->exists()) {
            return redirect()->back()
                ->with('error', 'Cannot remove unit from semester because there are students enrolled.');
        }
        
        // Remove the unit from the semester
        $unit->update([
            'semester_id' => null,
            'is_active' => false,
        ]);
        
        return redirect()->back()
            ->with('success', 'Unit removed from semester successfully.');
    }

    /**
     * Bulk assign units to a semester.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function bulkAssign(Request $request)
    {
        $validated = $request->validate([
            'semester_id' => 'required|exists:semesters,id',
            'program_id' => 'required|exists:programs,id',
            'unit_ids' => 'required|array',
            'unit_ids.*' => 'exists:units,id',
        ]);

        // Update all selected units to be assigned to the semester
        Unit::whereIn('id', $validated['unit_ids'])
            ->where('program_id', $validated['program_id'])
            ->update([
                'semester_id' => $validated['semester_id'],
                'is_active' => true,
            ]);

        return redirect()->route('semester-units.index')
            ->with('success', count($validated['unit_ids']) . ' units assigned to semester successfully.');
    }
}