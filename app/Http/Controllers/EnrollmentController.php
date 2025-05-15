<?php

namespace App\Http\Controllers;

use App\Models\Enrollment;
use App\Models\Unit;
use App\Models\Semester;
use App\Models\Program;
use App\Models\School;
use App\Models\User;
use App\Services\EnrollmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class EnrollmentController extends Controller
{
    protected $enrollmentService;

    /**
     * Create a new controller instance.
     *
     * @param  \App\Services\EnrollmentService  $enrollmentService
     * @return void
     */
    public function __construct(EnrollmentService $enrollmentService)
    {
        $this->middleware('auth');
        $this->authorizeResource(Enrollment::class, 'enrollment');
        $this->enrollmentService = $enrollmentService;
    }

    /**
     * Display a listing of the enrollments.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Inertia\Response
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Enrollment::class); // Ensure the user has permission to view enrollments

        $query = Enrollment::query()->with(['unit', 'semester']);

        // Filter by student
        if ($request->has('student_code') && $request->input('student_code')) {
            $query->where('student_code', $request->input('student_code'));
        }

        // Filter by lecturer
        if ($request->has('lecturer_code') && $request->input('lecturer_code')) {
            $query->where('lecturer_code', $request->input('lecturer_code'));
        }

        // Filter by unit
        if ($request->has('unit_id') && $request->input('unit_id')) {
            $query->where('unit_id', $request->input('unit_id'));
        }

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

        // Filter by group
        if ($request->has('group') && $request->input('group')) {
            $query->where('group', $request->input('group'));
        }

        // Sorting
        $sortField = $request->input('sort_field', 'created_at');
        $sortDirection = $request->input('sort_direction', 'desc');
        $query->orderBy($sortField, $sortDirection);

        // Pagination
        $perPage = $request->input('per_page', 10);
        $enrollments = $query->paginate($perPage)->withQueryString();

        // Get data for filter dropdowns
        $units = Unit::select('id', 'code', 'name')->orderBy('code')->get();
        $semesters = Semester::select('id', 'name')->orderBy('name')->get();
        $programs = Program::select('id', 'code', 'name')->orderBy('name')->get();
        $schools = School::select('id', 'code', 'name')->orderBy('name')->get();
        
        // Get unique groups
        $groups = Enrollment::select('group')
            ->whereNotNull('group')
            ->distinct()
            ->pluck('group')
            ->sort()
            ->values();

        return Inertia::render('Enrollments/Index', [
            'enrollments' => $enrollments,
            'units' => $units,
            'semesters' => $semesters,
            'programs' => $programs,
            'schools' => $schools,
            'groups' => $groups,
            'filters' => $request->only([
                'student_code', 'lecturer_code', 'unit_id', 'semester_id', 
                'program_id', 'school_id', 'group', 'sort_field', 
                'sort_direction', 'per_page'
            ]),
            'can' => [
                'create' => Gate::allows('create', Enrollment::class),
            ],
        ]);
    }

    /**
     * Display the form for enrolling in units.
     *
     * @return \Inertia\Response
     */
    public function create()
    {
        $this->authorize('create', Enrollment::class); // Ensure the user has permission to create enrollments

        $units = Unit::where('is_active', true)
            ->select('id', 'code', 'name')
            ->orderBy('code')
            ->get();

        $semesters = Semester::select('id', 'name')->orderBy('name')->get();

        $activeSemester = Semester::where('is_active', true)->first();

        return Inertia::render('Enrollments/Enroll', [
            'units' => $units,
            'semesters' => $semesters,
            'activeSemester' => $activeSemester,
        ]);
    }

    /**
     * Store a newly created enrollment in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'student_code' => 'required|string|exists:users,code',
            'lecturer_code' => 'nullable|string|exists:users,code',
            'unit_id' => 'required|exists:units,id',
            'semester_id' => 'required|exists:semesters,id',
        ]);

        // Check if enrollment already exists
        $exists = Enrollment::where('student_code', $validated['student_code'])
            ->where('unit_id', $validated['unit_id'])
            ->where('semester_id', $validated['semester_id'])
            ->exists();
            
        if ($exists) {
            return redirect()->back()
                ->withErrors(['student_code' => 'This student is already enrolled in this unit for the selected semester.']);
        }

        // Get student's program and school
        $student = User::where('code', $validated['student_code'])->first();
        $programId = $student->program_id;
        $schoolId = $student->school_id;

        try {
            // Use the enrollment service to handle group assignment
            $enrollment = $this->enrollmentService->enrollStudent(
                $validated['student_code'],
                $validated['unit_id'],
                $validated['semester_id']
            );
            
            // Update lecturer code if provided
            if (!empty($validated['lecturer_code'])) {
                $enrollment->update(['lecturer_code' => $validated['lecturer_code']]);
            }

            return redirect()->route('enrollments.index')
                ->with('success', 'Student enrolled successfully in group ' . $enrollment->group);
        } catch (\Exception $e) {
            return redirect()->back()
                ->withErrors(['error' => 'Failed to enroll student: ' . $e->getMessage()]);
        }
    }

    /**
     * Display the specified enrollment.
     *
     * @param  \App\Models\Enrollment  $enrollment
     * @return \Inertia\Response
     */
    public function show(Enrollment $enrollment)
    {
        $enrollment->load(['unit', 'semester', 'program', 'school']);
        
        // Get student details
        $student = User::where('code', $enrollment->student_code)
            ->select('id', 'code', 'name', 'email')
            ->first();
            
        // Get lecturer details
        $lecturer = null;
        if ($enrollment->lecturer_code) {
            $lecturer = User::where('code', $enrollment->lecturer_code)
                ->select('id', 'code', 'name', 'email')
                ->first();
        }

        return Inertia::render('Enrollments/Show', [
            'enrollment' => $enrollment,
            'student' => $student,
            'lecturer' => $lecturer,
            'can' => [
                'update' => Gate::allows('update', $enrollment),
                'delete' => Gate::allows('delete', $enrollment),
            ],
        ]);
    }

    /**
     * Show the form for editing the specified enrollment.
     *
     * @param  \App\Models\Enrollment  $enrollment
     * @return \Inertia\Response
     */
    public function edit(Enrollment $enrollment)
    {
        $enrollment->load(['unit', 'semester']);
        
        // Get student details
        $student = User::where('code', $enrollment->student_code)
            ->select('id', 'code', 'name', 'email')
            ->first();
            
        // Get lecturers
        $lecturers = User::where('role', 'lecturer')
            ->select('id', 'code', 'name', 'email')
            ->orderBy('name')
            ->get();
            
        // Get groups for the program
        $programGroups = [];
        if ($enrollment->program_id) {
            $programGroups = \App\Models\ProgramGroup::where('program_id', $enrollment->program_id)
                ->where('is_active', true)
                ->orderBy('group')
                ->get();
        }

        return Inertia::render('Enrollments/Edit', [
            'enrollment' => $enrollment,
            'student' => $student,
            'lecturers' => $lecturers,
            'programGroups' => $programGroups,
        ]);
    }

    /**
     * Update the specified enrollment in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Enrollment  $enrollment
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, Enrollment $enrollment)
    {
        $validated = $request->validate([
            'lecturer_code' => 'nullable|string|exists:users,code',
            'group' => 'nullable|string|size:1|regex:/^[A-Z]$/',
        ]);

        // If group is changing, update the program group counts
        if (isset($validated['group']) && $validated['group'] !== $enrollment->group) {
            // Decrement old group count if it exists
            if ($enrollment->group && $enrollment->program_id) {
                $oldGroup = \App\Models\ProgramGroup::where('program_id', $enrollment->program_id)
                    ->where('group', $enrollment->group)
                    ->first();
                    
                if ($oldGroup && $oldGroup->current_count > 0) {
                    $oldGroup->decrement('current_count');
                }
            }
            
            // Increment new group count
            if ($validated['group'] && $enrollment->program_id) {
                $newGroup = \App\Models\ProgramGroup::firstOrCreate(
                    [
                        'program_id' => $enrollment->program_id,
                        'group' => $validated['group'],
                    ],
                    [
                        'capacity' => 60,
                        'current_count' => 0,
                        'is_active' => true,
                    ]
                );
                
                $newGroup->increment('current_count');
            }
        }

        $enrollment->update($validated);

        return redirect()->route('enrollments.index')
            ->with('success', 'Enrollment updated successfully.');
    }

    /**
     * Remove the specified enrollment from storage.
     *
     * @param  \App\Models\Enrollment  $enrollment
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(Enrollment $enrollment)
    {
        // Decrement group count if applicable
        if ($enrollment->group && $enrollment->program_id) {
            $group = \App\Models\ProgramGroup::where('program_id', $enrollment->program_id)
                ->where('group', $enrollment->group)
                ->first();
                
            if ($group && $group->current_count > 0) {
                $group->decrement('current_count');
            }
        }

        $enrollment->delete();

        return redirect()->route('enrollments.index')
            ->with('success', 'Enrollment deleted successfully.');
    }

    /**
     * Bulk enroll students in a unit.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function bulkEnroll(Request $request)
    {
        $this->authorize('create', Enrollment::class);

        $validated = $request->validate([
            'student_codes' => 'required|array',
            'student_codes.*' => 'required|string|exists:users,code',
            'unit_id' => 'required|exists:units,id',
            'semester_id' => 'required|exists:semesters,id',
            'lecturer_code' => 'nullable|string|exists:users,code',
        ]);

        $successCount = 0;
        $failCount = 0;
        $errors = [];

        foreach ($validated['student_codes'] as $studentCode) {
            // Check if enrollment already exists
            $exists = Enrollment::where('student_code', $studentCode)
                ->where('unit_id', $validated['unit_id'])
                ->where('semester_id', $validated['semester_id'])
                ->exists();
                
            if ($exists) {
                $failCount++;
                $errors[] = "Student {$studentCode} is already enrolled in this unit.";
                continue;
            }

            try {
                // Use the enrollment service to handle group assignment
                $enrollment = $this->enrollmentService->enrollStudent(
                    $studentCode,
                    $validated['unit_id'],
                    $validated['semester_id']
                );
                
                // Update lecturer code if provided
                if (!empty($validated['lecturer_code'])) {
                    $enrollment->update(['lecturer_code' => $validated['lecturer_code']]);
                }
                
                $successCount++;
            } catch (\Exception $e) {
                $failCount++;
                $errors[] = "Failed to enroll student {$studentCode}: " . $e->getMessage();
            }
        }

        $message = "{$successCount} students enrolled successfully.";
        if ($failCount > 0) {
            $message .= " {$failCount} enrollments failed.";
        }

        return redirect()->route('enrollments.index')
            ->with('success', $message)
            ->with('errors', $errors);
    }

    /**
     * Display enrollments for the current student.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Inertia\Response
     */
    public function myEnrollments(Request $request)
    {
        $user = auth()->user();
        
        // Ensure the user is a student
        if ($user->role !== 'student') {
            abort(403, 'Only students can access this page.');
        }

        $query = Enrollment::where('student_code', $user->code)
            ->with(['unit', 'semester']);

        // Filter by semester
        if ($request->has('semester_id') && $request->input('semester_id')) {
            $query->where('semester_id', $request->input('semester_id'));
        } else {
            // Default to active semester
            $activeSemester = Semester::where('is_active', true)->first();
            if ($activeSemester) {
                $query->where('semester_id', $activeSemester->id);
            }
        }

        // Sorting
        $sortField = $request->input('sort_field', 'created_at');
        $sortDirection = $request->input('sort_direction', 'desc');
        $query->orderBy($sortField, $sortDirection);

        $enrollments = $query->get();
        
        // Get all semesters for the filter dropdown
        $semesters = Semester::select('id', 'name')->orderBy('name')->get();
        
        // Get active semester
        $activeSemester = Semester::where('is_active', true)->first();

        return Inertia::render('Student/Enrollments', [
            'enrollments' => $enrollments,
            'semesters' => $semesters,
            'activeSemester' => $activeSemester,
            'selectedSemester' => $request->input('semester_id', $activeSemester ? $activeSemester->id : null),
        ]);
    }
}