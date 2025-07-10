<?php

namespace App\Http\Controllers;

use App\Models\Enrollment;
use App\Models\Group;
use App\Models\Semester;
use App\Models\Unit;
use App\Models\ClassModel;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;

class EnrollmentController extends Controller
{
    public function adminStore(Request $request)
    {
        // CRITICAL DEBUG: Log everything at the start
        Log::info('=== ADMIN STORE CALLED ===', [
            'method' => $request->method(),
            'url' => $request->url(),
            'route_name' => $request->route() ? $request->route()->getName() : 'no_route',
            'raw_input' => $request->all(),
            'auth_user_code' => Auth::user()->code,
            'is_admin_store' => true,
            'request_has_student_code' => $request->has('student_code'),
            'student_code_value' => $request->get('student_code'),
            'all_keys' => array_keys($request->all())
        ]);

        // Check what field names are actually being sent
        $allData = $request->all();
        Log::info('All request data keys and values:', $allData);

        // Check for both possible field names
        $studentCode = null;
        if ($request->has('student_code')) {
            $studentCode = $request->get('student_code');
            Log::info('Found student_code field:', ['value' => $studentCode]);
        } elseif ($request->has('code')) {
            $studentCode = $request->get('code');
            Log::info('Found code field (legacy):', ['value' => $studentCode]);
        }

        if (!$studentCode || trim($studentCode) === '') {
            Log::error('CRITICAL: No student code found in request', [
                'available_fields' => array_keys($request->all()),
                'student_code_exists' => $request->has('student_code'),
                'code_exists' => $request->has('code'),
                'student_code_value' => $request->get('student_code'),
                'code_value' => $request->get('code')
            ]);
            return redirect()->back()->withErrors([
                'student_code' => 'Student code is required. Debug: Field not found in request data.'
            ])->withInput();
        }

        // Flexible validation - accept either field name
        $validationRules = [
            'group_id' => 'required|exists:groups,id',
            'unit_ids' => 'required|array|min:1',
            'unit_ids.*' => 'exists:units,id',
            'semester_id' => 'required|exists:semesters,id',
        ];

        // Add student code validation for whichever field is present
        if ($request->has('student_code')) {
            $validationRules['student_code'] = 'required|string|exists:users,code';
        } elseif ($request->has('code')) {
            $validationRules['code'] = 'required|string|exists:users,code';
        }

        try {
            $validated = $request->validate($validationRules);
            Log::info('Validation passed:', $validated);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validation failed:', [
                'errors' => $e->errors(),
                'request_data' => $request->all()
            ]);
            throw $e;
        }

        // Use the student code we found
        $targetStudentCode = $studentCode;
        $adminCode = Auth::user()->code;

        Log::info('ADMIN ENROLLMENT - Codes confirmed', [
            'target_student' => $targetStudentCode,
            'admin_performing_action' => $adminCode,
            'these_should_be_different' => $targetStudentCode !== $adminCode
        ]);

        // Verify the student exists
        $targetStudent = User::where('code', $targetStudentCode)->first();
        if (!$targetStudent) {
            Log::error('Target student not found', ['student_code' => $targetStudentCode]);
            return redirect()->back()->withErrors([
                'student_code' => "Student with code '{$targetStudentCode}' not found."
            ])->withInput();
        }

        Log::info('Target student found:', [
            'student_id' => $targetStudent->id,
            'student_name' => $targetStudent->first_name . ' ' . $targetStudent->last_name,
            'student_code' => $targetStudent->code
        ]);

        // CAPACITY CHECK - Get the group and verify capacity
        $group = Group::findOrFail($request->get('group_id') ?? $validated['group_id']);

        // Check current enrollment count for this group
        $currentEnrollmentCount = Enrollment::where('group_id', $group->id)
            ->distinct('student_code')
            ->count('student_code');

        Log::info('Group capacity check', [
            'group_id' => $group->id,
            'group_name' => $group->name ?? 'N/A',
            'group_capacity' => $group->capacity,
            'current_enrollment_count' => $currentEnrollmentCount,
            'target_student' => $targetStudentCode
        ]);

        // Check if adding this student would exceed capacity
        $studentAlreadyInGroup = Enrollment::where('group_id', $group->id)
            ->where('student_code', $targetStudentCode)
            ->exists();

        if (!$studentAlreadyInGroup && $currentEnrollmentCount >= $group->capacity) {
            Log::warning('Group capacity exceeded - Admin enrollment blocked', [
                'group_id' => $group->id,
                'group_name' => $group->name ?? 'N/A',
                'capacity' => $group->capacity,
                'current_count' => $currentEnrollmentCount,
                'attempted_student' => $targetStudentCode,
                'admin_user' => $adminCode
            ]);
            return redirect()->back()->withErrors([
                'group_id' => "This group is already full. Capacity: {$group->capacity}, Current enrollments: {$currentEnrollmentCount}. Cannot enroll student {$targetStudentCode}.",
            ])->withInput();
        }

        try {
            $enrollmentCount = 0;
            $unitIds = $request->has('unit_ids') ? $request->get('unit_ids') :
                       ($request->has('unit_ids') ? $validated['unit_ids'] : []);

            foreach ($unitIds as $unitId) {
                $unit = Unit::with(['program', 'school'])->find($unitId);
                if (!$unit) {
                    Log::warning('Unit not found:', ['unit_id' => $unitId]);
                    continue;
                }

                // Check if enrollment already exists
                $existingEnrollment = Enrollment::where([
                    'student_code' => $targetStudentCode,
                    'unit_id' => $unitId,
                    'semester_id' => $request->get('semester_id') ?? $validated['semester_id']
                ])->first();

                if ($existingEnrollment) {
                    Log::info('Enrollment already exists, skipping:', [
                        'student_code' => $targetStudentCode,
                        'unit_id' => $unitId
                    ]);
                    continue;
                }

                $enrollment = Enrollment::create([
                    'student_code' => $targetStudentCode, // CRITICAL: Use target student
                    'group_id' => $request->get('group_id') ?? $validated['group_id'],
                    'unit_id' => $unitId,
                    'semester_id' => $request->get('semester_id') ?? $validated['semester_id'],
                    'program_id' => $unit->program_id ?? null,
                    'school_id' => $unit->school_id ?? null,
                ]);

                $enrollmentCount++;

                Log::info('Enrollment created successfully', [
                    'enrollment_id' => $enrollment->id,
                    'stored_student_code' => $enrollment->student_code,
                    'target_was' => $targetStudentCode,
                    'admin_was' => $adminCode,
                    'unit_name' => $unit->name,
                    'group_capacity_after' => $currentEnrollmentCount + 1
                ]);
            }

            if ($enrollmentCount === 0) {
                return redirect()->back()->withErrors([
                    'error' => 'No new enrollments were created. Student may already be enrolled in selected units.'
                ])->withInput();
            }

            return redirect()->route('enrollments.index')->with('success', 
                "Successfully enrolled {$targetStudentCode} in {$enrollmentCount} unit(s)!"
            );

        } catch (\Exception $e) {
            Log::error('Enrollment creation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'target_student' => $targetStudentCode,
                'admin' => $adminCode
            ]);

            return redirect()->back()->withErrors([
                'error' => 'Failed to enroll student: ' . $e->getMessage()
            ])->withInput();
        }
    }

    // Add a debug route to check what's being sent
    public function debugEnrollmentRequest(Request $request)
    {
        Log::info('=== DEBUG ENROLLMENT REQUEST ===', [
            'method' => $request->method(),
            'url' => $request->url(),
            'all_data' => $request->all(),
            'headers' => $request->headers->all(),
            'content_type' => $request->header('Content-Type'),
            'raw_content' => $request->getContent()
        ]);

        return response()->json([
            'received_data' => $request->all(),
            'has_student_code' => $request->has('student_code'),
            'has_code' => $request->has('code'),
            'student_code_value' => $request->get('student_code'),
            'code_value' => $request->get('code')
        ]);
    }

    // STUDENT: Only enroll (not manage)
    public function showEnrollmentForm()
    {
        $student = Auth::user();
        $enrollments = Enrollment::with(['unit', 'group', 'group.class'])
            ->where('student_code', $student->code)
            ->get();

        $semesters = Semester::all();
        $classes = ClassModel::with('semester')->get();
        $groups = Group::with('class')->get();
        $units = Unit::with(['program', 'school'])->get();

        return inertia('Student/Enroll', [
            'student' => $student,
            'enrollments' => $enrollments,
            'semesters' => $semesters,
            'classes' => $classes,
            'groups' => $groups,
            'units' => $units,
        ]);
    }

    // STUDENT: Only enroll (not manage)
    public function store(Request $request)
    {
        // For self-enrollment, use the authenticated user's code
        $studentCode = Auth::user()->code;

        $validated = $request->validate([
            'group_id' => 'required|exists:groups,id',
            'unit_ids' => 'required|array',
            'unit_ids.*' => 'exists:units,id',
            'semester_id' => 'required|exists:semesters,id',
        ]);

        $group = Group::findOrFail($validated['group_id']);
        $class = $group->class;

        if (!$class) {
            return redirect()->back()->withErrors([
                'group_id' => 'Selected group does not belong to a valid class.'
            ]);
        }

        // CAPACITY CHECK FOR STUDENT ENROLLMENT
        $currentEnrollmentCount = Enrollment::where('group_id', $group->id)
            ->distinct('student_code')
            ->count('student_code');

        if ($currentEnrollmentCount >= $group->capacity) {
            Log::info('Student enrollment blocked - group full', [
                'student_code' => $studentCode,
                'group_id' => $group->id,
                'capacity' => $group->capacity,
                'current_count' => $currentEnrollmentCount
            ]);

            return redirect()->back()->withErrors([
                'group_id' => "This group is already full. Capacity: {$group->capacity}",
            ]);
        }

        $existingEnrollments = Enrollment::where('group_id', $group->id)
            ->where('student_code', $studentCode)
            ->where('semester_id', $validated['semester_id'])
            ->pluck('unit_id')
            ->toArray();

        if (!empty($existingEnrollments)) {
            $conflictingUnits = array_intersect($validated['unit_ids'], $existingEnrollments);
            if (!empty($conflictingUnits)) {
                $unitNames = Unit::whereIn('id', $conflictingUnits)->pluck('name')->toArray();
                return redirect()->back()->withErrors([
                    'unit_ids' => 'You are already enrolled in: ' . implode(', ', $unitNames),
                ]);
            }
        }

        try {
            foreach ($validated['unit_ids'] as $unitId) {
                $unit = Unit::with(['program', 'school'])->find($unitId);

                Enrollment::create([
                    'student_code' => $studentCode,
                    'group_id' => $group->id,
                    'unit_id' => $unitId,
                    'semester_id' => $validated['semester_id'],
                    'program_id' => $unit->program_id ?? $class->program_id ?? null,
                    'school_id' => $unit->school_id ?? $class->school_id ?? null,
                ]);
            }

            return redirect()->back()->with('success', 
                'Successfully enrolled in ' . count($validated['unit_ids']) . ' unit(s)!'
            );

        } catch (\Exception $e) {
            Log::error('Error creating enrollment: ' . $e->getMessage());
            return redirect()->back()->withErrors([
                'error' => 'Failed to enroll student. Please try again.'
            ]);
        }
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            // Get enrollments with relationships
            $enrollments = Enrollment::with(['student', 'unit', 'group.class'])
                ->orderBy('created_at', 'desc')
                ->paginate(15);

            // Get lecturer assignments - FIXED: Proper query to get unique lecturer-unit assignments
            $lecturerPerPage = $request->input('lecturer_per_page', 15);
            $lecturerAssignments = DB::table('enrollments')
                ->select([
                    'enrollments.unit_id',
                    'units.name as unit_name',
                    'units.code as unit_code',
                    'enrollments.lecturer_code',
                    'users.first_name',
                    'users.last_name',
                    DB::raw("CONCAT(users.first_name, ' ', users.last_name) as lecturer_name")
                ])
                ->join('units', 'enrollments.unit_id', '=', 'units.id')
                ->leftJoin('users', 'enrollments.lecturer_code', '=', 'users.code')
                ->whereNotNull('enrollments.lecturer_code')
                ->where('enrollments.lecturer_code', '!=', '')
                ->groupBy([
                    'enrollments.unit_id', 'units.name', 'units.code',
                    'enrollments.lecturer_code', 'users.first_name', 'users.last_name'
                ])
                ->orderBy('units.name')
                ->paginate($lecturerPerPage, ['*'], 'lecturer_page');

            Log::info('Lecturer assignments query result:', [
                'count' => $lecturerAssignments->count(),
                'total' => $lecturerAssignments->total(),
                'data' => $lecturerAssignments->items()
            ]);

            // Get other required data
            $semesters = Semester::orderBy('name')->get();
            $groups = Group::with('class')->orderBy('name')->get();
            $classes = ClassModel::orderBy('name')->get();
            $units = Unit::orderBy('name')->get();

            return Inertia::render('Enrollments/Index', [
                'enrollments' => $enrollments,
                'lecturerAssignments' => [
                    'data' => $lecturerAssignments->items(),
                    'links' => $lecturerAssignments->linkCollection()->toArray()['data'] ?? [],
                    'total' => $lecturerAssignments->total(),
                    'current_page' => $lecturerAssignments->currentPage(),
                    'per_page' => $lecturerAssignments->perPage()
                ],
                'semesters' => $semesters,
                'groups' => $groups,
                'classes' => $classes,
                'units' => $units,
            ]);

        } catch (\Exception $e) {
            Log::error('Error in enrollments index:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return Inertia::render('Enrollments/Index', [
                'enrollments' => null,
                'lecturerAssignments' => ['data' => [], 'links' => []],
                'semesters' => [],
                'groups' => [],
                'classes' => [],
                'units' => [],
                'errors' => ['error' => 'Failed to load enrollments data.']
            ]);
        }
    }

    // FIXED: Method to assign units to lecturers
    public function assignUnitToLecturer(Request $request)
    {
        Log::info('=== ASSIGN UNIT TO LECTURER ===', [
            'request_data' => $request->all(),
            'admin_user' => Auth::user()->code
        ]);

        $validated = $request->validate([
            'unit_id' => 'required|exists:units,id',
            'lecturer_code' => 'required|string|exists:users,code',
        ]);

        try {
            // Update all enrollments for this unit to have the lecturer
            $updated = Enrollment::where('unit_id', $validated['unit_id'])
                ->update(['lecturer_code' => $validated['lecturer_code']]);

            Log::info('Unit assigned to lecturer', [
                'unit_id' => $validated['unit_id'],
                'lecturer_code' => $validated['lecturer_code'],
                'enrollments_updated' => $updated
            ]);

            return redirect()->route('enrollments.index')->with('success', 
                "Unit successfully assigned to lecturer {$validated['lecturer_code']}!"
            );

        } catch (\Exception $e) {
            Log::error('Failed to assign unit to lecturer', [
                'error' => $e->getMessage(),
                'unit_id' => $validated['unit_id'],
                'lecturer_code' => $validated['lecturer_code']
            ]);

            return redirect()->back()->withErrors([
                'error' => 'Failed to assign unit to lecturer: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        // Get all students (with role Student if your User model has roles)
        $students = User::where('role', 'Student')->orWhere('role_id', function($query) {
            $query->select('id')->from('roles')->where('name', 'Student');
        })->get();

        // If no students found with explicit role, get all users
        if ($students->isEmpty()) {
            $students = User::all();
            Log::info('No students found with role Student, using all users');
        }

        $semesters = Semester::all();
        $classes = ClassModel::all();
        $groups = Group::with('class')->get();
        $units = Unit::all();

        // Debug information
        Log::info('Admin enrollment create form data', [
            'students_count' => $students->count(),
            'semesters_count' => $semesters->count(),
            'classes_count' => $classes->count(),
            'groups_count' => $groups->count(),
            'units_count' => $units->count()
        ]);

        return inertia('Enrollments/Create', [
            'students' => $students,
            'semesters' => $semesters,
            'classes' => $classes,
            'groups' => $groups,
            'units' => $units
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Enrollment $enrollment)
    {
        $enrollment->load(['student', 'unit', 'group', 'semester']);

        $students = User::where('role', 'Student')->orWhere('role_id', function($query) {
            $query->select('id')->from('roles')->where('name', 'Student');
        })->get();

        // If no students found with explicit role, get all users
        if ($students->isEmpty()) {
            $students = User::all();
        }

        $semesters = Semester::all();
        $classes = ClassModel::all();
        $groups = Group::with('class')->get();
        $units = Unit::all();

        return inertia('Enrollments/Edit', [
            'enrollment' => $enrollment,
            'students' => $students,
            'semesters' => $semesters,
            'classes' => $classes,
            'groups' => $groups,
            'units' => $units
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Enrollment $enrollment)
    {
        $validated = $request->validate([
            'group_id' => 'required|exists:groups,id',
            'unit_id' => 'required|exists:units,id',
            'semester_id' => 'required|exists:semesters,id',
            'student_code' => 'required|exists:users,code',
        ]);

        // CAPACITY CHECK FOR UPDATE
        $group = Group::findOrFail($validated['group_id']);

        // Only check capacity if we're changing to a different group
        if ($enrollment->group_id != $validated['group_id']) {
            $currentEnrollmentCount = Enrollment::where('group_id', $group->id)
                ->distinct('student_code')
                ->count('student_code');

            // Check if the student is already in the new group
            $studentAlreadyInNewGroup = Enrollment::where('group_id', $group->id)
                ->where('student_code', $validated['student_code'])
                ->where('id', '!=', $enrollment->id) // Exclude current enrollment
                ->exists();

            if (!$studentAlreadyInNewGroup && $currentEnrollmentCount >= $group->capacity) {
                Log::warning('Group capacity exceeded during update', [
                    'enrollment_id' => $enrollment->id,
                    'old_group_id' => $enrollment->group_id,
                    'new_group_id' => $group->id,
                    'capacity' => $group->capacity,
                    'current_count' => $currentEnrollmentCount,
                    'student_code' => $validated['student_code']
                ]);

                return redirect()->back()->withErrors([
                    'group_id' => "Cannot move to this group - it's already full. Capacity: {$group->capacity}, Current: {$currentEnrollmentCount}",
                ])->withInput();
            }
        }

        Log::info('Updating enrollment', [
            'enrollment_id' => $enrollment->id,
            'old_student_code' => $enrollment->student_code,
            'new_student_code' => $validated['student_code'],
            'old_group_id' => $enrollment->group_id,
            'new_group_id' => $validated['group_id'],
            'updated_by' => Auth::user()->code
        ]);

        $enrollment->update($validated);

        return redirect()->route('enrollments.index')->with('success', 'Enrollment updated.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Enrollment $enrollment)
    {
        Log::info('Deleting enrollment', [
            'enrollment_id' => $enrollment->id,
            'student_code' => $enrollment->student_code,
            'group_id' => $enrollment->group_id,
            'deleted_by' => Auth::user()->code
        ]);

        $enrollment->delete();

        return redirect()->route('enrollments.index')->with('success', 'Enrollment deleted.');
    }

    public function bulkDelete(Request $request)
    {
        $ids = $request->input('enrollment_ids', []);

        Log::info('Bulk deleting enrollments', [
            'enrollment_ids' => $ids,
            'count' => count($ids),
            'deleted_by' => Auth::user()->code
        ]);

        \App\Models\Enrollment::whereIn('id', $ids)->delete();

        return back()->with('success', 'Selected enrollments deleted.');
    }

    // --- FACULTY ADMIN METHODS ---

    /**
     * Show enrollments for faculty admin's school
     */
    public function facultyEnrollments(Request $request)
    {
        try {
            // Get the current school code from the request (set by middleware)
            $schoolCode = $request->get('current_school_code');
            
            if (!$schoolCode) {
                // Fallback: get from user's role
                $user = auth()->user();
                $roles = $user->getRoleNames();
                foreach ($roles as $role) {
                    if (str_starts_with($role, 'Faculty Admin - ')) {
                        $schoolCode = str_replace('Faculty Admin - ', '', $role);
                        break;
                    }
                }
            }

            Log::info('Faculty enrollments accessed', [
                'user_id' => auth()->id(),
                'school_code' => $schoolCode,
                'user_roles' => auth()->user()->getRoleNames()->toArray()
            ]);

            // Get enrollments for units belonging to this school
            $enrollments = Enrollment::with(['unit.program.school', 'group.class'])
                ->whereHas('unit.program.school', function($query) use ($schoolCode) {
                    $query->where('code', $schoolCode);
                })
                ->orderBy('created_at', 'desc')
                ->paginate(15);

            // Get lecturer assignments for this school
            $lecturerPerPage = $request->input('lecturer_per_page', 15);
            $lecturerAssignments = DB::table('enrollments')
                ->select([
                    'enrollments.unit_id',
                    'units.name as unit_name',
                    'units.code as unit_code',
                    'enrollments.lecturer_code',
                    'users.first_name',
                    'users.last_name',
                    DB::raw("CONCAT(users.first_name, ' ', users.last_name) as lecturer_name")
                ])
                ->join('units', 'enrollments.unit_id', '=', 'units.id')
                ->join('programs', 'units.program_id', '=', 'programs.id')
                ->join('schools', 'programs.school_id', '=', 'schools.id')
                ->leftJoin('users', 'enrollments.lecturer_code', '=', 'users.code')
                ->where('schools.code', $schoolCode)
                ->whereNotNull('enrollments.lecturer_code')
                ->where('enrollments.lecturer_code', '!=', '')
                ->groupBy([
                    'enrollments.unit_id', 'units.name', 'units.code',
                    'enrollments.lecturer_code', 'users.first_name', 'users.last_name'
                ])
                ->orderBy('units.name')
                ->paginate($lecturerPerPage, ['*'], 'lecturer_page');

            // Get school-specific data
            $semesters = Semester::orderBy('name')->get();
            $groups = Group::with(['class.program.school'])
                ->whereHas('class.program.school', function($query) use ($schoolCode) {
                    $query->where('code', $schoolCode);
                })
                ->orderBy('name')
                ->get();
            
            $classes = ClassModel::with('program.school')
                ->whereHas('program.school', function($query) use ($schoolCode) {
                    $query->where('code', $schoolCode);
                })
                ->orderBy('name')
                ->get();
            
            $units = Unit::with('program.school')
                ->whereHas('program.school', function($query) use ($schoolCode) {
                    $query->where('code', $schoolCode);
                })
                ->orderBy('name')
                ->get();

            // Get students for this school (you might need to adjust this based on your student-school relationship)
            $students = User::role('Student')->get(); // You may want to filter by school

            return Inertia::render('FacultyAdmin/sces/Enrollments', [
                'enrollments' => $enrollments,
                'lecturerAssignments' => [
                    'data' => $lecturerAssignments->items(),
                    'links' => $lecturerAssignments->linkCollection()->toArray()['data'] ?? [],
                    'total' => $lecturerAssignments->total(),
                    'current_page' => $lecturerAssignments->currentPage(),
                    'per_page' => $lecturerAssignments->perPage()
                ],
                'semesters' => $semesters,
                'groups' => $groups,
                'classes' => $classes,
                'units' => $units,
                'students' => $students,
                'schoolCode' => $schoolCode,
                'schoolName' => $this->getSchoolName($schoolCode)
            ]);

        } catch (\Exception $e) {
            Log::error('Error in faculty enrollments:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id()
            ]);

            return Inertia::render('FacultyAdmin/Enrollments', [
                'enrollments' => null,
                'lecturerAssignments' => ['data' => [], 'links' => []],
                'semesters' => [],
                'groups' => [],
                'classes' => [],
                'units' => [],
                'students' => [],
                'schoolCode' => null,
                'schoolName' => null,
                'errors' => ['error' => 'Failed to load enrollments data.']
            ]);
        }
    }

    /**
     * Show bulk enrollment form for faculty
     */
    public function bulkEnrollment(Request $request)
    {
        $schoolCode = $request->get('current_school_code');
        
        if (!$schoolCode) {
            $user = auth()->user();
            $roles = $user->getRoleNames();
            foreach ($roles as $role) {
                if (str_starts_with($role, 'Faculty Admin - ')) {
                    $schoolCode = str_replace('Faculty Admin - ', '', $role);
                    break;
                }
            }
        }

        // Get students for this school (you might need to adjust this based on your student-school relationship)
        $students = User::role('Student')->get(); // You may want to filter by school

        $semesters = Semester::orderBy('name')->get();
        
        $classes = ClassModel::with('program.school')
            ->whereHas('program.school', function($query) use ($schoolCode) {
                $query->where('code', $schoolCode);
            })
            ->orderBy('name')
            ->get();
        
        $groups = Group::with(['class.program.school'])
            ->whereHas('class.program.school', function($query) use ($schoolCode) {
                $query->where('code', $schoolCode);
            })
            ->orderBy('name')
            ->get();
        
        $units = Unit::with('program.school')
            ->whereHas('program.school', function($query) use ($schoolCode) {
                $query->where('code', $schoolCode);
            })
            ->orderBy('name')
            ->get();

        return Inertia::render('FacultyAdmin/BulkEnrollment', [
            'students' => $students,
            'semesters' => $semesters,
            'classes' => $classes,
            'groups' => $groups,
            'units' => $units,
            'schoolCode' => $schoolCode,
            'schoolName' => $this->getSchoolName($schoolCode)
        ]);
    }

    /**
     * Store bulk enrollment for faculty
     */
    public function storeBulkEnrollment(Request $request)
    {
        $schoolCode = $request->get('current_school_code');
        
        $validated = $request->validate([
            'student_codes' => 'required|array|min:1',
            'student_codes.*' => 'required|string|exists:users,code',
            'group_id' => 'required|exists:groups,id',
            'unit_ids' => 'required|array|min:1',
            'unit_ids.*' => 'exists:units,id',
            'semester_id' => 'required|exists:semesters,id',
        ]);

        try {
            $enrollmentCount = 0;
            $errors = [];

            foreach ($validated['student_codes'] as $studentCode) {
                foreach ($validated['unit_ids'] as $unitId) {
                    // Check if enrollment already exists
                    $existingEnrollment = Enrollment::where([
                        'student_code' => $studentCode,
                        'unit_id' => $unitId,
                        'semester_id' => $validated['semester_id']
                    ])->first();

                    if ($existingEnrollment) {
                        continue; // Skip if already enrolled
                    }

                    $unit = Unit::with(['program', 'school'])->find($unitId);
                    
                    // Verify unit belongs to faculty's school
                    if ($unit && $unit->program && $unit->program->school && $unit->program->school->code !== $schoolCode) {
                        $errors[] = "Unit {$unit->name} does not belong to your school";
                        continue;
                    }

                    $enrollment = Enrollment::create([
                        'student_code' => $studentCode,
                        'group_id' => $validated['group_id'],
                        'unit_id' => $unitId,
                        'semester_id' => $validated['semester_id'],
                        'program_id' => $unit->program_id ?? null,
                        'school_id' => $unit->school_id ?? null,
                    ]);

                    $enrollmentCount++;
                }
            }

            if (!empty($errors)) {
                return redirect()->back()->withErrors(['errors' => $errors])->withInput();
            }

            return redirect()->route('faculty.enrollments.' . strtolower($schoolCode))
                ->with('success', "Successfully created {$enrollmentCount} enrollments!");

        } catch (\Exception $e) {
            Log::error('Bulk enrollment failed', [
                'error' => $e->getMessage(),
                'school_code' => $schoolCode,
                'user_id' => auth()->id()
            ]);

            return redirect()->back()->withErrors([
                'error' => 'Failed to create bulk enrollments: ' . $e->getMessage()
            ])->withInput();
        }
    }

    /**
     * Faculty admin store enrollment (school-specific)
     */
    public function facultyStore(Request $request)
    {
        $schoolCode = $request->get('current_school_code');
        
        Log::info('=== FACULTY STORE CALLED ===', [
            'method' => $request->method(),
            'url' => $request->url(),
            'school_code' => $schoolCode,
            'raw_input' => $request->all(),
            'auth_user_code' => Auth::user()->code,
            'user_roles' => Auth::user()->getRoleNames()->toArray()
        ]);

        // Check for student code
        $studentCode = $request->get('student_code');
        if (!$studentCode || trim($studentCode) === '') {
            Log::error('CRITICAL: No student code found in faculty request', [
                'available_fields' => array_keys($request->all()),
                'school_code' => $schoolCode
            ]);
            return redirect()->back()->withErrors([
                'student_code' => 'Student code is required.'
            ])->withInput();
        }

        $validated = $request->validate([
            'student_code' => 'required|string|exists:users,code',
            'group_id' => 'required|exists:groups,id',
            'unit_ids' => 'required|array|min:1',
            'unit_ids.*' => 'exists:units,id',
            'semester_id' => 'required|exists:semesters,id',
        ]);

        // Verify the student exists
        $targetStudent = User::where('code', $validated['student_code'])->first();
        if (!$targetStudent) {
            return redirect()->back()->withErrors([
                'student_code' => "Student with code '{$validated['student_code']}' not found."
            ])->withInput();
        }

        // Verify group belongs to faculty's school
        $group = Group::with('class.program.school')->findOrFail($validated['group_id']);
        if ($group->class && $group->class->program && $group->class->program->school) {
            if ($group->class->program->school->code !== $schoolCode) {
                return redirect()->back()->withErrors([
                    'group_id' => 'Selected group does not belong to your school.'
                ])->withInput();
            }
        }

        // Check group capacity
        $currentEnrollmentCount = Enrollment::where('group_id', $group->id)
            ->distinct('student_code')
            ->count('student_code');

        $studentAlreadyInGroup = Enrollment::where('group_id', $group->id)
            ->where('student_code', $validated['student_code'])
            ->exists();

        if (!$studentAlreadyInGroup && $currentEnrollmentCount >= $group->capacity) {
            return redirect()->back()->withErrors([
                'group_id' => "This group is already full. Capacity: {$group->capacity}, Current enrollments: {$currentEnrollmentCount}.",
            ])->withInput();
        }

        try {
            $enrollmentCount = 0;

            foreach ($validated['unit_ids'] as $unitId) {
                $unit = Unit::with(['program', 'school'])->find($unitId);
                if (!$unit) {
                    continue;
                }

                // Verify unit belongs to faculty's school
                if ($unit->program && $unit->program->school && $unit->program->school->code !== $schoolCode) {
                    Log::warning('Unit does not belong to faculty school', [
                        'unit_id' => $unitId,
                        'unit_school' => $unit->program->school->code,
                        'faculty_school' => $schoolCode
                    ]);
                    continue;
                }

                // Check if enrollment already exists
                $existingEnrollment = Enrollment::where([
                    'student_code' => $validated['student_code'],
                    'unit_id' => $unitId,
                    'semester_id' => $validated['semester_id']
                ])->first();

                if ($existingEnrollment) {
                    continue;
                }

                $enrollment = Enrollment::create([
                    'student_code' => $validated['student_code'],
                    'group_id' => $validated['group_id'],
                    'unit_id' => $unitId,
                    'semester_id' => $validated['semester_id'],
                    'program_id' => $unit->program_id ?? null,
                    'school_id' => $unit->school_id ?? null,
                ]);

                $enrollmentCount++;

                Log::info('Faculty enrollment created successfully', [
                    'enrollment_id' => $enrollment->id,
                    'student_code' => $enrollment->student_code,
                    'unit_name' => $unit->name,
                    'school_code' => $schoolCode,
                    'faculty_admin' => Auth::user()->code
                ]);
            }

            if ($enrollmentCount === 0) {
                return redirect()->back()->withErrors([
                    'error' => 'No new enrollments were created. Student may already be enrolled in selected units.'
                ])->withInput();
            }

            return redirect()->route('faculty.enrollments.' . strtolower($schoolCode))
                ->with('success', "Successfully enrolled {$validated['student_code']} in {$enrollmentCount} unit(s)!");

        } catch (\Exception $e) {
            Log::error('Faculty enrollment creation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'student_code' => $validated['student_code'],
                'school_code' => $schoolCode,
                'faculty_admin' => Auth::user()->code
            ]);

            return redirect()->back()->withErrors([
                'error' => 'Failed to enroll student: ' . $e->getMessage()
            ])->withInput();
        }
    }

    // --- Helper Methods ---

    public function getEnrollmentsByStudent(Request $request, $studentCode)
    {
        try {
            $validated = $request->validate([
                'semester_id' => 'required|exists:semesters,id',
            ]);

            $enrollments = Enrollment::with(['unit', 'group.class'])
                ->where('student_code', $studentCode)
                ->where('semester_id', $validated['semester_id'])
                ->get();

            return response()->json([
                'success' => true,
                'data' => $enrollments,
            ], 200);

        } catch (\Exception $e) {
            Log::error('Get enrollments by student failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get enrollments. Please try again.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getStudentsByUnit(Request $request, $unitId)
    {
        try {
            $validated = $request->validate([
                'semester_id' => 'required|exists:semesters,id',
            ]);

            $enrollments = Enrollment::with('student')
                ->where('unit_id', $unitId)
                ->where('semester_id', $validated['semester_id'])
                ->get();

            $students = $enrollments->map(function ($enrollment) {
                return $enrollment->student;
            });

            return response()->json([
                'success' => true,
                'data' => $students,
            ], 200);

        } catch (\Exception $e) {
            Log::error('Get students by unit failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get students. Please try again.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getUnitsByClassAndSemester(Request $request)
    {
        try {
            $validated = $request->validate([
                'semester_id' => 'required|exists:semesters,id',
                'class_id' => 'required|exists:classes,id',
                'student_code' => 'sometimes|string', // Add optional student_code parameter
            ]);

            // Use the provided student code if available, otherwise use the authenticated user's code
            $studentCode = $validated['student_code'] ?? Auth::user()->code;

            Log::info('Getting units by class and semester', [
                'semester_id' => $validated['semester_id'],
                'class_id' => $validated['class_id'],
                'student_code' => $studentCode,
                'is_admin_request' => isset($validated['student_code'])
            ]);

            $unitIds = DB::table('semester_unit')
                ->where('semester_id', $validated['semester_id'])
                ->where('class_id', $validated['class_id'])
                ->pluck('unit_id');

            if ($unitIds->isNotEmpty()) {
                $units = Unit::whereIn('id', $unitIds)
                    ->where('is_active', true)
                    ->whereDoesntHave('enrollments', function($query) use ($validated, $studentCode) {
                        $query->where('student_code', $studentCode)
                              ->where('semester_id', $validated['semester_id']);
                    })
                    ->with(['program', 'school'])
                    ->get();
            } else {
                $class = ClassModel::with(['program', 'school'])->find($validated['class_id']);
                if ($class) {
                    $query = Unit::where('is_active', true);

                    if ($class->program_id) {
                        $query->where('program_id', $class->program_id);
                    }

                    if ($class->school_id) {
                        $query->where('school_id', $class->school_id);
                    }

                    $query->whereDoesntHave('enrollments', function($subQuery) use ($validated, $studentCode) {
                        $subQuery->where('student_code', $studentCode)
                                 ->where('semester_id', $validated['semester_id']);
                    });

                    $units = $query->with(['program', 'school'])->take(10)->get();
                } else {
                    $units = collect();
                }
            }

            return response()->json([
                'success' => true,
                'units' => $units->map(function($unit) {
                    return [
                        'id' => $unit->id,
                        'name' => $unit->name,
                        'code' => $unit->code ?? null,
                        'credit_hours' => $unit->credit_hours ?? null,
                        'program' => $unit->program ? $unit->program->name : null,
                        'school' => $unit->school ? $unit->school->name : null,
                    ];
                }),
                'count' => $units->count(),
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching units for class and semester: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch units. Please try again.',
                'units' => [],
                'debug' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Helper method to get school name from code
     */
    private function getSchoolName($schoolCode)
    {
        $schoolNames = [
            'SCES' => 'School of Computing and Engineering Sciences',
            'SBS' => 'School of Business Studies',
            'SLS' => 'School of Legal Studies',
            'TOURISM' => 'School of Tourism and Hospitality',
            'SHM' => 'School of Humanities',
            'SHS' => 'School of Health Sciences',
        ];

        return $schoolNames[$schoolCode] ?? $schoolCode;
    }
}
