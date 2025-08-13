<?php

namespace App\Http\Controllers;

use App\Models\Enrollment;
use App\Models\Group;
use App\Models\Semester;
use App\Models\Unit;
use App\Models\ScesUnit;
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
    Log::info('=== ADMIN/FACULTY STORE CALLED ===', [
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
        'unit_ids.*' => 'exists:sces_units,id',
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

    // Use the student code we found (this is the target student, not the admin)
    $targetStudentCode = $studentCode;
    $currentUser = Auth::user();
    $currentUserCode = $currentUser->code;

    // Determine if this is faculty admin or super admin
    $isFacultyAdmin = false;
    $schoolCode = null;
    $roles = $currentUser->getRoleNames();
    foreach ($roles as $role) {
        if (str_starts_with($role, 'Faculty Admin - ')) {
            $isFacultyAdmin = true;
            $schoolCode = str_replace('Faculty Admin - ', '', $role);
            break;
        }
    }

    Log::info('ADMIN/FACULTY ENROLLMENT - User context', [
        'target_student' => $targetStudentCode,
        'current_user_code' => $currentUserCode,
        'is_faculty_admin' => $isFacultyAdmin,
        'school_code' => $schoolCode,
        'user_roles' => $roles->toArray(),
        'enrolling_different_student' => $targetStudentCode !== $currentUserCode
    ]);

    // Verify the target student exists
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

    // For faculty admin, verify group belongs to their school
    if ($isFacultyAdmin && $schoolCode) {
        $groupBelongsToSchool = $group->class && 
                               $group->class->program && 
                               $group->class->program->school && 
                               $group->class->program->school->code === $schoolCode;
        
        if (!$groupBelongsToSchool) {
            Log::warning('Faculty admin tried to enroll student in group outside their school', [
                'faculty_admin' => $currentUserCode,
                'school_code' => $schoolCode,
                'group_id' => $group->id,
                'target_student' => $targetStudentCode
            ]);
            return redirect()->back()->withErrors([
                'group_id' => 'You can only enroll students in groups belonging to your school.'
            ])->withInput();
        }
    }

    // Check current enrollment count for this group using sces_enrollments
    $currentEnrollmentCount = DB::table('sces_enrollments')->where('group_id', $group->id)
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
    $studentAlreadyInGroup = DB::table('sces_enrollments')->where('group_id', $group->id)
        ->where('student_code', $targetStudentCode)
        ->exists();

    if (!$studentAlreadyInGroup && $currentEnrollmentCount >= $group->capacity) {
        Log::warning('Group capacity exceeded - Enrollment blocked', [
            'group_id' => $group->id,
            'group_name' => $group->name ?? 'N/A',
            'capacity' => $group->capacity,
            'current_count' => $currentEnrollmentCount,
            'attempted_student' => $targetStudentCode,
            'enrolled_by' => $currentUserCode,
            'is_faculty_admin' => $isFacultyAdmin
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
            $unit = ScesUnit::with(['program', 'school'])->find($unitId);
            if (!$unit) {
                Log::warning('SCES Unit not found:', ['unit_id' => $unitId]);
                continue;
            }

            // For faculty admin, verify unit belongs to their school
            if ($isFacultyAdmin && $schoolCode) {
                $unitBelongsToSchool = $unit->program && 
                                     $unit->program->school && 
                                     $unit->program->school->code === $schoolCode;
                
                if (!$unitBelongsToSchool) {
                    Log::warning('Faculty admin tried to enroll student in unit outside their school', [
                        'faculty_admin' => $currentUserCode,
                        'school_code' => $schoolCode,
                        'unit_id' => $unitId,
                        'unit_name' => $unit->name,
                        'target_student' => $targetStudentCode
                    ]);
                    continue; // Skip this unit
                }
            }

            // Check if enrollment already exists in sces_enrollments
            $existingEnrollment = DB::table('sces_enrollments')->where([
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

            $enrollmentId = DB::table('sces_enrollments')->insertGetId([
                'student_code' => $targetStudentCode, // CRITICAL: Use target student, not current user
                'group_id' => $request->get('group_id') ?? $validated['group_id'],
                'unit_id' => $unitId,
                'semester_id' => $request->get('semester_id') ?? $validated['semester_id'],
                'program_id' => $unit->program_id ?? null,
                'school_id' => $unit->school_id ?? null,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            $enrollmentCount++;

            Log::info('Enrollment created successfully', [
                'enrollment_id' => $enrollmentId,
                'target_student_code' => $targetStudentCode,
                'enrolled_by' => $currentUserCode,
                'is_faculty_admin' => $isFacultyAdmin,
                'school_code' => $schoolCode,
                'unit_name' => $unit->name,
                'group_capacity_after' => $currentEnrollmentCount + 1
            ]);
        }

        if ($enrollmentCount === 0) {
            $errorMessage = $isFacultyAdmin 
                ? 'No new enrollments were created. Student may already be enrolled in selected units, or units may not belong to your school.'
                : 'No new enrollments were created. Student may already be enrolled in selected units.';
            
            return redirect()->back()->withErrors([
                'error' => $errorMessage
            ])->withInput();
        }

        // Determine redirect route based on user type
        $redirectRoute = 'enrollments.index'; // Default for super admin
        if ($isFacultyAdmin && $schoolCode) {
            $redirectRoute = 'faculty.enrollments.' . strtolower($schoolCode);
        }

        $successMessage = $isFacultyAdmin 
            ? "Successfully enrolled {$targetStudentCode} in {$enrollmentCount} unit(s) as Faculty Admin!"
            : "Successfully enrolled {$targetStudentCode} in {$enrollmentCount} unit(s)!";

        return redirect()->route($redirectRoute)->with('success', $successMessage);

    } catch (\Exception $e) {
        Log::error('Enrollment creation failed', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
            'target_student' => $targetStudentCode,
            'enrolled_by' => $currentUserCode,
            'is_faculty_admin' => $isFacultyAdmin,
            'school_code' => $schoolCode
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
        $enrollments = DB::table('sces_enrollments as se')
            ->leftJoin('sces_units as su', 'se.unit_id', '=', 'su.id')
            ->leftJoin('groups as g', 'se.group_id', '=', 'g.id')
            ->leftJoin('classes as c', 'g.class_id', '=', 'c.id')
            ->where('se.student_code', $student->code)
            ->select([
                'se.*',
                'su.name as unit_name',
                'su.code as unit_code',
                'g.name as group_name',
                'c.name as class_name'
            ])
            ->get();

        $semesters = Semester::all();
        $classes = ClassModel::with('semester')->get();
        $groups = Group::with('class')->get();
        $sces_units = ScesUnit::with(['program', 'school'])->get();

        return inertia('Student/Enroll', [
            'student' => $student,
            'enrollments' => $enrollments,
            'semesters' => $semesters,
            'classes' => $classes,
            'groups' => $groups,
            'sces_units' => $sces_units,
        ]);
    }

    // STUDENT: Only enroll (not manage) - UPDATED to use sces_enrollments
public function store(Request $request)
{
    // For self-enrollment, use the authenticated user's code
    $studentCode = Auth::user()->code;

    $validated = $request->validate([
        'group_id' => 'required|exists:groups,id',
        'unit_ids' => 'required|array',
        'unit_ids.*' => 'exists:sces_units,id',
        'semester_id' => 'required|exists:semesters,id',
    ]);

    $group = Group::findOrFail($validated['group_id']);

    // CAPACITY CHECK FOR STUDENT ENROLLMENT using sces_enrollments
    $currentEnrollmentCount = DB::table('sces_enrollments')->where('group_id', $group->id)
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

    $existingEnrollments = DB::table('sces_enrollments')->where('group_id', $group->id)
        ->where('student_code', $studentCode)
        ->where('semester_id', $validated['semester_id'])
        ->pluck('unit_id')
        ->toArray();

    if (!empty($existingEnrollments)) {
        $conflictingUnits = array_intersect($validated['unit_ids'], $existingEnrollments);
        if (!empty($conflictingUnits)) {
            $unitNames = ScesUnit::whereIn('id', $conflictingUnits)->pluck('name')->toArray();
            return redirect()->back()->withErrors([
                'unit_ids' => 'You are already enrolled in: ' . implode(', ', $unitNames),
            ]);
        }
    }

    try {
        $enrollmentCount = 0;
        foreach ($validated['unit_ids'] as $unitId) {
            $unit = ScesUnit::find($unitId);

            if (!$unit) {
                Log::warning('Unit not found during enrollment', ['unit_id' => $unitId]);
                continue;
            }

            // Check if enrollment already exists
            $exists = DB::table('sces_enrollments')
                ->where('student_code', $studentCode)
                ->where('unit_id', $unitId)
                ->where('semester_id', $validated['semester_id'])
                ->exists();

            if ($exists) {
                Log::info('Enrollment already exists, skipping', [
                    'student_code' => $studentCode,
                    'unit_id' => $unitId,
                    'semester_id' => $validated['semester_id']
                ]);
                continue;
            }

            DB::table('sces_enrollments')->insert([
                'student_code' => $studentCode,
                'group_id' => $group->id,
                'unit_id' => $unitId,
                'semester_id' => $validated['semester_id'],
                'program_id' => $unit->program_id ?? null,
                'school_id' => $unit->school_id ?? null,
                'created_at' => now(),
                'updated_at' => now()
            ]);
            $enrollmentCount++;
        }

        if ($enrollmentCount === 0) {
            return redirect()->back()->withErrors([
                'error' => 'No new enrollments were created. You may already be enrolled in the selected units.'
            ])->withInput();
        }

        return redirect()->back()->with('success', 
            'Successfully enrolled in ' . $enrollmentCount . ' unit(s)!'
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
            // Get enrollments with relationships from sces_enrollments
            $enrollments = DB::table('sces_enrollments as se')
                ->leftJoin('users as u', 'se.student_code', '=', 'u.code')
                ->leftJoin('sces_units as su', 'se.unit_id', '=', 'su.id')
                ->leftJoin('groups as g', 'se.group_id', '=', 'g.id')
                ->leftJoin('classes as c', 'g.class_id', '=', 'c.id')
                ->select([
                    'se.*',
                    'u.first_name as student_first_name',
                    'u.last_name as student_last_name',
                    'u.code as student_code_field',
                    'su.name as unit_name',
                    'su.code as unit_code',
                    'g.name as group_name',
                    'c.name as class_name'
                ])
                ->orderBy('se.created_at', 'desc')
                ->paginate(15);

            // Get lecturer assignments
            $lecturerPerPage = $request->input('lecturer_per_page', 15);
            $lecturerAssignments = DB::table('sces_enrollments')
                ->select([
                    'sces_enrollments.unit_id',
                    'sces_units.name as unit_name',
                    'sces_units.code as unit_code',
                    'sces_enrollments.lecturer_code',
                    'users.first_name',
                    'users.last_name',
                    DB::raw("CONCAT(users.first_name, ' ', users.last_name) as lecturer_name")
                ])
                ->join('sces_units', 'sces_enrollments.unit_id', '=', 'sces_units.id')
                ->leftJoin('users', 'sces_enrollments.lecturer_code', '=', 'users.code')
                ->whereNotNull('sces_enrollments.lecturer_code')
                ->where('sces_enrollments.lecturer_code', '!=', '')
                ->groupBy([
                    'sces_enrollments.unit_id', 'sces_units.name', 'sces_units.code',
                    'sces_enrollments.lecturer_code', 'users.first_name', 'users.last_name'
                ])
                ->orderBy('sces_units.name')
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
            $sces_units = ScesUnit::orderBy('name')->get();

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
                'sces_units' => $sces_units,
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
                'sces_units' => [],
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
            'unit_id' => 'required|exists:sces_units,id',
            'lecturer_code' => 'required|string|exists:users,code',
        ]);

        try {
            // Update all sces_enrollments for this unit to have the lecturer
            $updated = DB::table('sces_enrollments')->where('unit_id', $validated['unit_id'])
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
        $sces_units = ScesUnit::all();

        // Debug information
        Log::info('Admin enrollment create form data', [
            'students_count' => $students->count(),
            'semesters_count' => $semesters->count(),
            'classes_count' => $classes->count(),
            'groups_count' => $groups->count(),
            'sces_units_count' => $sces_units->count()
        ]);

        return inertia('Enrollments/Create', [
            'students' => $students,
            'semesters' => $semesters,
            'classes' => $classes,
            'groups' => $groups,
            'sces_units' => $sces_units
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($enrollmentId)
    {
        $enrollment = DB::table('sces_enrollments as se')
            ->leftJoin('users as u', 'se.student_code', '=', 'u.code')
            ->leftJoin('sces_units as su', 'se.unit_id', '=', 'su.id')
            ->leftJoin('groups as g', 'se.group_id', '=', 'g.id')
            ->leftJoin('semesters as s', 'se.semester_id', '=', 's.id')
            ->where('se.id', $enrollmentId)
            ->select([
                'se.*',
                'u.first_name as student_first_name',
                'u.last_name as student_last_name',
                'u.code as student_code_field',
                'su.name as unit_name',
                'g.name as group_name',
                's.name as semester_name'
            ])
            ->first();

        if (!$enrollment) {
            abort(404, 'Enrollment not found');
        }

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
        $sces_units = ScesUnit::all();

        return inertia('Enrollments/Edit', [
            'enrollment' => $enrollment,
            'students' => $students,
            'semesters' => $semesters,
            'classes' => $classes,
            'groups' => $groups,
            'sces_units' => $sces_units
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $enrollmentId)
    {
        $validated = $request->validate([
            'group_id' => 'required|exists:groups,id',
            'unit_id' => 'required|exists:sces_units,id',
            'semester_id' => 'required|exists:semesters,id',
            'student_code' => 'required|exists:users,code',
        ]);

        // Get current enrollment
        $enrollment = DB::table('sces_enrollments')->where('id', $enrollmentId)->first();
        if (!$enrollment) {
            abort(404, 'Enrollment not found');
        }

        // CAPACITY CHECK FOR UPDATE
        $group = Group::findOrFail($validated['group_id']);

        // Only check capacity if we're changing to a different group
        if ($enrollment->group_id != $validated['group_id']) {
            $currentEnrollmentCount = DB::table('sces_enrollments')->where('group_id', $group->id)
                ->distinct('student_code')
                ->count('student_code');

            // Check if the student is already in the new group
            $studentAlreadyInNewGroup = DB::table('sces_enrollments')->where('group_id', $group->id)
                ->where('student_code', $validated['student_code'])
                ->where('id', '!=', $enrollmentId) // Exclude current enrollment
                ->exists();

            if (!$studentAlreadyInNewGroup && $currentEnrollmentCount >= $group->capacity) {
                Log::warning('Group capacity exceeded during update', [
                    'enrollment_id' => $enrollmentId,
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
            'enrollment_id' => $enrollmentId,
            'old_student_code' => $enrollment->student_code,
            'new_student_code' => $validated['student_code'],
            'old_group_id' => $enrollment->group_id,
            'new_group_id' => $validated['group_id'],
            'updated_by' => Auth::user()->code
        ]);

        DB::table('sces_enrollments')->where('id', $enrollmentId)->update([
            'group_id' => $validated['group_id'],
            'unit_id' => $validated['unit_id'],
            'semester_id' => $validated['semester_id'],
            'student_code' => $validated['student_code'],
            'updated_at' => now()
        ]);

        return redirect()->route('enrollments.index')->with('success', 'Enrollment updated.');
    }

    /**
     * UPDATED: Destroy method for SCES enrollments
     */
    public function destroy($id)
    {
        try {
            $user = auth()->user();
            $schoolCode = null;
            
            // Get school code from user's role
            $roles = $user->getRoleNames();
            foreach ($roles as $role) {
                if (str_starts_with($role, 'Faculty Admin - ')) {
                    $schoolCode = str_replace('Faculty Admin - ', '', $role);
                    break;
                }
            }

            Log::info('Destroying enrollment', [
                'enrollment_id' => $id,
                'school_code' => $schoolCode,
                'user_code' => $user->code
            ]);

            // Always delete from sces_enrollments table for SCES school
            $deleted = DB::table('sces_enrollments')->where('id', $id)->delete();
            
            Log::info('SCES enrollment deleted', [
                'enrollment_id' => $id,
                'deleted_rows' => $deleted,
                'deleted_by' => $user->code
            ]);

            if ($deleted) {
                return redirect()->back()->with('success', 'Enrollment deleted successfully!');
            } else {
                return redirect()->back()->withErrors(['error' => 'Enrollment not found']);
            }
        
    } catch (\Exception $e) {
        Log::error('Error deleting enrollment', [
            'enrollment_id' => $id,
            'error' => $e->getMessage(),
            'user_id' => auth()->id()
        ]);
        
        return redirect()->back()->withErrors([
            'error' => 'Failed to delete enrollment: ' . $e->getMessage()
        ]);
    }
}

    public function bulkDelete(Request $request)
    {
        $ids = $request->input('enrollment_ids', []);

        Log::info('Bulk deleting sces_enrollments', [
            'enrollment_ids' => $ids,
            'count' => count($ids),
            'deleted_by' => Auth::user()->code
        ]);

        DB::table('sces_enrollments')->whereIn('id', $ids)->delete();

        return back()->with('success', 'Selected enrollments deleted.');
    }

    // --- FACULTY ADMIN METHODS ---

    /**
     * Show enrollments for faculty admin's school - UPDATED FOR SCES TABLES
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

            // Query SCES tables directly - always use sces_enrollments for SCES
            // Fetch enrollments from sces_enrollments table
            $enrollments = DB::table('sces_enrollments as se')
                ->leftJoin('sces_units as su', 'se.unit_id', '=', 'su.id')
                ->leftJoin('groups as g', 'se.group_id', '=', 'g.id')
                ->leftJoin('classes as c', 'g.class_id', '=', 'c.id')
                ->leftJoin('users as u', 'se.student_code', '=', 'u.code')
                ->select([
                    'se.id',
                    'se.student_code',
                    'se.group_id', 
                    'se.unit_id',
                    'se.semester_id',
                    'su.name as unit_name',
                    'su.code as unit_code',
                    'g.name as group_name',
                    'g.capacity as group_capacity',
                    'c.name as class_name',
                    'c.id as class_id',
                    'u.first_name as student_first_name',
                    'u.last_name as student_last_name'
                ])
                ->orderBy('se.created_at', 'desc')
                ->paginate(15);

            // Transform the data to match your frontend interface
            $transformedEnrollments = $enrollments->through(function ($enrollment) {
                return [
                    'id' => $enrollment->id,
                    'student_code' => $enrollment->student_code,
                    'group_id' => $enrollment->group_id,
                    'unit_id' => $enrollment->unit_id,
                    'semester_id' => $enrollment->semester_id,
                    'student' => $enrollment->student_first_name ? [
                        'code' => $enrollment->student_code,
                        'first_name' => $enrollment->student_first_name,
                        'last_name' => $enrollment->student_last_name,
                        'name' => $enrollment->student_first_name . ' ' . $enrollment->student_last_name
                    ] : null,
                    'unit' => $enrollment->unit_name ? [
                        'id' => $enrollment->unit_id,
                        'name' => $enrollment->unit_name,
                        'code' => $enrollment->unit_code
                    ] : null,
                    'group' => $enrollment->group_name ? [
                        'id' => $enrollment->group_id,
                        'name' => $enrollment->group_name,
                        'capacity' => $enrollment->group_capacity,
                        'class' => $enrollment->class_name ? [
                            'id' => $enrollment->class_id,
                            'name' => $enrollment->class_name
                        ] : null
                    ] : null
                ];
            });

            // Fetch units from sces_units table
            $sces_units = DB::table('sces_units')->select('id', 'name', 'code', 'semester_id', 'program_id', 'class_id')->get();

            // Get lecturer assignments for SCES units
            $lecturerPerPage = $request->input('lecturer_per_page', 15);
            $lecturerAssignments = DB::table('sces_enrollments as se')
                ->select([
                    'se.unit_id',
                    'su.name as unit_name',
                    'su.code as unit_code',
                    'se.lecturer_code',
                    'u.first_name',
                    'u.last_name',
                    DB::raw("CONCAT(u.first_name, ' ', u.last_name) as lecturer_name")
                ])
                ->join('sces_units as su', 'se.unit_id', '=', 'su.id')
                ->leftJoin('users as u', 'se.lecturer_code', '=', 'u.code')
                ->whereNotNull('se.lecturer_code')
                ->where('se.lecturer_code', '!=', '')
                ->groupBy([
                    'se.unit_id', 'su.name', 'su.code',
                    'se.lecturer_code', 'u.first_name', 'u.last_name'
                ])
                ->orderBy('su.name')
                ->paginate($lecturerPerPage, ['*'], 'lecturer_page');

            // Transform lecturer assignments for consistent format
            $transformedLecturerAssignments = $lecturerAssignments->through(function ($assignment) {
                return [
                    'unit_id' => $assignment->unit_id,
                    'unit_name' => $assignment->unit_name,
                    'unit_code' => $assignment->unit_code,
                    'lecturer_code' => $assignment->lecturer_code,
                    'lecturer_name' => $assignment->lecturer_name ?? 'Unknown'
                ];
            });

            // Get supporting data from regular tables (these should be shared across schools)
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

            // Get students for this school
            $students = User::role('Student')->get();

            return Inertia::render('FacultyAdmin/sces/Enrollments', [
                'enrollments' => $transformedEnrollments,
                'lecturerAssignments' => [
                    'data' => $transformedLecturerAssignments->items(),
                    'links' => $lecturerAssignments->linkCollection()->toArray()['data'] ?? [],
                    'total' => $lecturerAssignments->total(),
                    'current_page' => $lecturerAssignments->currentPage(),
                    'per_page' => $lecturerAssignments->perPage(),
                    'last_page' => $lecturerAssignments->lastPage()
                ],
                'semesters' => $semesters,
                'groups' => $groups,
                'classes' => $classes,
                'sces_units' => $sces_units,
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

            return Inertia::render('FacultyAdmin/sces/Enrollments', [
                'enrollments' => null,
                'lecturerAssignments' => ['data' => [], 'links' => []],
                'semesters' => [],
                'groups' => [],
                'classes' => [],
                'sces_units' => [],
                'students' => [],
                'schoolCode' => null,
                'schoolName' => null,
                'errors' => ['error' => 'Failed to load enrollments data.']
            ]);
        }
    }

    /**
     * UPDATED: Store method for SCES enrollments
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
            'unit_ids.*' => 'integer',
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

        // Check group capacity using SCES enrollments
        $currentEnrollmentCount = DB::table('sces_enrollments')
            ->where('group_id', $group->id)
            ->distinct('student_code')
            ->count('student_code');

        $studentAlreadyInGroup = DB::table('sces_enrollments')
            ->where('group_id', $group->id)
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
                // Check if SCES unit exists
                $unit = DB::table('sces_units')->where('id', $unitId)->first();
                if (!$unit) {
                    Log::warning('SCES unit not found', ['unit_id' => $unitId]);
                    continue;
                }

                // Check if enrollment already exists in SCES table
                $existingEnrollment = DB::table('sces_enrollments')->where([
                    'student_code' => $validated['student_code'],
                    'unit_id' => $unitId,
                    'semester_id' => $validated['semester_id']
                ])->first();

                if ($existingEnrollment) {
                    Log::info('SCES enrollment already exists, skipping', [
                        'student_code' => $validated['student_code'],
                        'unit_id' => $unitId
                    ]);
                    continue;
                }

                // Insert into sces_enrollments table
                $enrollmentId = DB::table('sces_enrollments')->insertGetId([
                    'student_code' => $validated['student_code'],
                    'group_id' => $validated['group_id'],
                    'unit_id' => $unitId,
                    'semester_id' => $validated['semester_id'],
                    'program_id' => $unit->program_id ?? null,
                    'school_id' => $unit->school_id ?? null,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                $enrollmentCount++;

                Log::info('SCES enrollment created successfully', [
                    'enrollment_id' => $enrollmentId,
                    'student_code' => $validated['student_code'],
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
            Log::error('Faculty SCES enrollment creation failed', [
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

    /**
     * UPDATED: Bulk destroy method for SCES enrollments
     */
    public function bulkDestroy(Request $request)
    {
        try {
            $enrollmentIds = $request->input('enrollment_ids', []);
            
            if (empty($enrollmentIds)) {
                return redirect()->back()->withErrors(['error' => 'No enrollments selected']);
            }

            $user = auth()->user();
            
            // Delete from sces_enrollments table
            $deleted = DB::table('sces_enrollments')->whereIn('id', $enrollmentIds)->delete();
            
            Log::info('SCES enrollments bulk deleted', [
                'enrollment_ids' => $enrollmentIds,
                'deleted_rows' => $deleted,
                'deleted_by' => $user->code
            ]);
            
            return redirect()->back()->with('success', count($enrollmentIds) . ' enrollments deleted successfully!');
            
        } catch (\Exception $e) {
            Log::error('Error bulk deleting enrollments', [
                'enrollment_ids' => $request->input('enrollment_ids', []),
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);
            
            return redirect()->back()->withErrors([
                'error' => 'Failed to delete enrollments: ' . $e->getMessage()
            ]);
        }
    }

    /**
 * UPDATED: Get units by class and semester for SCES with better debugging
 */
public function getUnitsByClassAndSemester(Request $request)
{
    try {
        $validated = $request->validate([
            'semester_id' => 'required|integer',
            'class_id' => 'required|integer',
            'student_code' => 'sometimes|string',
        ]);

        $studentCode = $validated['student_code'] ?? Auth::user()->code;
        $user = auth()->user();
        $schoolCode = null;
        
        // Get school code from user's role
        $roles = $user->getRoleNames();
        foreach ($roles as $role) {
            if (str_starts_with($role, 'Faculty Admin - ')) {
                $schoolCode = str_replace('Faculty Admin - ', '', $role);
                break;
            }
        }

        Log::info('Getting units by class and semester', [
            'semester_id' => $validated['semester_id'],
            'class_id' => $validated['class_id'],
            'student_code' => $studentCode,
            'school_code' => $schoolCode,
            'is_sces' => $schoolCode === 'SCES'
        ]);

        if ($schoolCode === 'SCES') {
            // First, let's check what class we're dealing with
            $selectedClass = ClassModel::find($validated['class_id']);
            Log::info('Selected class details', [
                'class_id' => $validated['class_id'],
                'class_name' => $selectedClass ? $selectedClass->name : 'Not found',
                'class_semester_id' => $selectedClass ? $selectedClass->semester_id : 'N/A'
            ]);

            // Debug: Check what units exist in sces_units
            $allScesUnits = DB::table('sces_units')
                ->select('id', 'name', 'code', 'semester_id', 'class_id', 'is_active')
                ->get();

            Log::info('All SCES units in database', [
                'total_units' => $allScesUnits->count(),
                'units_by_semester' => $allScesUnits->groupBy('semester_id')->map->count(),
                'units_by_class' => $allScesUnits->groupBy('class_id')->map->count(),
                'sample_units' => $allScesUnits->take(5)->toArray()
            ]);

            // Check if there are units for this specific semester and class
            $unitsForSemesterAndClass = DB::table('sces_units')
                ->where('semester_id', $validated['semester_id'])
                ->where('class_id', $validated['class_id'])
                ->where('is_active', 1)
                ->get();

            Log::info('Units for specific semester and class', [
                'count' => $unitsForSemesterAndClass->count(),
                'sces_units' => $unitsForSemesterAndClass->toArray()
            ]);

            // If no units found with exact class_id match, try alternative approaches
            if ($unitsForSemesterAndClass->count() === 0) {
                Log::info('No units found with exact match, trying alternatives...');
                
                // Option 1: Try with just semester_id (ignore class_id)
                $unitsForSemesterOnly = DB::table('sces_units')
                    ->where('semester_id', $validated['semester_id'])
                    ->where('is_active', 1)
                    ->get();

                Log::info('Units for semester only', [
                    'count' => $unitsForSemesterOnly->count(),
                    'units' => $unitsForSemesterOnly->take(3)->toArray()
                ]);

                // Option 2: Check if class_id is NULL in sces_units
                $unitsWithNullClassId = DB::table('sces_units')
                    ->where('semester_id', $validated['semester_id'])
                    ->whereNull('class_id')
                    ->where('is_active', 1)
                    ->get();

                Log::info('Units with NULL class_id', [
                    'count' => $unitsWithNullClassId->count()
                ]);

                // Option 3: Use semester + program relationship
                if ($selectedClass && $selectedClass->program_id) {
                    $unitsForProgram = DB::table('sces_units')
                        ->where('semester_id', $validated['semester_id'])
                        ->where('program_id', $selectedClass->program_id)
                        ->where('is_active', 1)
                        ->get();

                    Log::info('Units for program', [
                        'program_id' => $selectedClass->program_id,
                        'count' => $unitsForProgram->count(),
                        'sces_units' => $unitsForProgram->take(3)->toArray()
                    ]);

                    // Use program-based units if available
                    if ($unitsForProgram->count() > 0) {
                        $unitsForSemesterAndClass = $unitsForProgram;
                    }
                }

                // Fallback: Use semester-only units if nothing else works
                if ($unitsForSemesterAndClass->count() === 0 && $unitsForSemesterOnly->count() > 0) {
                    $unitsForSemesterAndClass = $unitsForSemesterOnly;
                    Log::info('Using semester-only units as fallback');
                }
            }

            // Convert to collection and exclude already enrolled units
            $units = collect($unitsForSemesterAndClass);

            // Check for existing enrollments
            $enrolledUnitIds = DB::table('sces_enrollments')
                ->where('student_code', $studentCode)
                ->where('semester_id', $validated['semester_id'])
                ->pluck('unit_id')
                ->toArray();

            Log::info('Already enrolled units', [
                'student_code' => $studentCode,
                'enrolled_unit_ids' => $enrolledUnitIds
            ]);

            // Filter out already enrolled units
            $availableUnits = $units->whereNotIn('id', $enrolledUnitIds);

            Log::info('Final available units', [
                'total_before_filter' => $units->count(),
                'available_after_filter' => $availableUnits->count(),
                'available_units' => $availableUnits->values()->toArray()
            ]);

        } else {
            // Use original logic for other schools
            $unitIds = DB::table('semester_unit')
                ->where('semester_id', $validated['semester_id'])
                ->where('class_id', $validated['class_id'])
                ->pluck('unit_id');

            if ($unitIds->isNotEmpty()) {
                $units = ScesUnit::whereIn('id', $unitIds)
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
                    $query = ScesUnit::where('is_active', true);

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
            $availableUnits = $units;
        }

        return response()->json([
            'success' => true,
            'sces_units' => $availableUnits->map(function($unit) {
                return [
                    'id' => $unit->id ?? $unit['id'],
                    'name' => $unit->name ?? $unit['name'],
                    'code' => $unit->code ?? $unit['code'] ?? null,
                    'credit_hours' => $unit->credit_hours ?? $unit['credit_hours'] ?? null,
                    'program' => isset($unit->program) ? $unit->program->name : null,
                    'school' => isset($unit->school) ? $unit->school->name : null,
                ];
            }),
            'count' => $availableUnits->count(),
        ]);

    } catch (\Exception $e) {
        Log::error('Error fetching units for class and semester: ' . $e->getMessage(), [
            'trace' => $e->getTraceAsString(),
            'semester_id' => $validated['semester_id'] ?? null,
            'class_id' => $validated['class_id'] ?? null
        ]);
        
        return response()->json([
            'success' => false,
            'error' => 'Failed to fetch units. Please try again.',
            'sces_units' => [],
            'debug' => $e->getMessage()
        ], 500);
    }
}
    /**
     * Get enrollments by student
     */
    public function getEnrollmentsByStudent(Request $request, $studentCode)
    {
        try {
            $validated = $request->validate([
                'semester_id' => 'required|exists:semesters,id',
            ]);

            // Query SCES enrollments
            $enrollments = DB::table('sces_enrollments as se')
                ->leftJoin('sces_units as su', 'se.unit_id', '=', 'su.id')
                ->leftJoin('groups as g', 'se.group_id', '=', 'g.id')
                ->leftJoin('classes as c', 'g.class_id', '=', 'c.id')
                ->where('se.student_code', $studentCode)
                ->where('se.semester_id', $validated['semester_id'])
                ->select([
                    'se.*',
                    'su.name as unit_name',
                    'su.code as unit_code',
                    'g.name as group_name',
                    'c.name as class_name'
                ])
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

    /**
     * Get students by unit
     */
    public function getStudentsByUnit(Request $request, $unitId)
    {
        try {
            $validated = $request->validate([
                'semester_id' => 'required|exists:semesters,id',
            ]);

            // Query SCES enrollments
            $enrollments = DB::table('sces_enrollments as se')
                ->leftJoin('users as u', 'se.student_code', '=', 'u.code')
                ->where('se.unit_id', $unitId)
                ->where('se.semester_id', $validated['semester_id'])
                ->select([
                    'u.id',
                    'u.code',
                    'u.first_name',
                    'u.last_name',
                    'u.email'
                ])
                ->get();

            $students = $enrollments->map(function ($enrollment) {
                return [
                    'id' => $enrollment->id,
                    'code' => $enrollment->code,
                    'first_name' => $enrollment->first_name,
                    'last_name' => $enrollment->last_name,
                    'email' => $enrollment->email,
                    'name' => $enrollment->first_name . ' ' . $enrollment->last_name
                ];
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