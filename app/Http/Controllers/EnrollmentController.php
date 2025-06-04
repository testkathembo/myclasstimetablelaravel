<?php
namespace App\Http\Controllers;

use App\Models\ClassModel;
use App\Models\Enrollment;
use App\Models\Semester;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Group;
use Inertia\Inertia;

class EnrollmentController extends Controller
{
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
            return redirect()->back()->withErrors(['group_id' => 'Selected group does not belong to a valid class.']);
        }

        $currentEnrollmentCount = Enrollment::where('group_id', $group->id)
            ->distinct('student_code')
            ->count('student_code');

        if ($currentEnrollmentCount >= $group->capacity) {
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

            return redirect()->back()->with('success', 'Successfully enrolled in ' . count($validated['unit_ids']) . ' unit(s)!');
        } catch (\Exception $e) {
            Log::error('Error creating enrollment: ' . $e->getMessage());
            return redirect()->back()->withErrors(['error' => 'Failed to enroll student. Please try again.']);
        }
    }

    // ADMIN: Manage enrollments (CRUD)
    public function index()
    {
        $enrollments = Enrollment::with(['student', 'unit', 'group.class', 'semester'])->paginate(20);
        
        // Add the missing data that the frontend component expects
        $semesters = Semester::all();
        $classes = ClassModel::with('semester')->get();
        $groups = Group::with('class')->get();
        $units = Unit::with(['program', 'school'])->get();
        
        // Debug information
        Log::info('Enrollments loaded for admin view', [
            'count' => $enrollments->count(),
            'semesters_count' => $semesters->count(),
            'classes_count' => $classes->count(),
            'groups_count' => $groups->count(),
            'units_count' => $units->count(),
            'first_enrollment' => $enrollments->first() ? $enrollments->first()->toArray() : null
        ]);
        
        return inertia('Enrollments/Index', [
            'enrollments' => $enrollments,
            'semesters' => $semesters,
            'classes' => $classes,
            'groups' => $groups,
            'units' => $units,
        ]);
    }

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

    // ADMIN: Store enrollment for any student
    public function adminStore(Request $request)
    {
        Log::info('Admin enrollment store request', $request->all());
        
        $validated = $request->validate([
            'code' => 'required|exists:users,code', // Changed from student_code to code to match frontend
            'group_id' => 'required|exists:groups,id',
            'unit_ids' => 'required|array',
            'unit_ids.*' => 'exists:units,id',
            'semester_id' => 'required|exists:semesters,id',
        ]);

        $studentCode = $validated['code']; // Use the submitted student code
        
        $group = Group::findOrFail($validated['group_id']);
        $class = $group->class;
        if (!$class) {
            return redirect()->back()->withErrors(['group_id' => 'Selected group does not belong to a valid class.']);
        }

        $currentEnrollmentCount = Enrollment::where('group_id', $group->id)
            ->distinct('student_code')
            ->count('student_code');

        if ($currentEnrollmentCount >= $group->capacity) {
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
                    'unit_ids' => 'Student is already enrolled in: ' . implode(', ', $unitNames),
                ]);
            }
        }

        try {
            foreach ($validated['unit_ids'] as $unitId) {
                $unit = Unit::with(['program', 'school'])->find($unitId);

                Enrollment::create([
                    'student_code' => $studentCode, // Use the submitted student code
                    'group_id' => $group->id,
                    'unit_id' => $unitId,
                    'semester_id' => $validated['semester_id'],
                    'program_id' => $unit->program_id ?? $class->program_id ?? null,
                    'school_id' => $unit->school_id ?? $class->school_id ?? null,
                ]);
            }

            return redirect()->route('enrollments.index')->with('success', 'Successfully enrolled student in ' . count($validated['unit_ids']) . ' unit(s)!');
        } catch (\Exception $e) {
            Log::error('Error creating enrollment (admin): ' . $e->getMessage());
            return redirect()->back()->withErrors(['error' => 'Failed to enroll student. Please try again.']);
        }
    }

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

    public function update(Request $request, Enrollment $enrollment)
    {
        $validated = $request->validate([
            'group_id' => 'required|exists:groups,id',
            'unit_id' => 'required|exists:units,id',
            'semester_id' => 'required|exists:semesters,id',
            'student_code' => 'required|exists:users,code',
        ]);
        $enrollment->update($validated);
        return redirect()->route('enrollments.index')->with('success', 'Enrollment updated.');
    }

    public function destroy(Enrollment $enrollment)
    {
        $enrollment->delete();
        return redirect()->route('enrollments.index')->with('success', 'Enrollment deleted.');
    }

    // Optionally, add bulkEnroll and other admin methods as needed

    // --- Existing helper methods below (unchanged) ---

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

    public function bulkDelete(Request $request)
    {
        $ids = $request->input('enrollment_ids', []);
        \App\Models\Enrollment::whereIn('id', $ids)->delete();
        return back()->with('success', 'Selected enrollments deleted.');
    }
}
