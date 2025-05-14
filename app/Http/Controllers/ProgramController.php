<?php

namespace App\Http\Controllers;

use App\Models\Program;
use App\Models\School;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class ProgramController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
        $this->authorizeResource(Program::class, 'program');
    }

    /**
     * Display a listing of the programs.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Inertia\Response
     */
    public function index(Request $request)
    {
        $query = Program::query()->with('school');

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
        $programs = $query->paginate($perPage)->withQueryString();

        // Get all schools for the filter dropdown
        $schools = School::select('id', 'name')->orderBy('name')->get();

        return Inertia::render('Programs/Index', [
            'programs' => $programs,
            'schools' => $schools,
            'filters' => $request->only(['search', 'school_id', 'sort_field', 'sort_direction', 'per_page']),
            'can' => [
                'create' => Gate::allows('create', Program::class),
            ],
        ]);
    }

    /**
     * Show the form for creating a new program.
     *
     * @return \Inertia\Response
     */
    public function create()
    {
        $schools = School::select('id', 'name')->orderBy('name')->get();

        return Inertia::render('Programs/Create', [
            'schools' => $schools,
        ]);
    }

    /**
     * Store a newly created program in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:255|unique:programs,code',
            'name' => 'required|string|max:255',
            'school_id' => 'required|exists:schools,id',
        ]);

        Program::create($validated);

        return redirect()->route('programs.index')
            ->with('success', 'Program created successfully.');
    }

    /**
     * Display the specified program.
     *
     * @param  \App\Models\Program  $program
     * @return \Inertia\Response
     */
    public function show(Program $program)
    {
        $program->load(['school', 'programGroups', 'units' => function ($query) {
            $query->withCount('enrollments');
        }]);

        // Get student count for this program
        $studentCount = $program->enrollments()
            ->select('student_code')
            ->distinct()
            ->count();

        return Inertia::render('Programs/Show', [
            'program' => $program,
            'studentCount' => $studentCount,
            'can' => [
                'update' => Gate::allows('update', $program),
                'delete' => Gate::allows('delete', $program),
            ],
        ]);
    }

    /**
     * Show the form for editing the specified program.
     *
     * @param  \App\Models\Program  $program
     * @return \Inertia\Response
     */
    public function edit(Program $program)
    {
        $schools = School::select('id', 'name')->orderBy('name')->get();

        return Inertia::render('Programs/Edit', [
            'program' => $program,
            'schools' => $schools,
        ]);
    }

    /**
     * Update the specified program in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Program  $program
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, Program $program)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:255|unique:programs,code,' . $program->id,
            'name' => 'required|string|max:255',
            'school_id' => 'required|exists:schools,id',
        ]);

        $program->update($validated);

        return redirect()->route('programs.index')
            ->with('success', 'Program updated successfully.');
    }

    /**
     * Remove the specified program from storage.
     *
     * @param  \App\Models\Program  $program
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(Program $program)
    {
        // Check if the program has any units or enrollments
        if ($program->units()->exists() || $program->enrollments()->exists()) {
            return redirect()->route('programs.index')
                ->with('error', 'Cannot delete program because it has associated units or enrollments.');
        }

        $program->delete();

        return redirect()->route('programs.index')
            ->with('success', 'Program deleted successfully.');
    }

    /**
     * Display the program dashboard with statistics.
     *
     * @param  \App\Models\Program  $program
     * @return \Inertia\Response
     */
    public function dashboard(Program $program)
    {
        $program->load('school');
        
        $unitCount = $program->units()->count();
        $studentCount = $program->enrollments()
            ->select('student_code')
            ->distinct()
            ->count();
            
        $groupStats = $program->programGroups()
            ->withCount(['enrollments as student_count' => function ($query) {
                $query->select(\DB::raw('count(distinct student_code)'));
            }])
            ->get();

        return Inertia::render('Programs/Dashboard', [
            'program' => $program,
            'stats' => [
                'unitCount' => $unitCount,
                'studentCount' => $studentCount,
            ],
            'groups' => $groupStats,
        ]);
    }
}