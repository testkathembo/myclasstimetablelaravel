<?php

namespace App\Http\Controllers;

use App\Models\Enrollment;
use App\Models\Unit;
use App\Models\Semester;
use App\Models\ClassModel;
use App\Models\Group;
use App\Services\EnrollmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class StudentEnrollmentController extends Controller
{
    protected $enrollmentService;

    public function __construct(EnrollmentService $enrollmentService)
    {
        $this->middleware('auth');
        $this->enrollmentService = $enrollmentService;
    }

    /**
     * Show the enrollment form with available units.
     */
    public function showEnrollmentForm(Request $request)
    {
        $student = Auth::user();
        
        // Get current enrollments
        $enrollments = Enrollment::with(['unit', 'group', 'group.class'])
            ->where('student_code', $student->code)
            ->get();

        // Get all required data for enrollment
        $semesters = Semester::where('is_active', true)->get();
        $classes = ClassModel::with('semester')->get();
        $groups = Group::with(['class', 'class.semester'])
            ->whereHas('class', function($query) {
                $query->whereHas('semester', function($semesterQuery) {
                    $semesterQuery->where('is_active', true);
                });
            })
            ->get();
        $units = Unit::with(['program', 'school'])
            ->where('is_active', true)
            ->get();

        return Inertia::render('Student/Enroll', [
            'student' => [
                'id' => $student->id,
                'code' => $student->code,
                'name' => $student->first_name . ' ' . $student->last_name,
                'email' => $student->email
            ],
            'enrollments' => $enrollments,
            'semesters' => $semesters,
            'classes' => $classes,
            'groups' => $groups,
            'units' => $units,
        ]);
    }

    /**
     * Process student enrollment.
     */
    public function enroll(Request $request)
    {
        $studentCode = Auth::user()->code;

        $validated = $request->validate([
            'group_id' => 'required|exists:groups,id',
            'unit_ids' => 'required|array|min:1',
            'unit_ids.*' => 'exists:units,id',
            'semester_id' => 'required|exists:semesters,id',
        ], [
            'unit_ids.required' => 'Please select at least one unit to enroll in.',
            'unit_ids.min' => 'Please select at least one unit to enroll in.',
        ]);

        $group = Group::findOrFail($validated['group_id']);
        $class = $group->class;
        
        if (!$class) {
            return redirect()->back()->withErrors([
                'group_id' => 'Selected group does not belong to a valid class.'
            ]);
        }

        // CAPACITY CHECK
        $currentEnrollmentCount = Enrollment::where('group_id', $group->id)
            ->distinct('student_code')
            ->count('student_code');

        $studentAlreadyInGroup = Enrollment::where('group_id', $group->id)
            ->where('student_code', $studentCode)
            ->exists();

        if (!$studentAlreadyInGroup && $currentEnrollmentCount >= $group->capacity) {
            Log::info('Student enrollment blocked - group full', [
                'student_code' => $studentCode,
                'group_id' => $group->id,
                'capacity' => $group->capacity,
                'current_count' => $currentEnrollmentCount
            ]);
            
            return redirect()->back()->withErrors([
                'group_id' => "This group is already full. Capacity: {$group->capacity}, Current enrollments: {$currentEnrollmentCount}",
            ]);
        }

        // Check for existing enrollments
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
            $enrollmentCount = 0;
            $enrolledUnits = [];

            foreach ($validated['unit_ids'] as $unitId) {
                $unit = Unit::with(['program', 'school'])->find($unitId);

                if (!$unit) {
                    continue;
                }

                // Check if already enrolled in this specific unit
                $exists = Enrollment::where('student_code', $studentCode)
                    ->where('unit_id', $unitId)
                    ->where('semester_id', $validated['semester_id'])
                    ->exists();

                if ($exists) {
                    continue;
                }

                $enrollment = Enrollment::create([
                    'student_code' => $studentCode,
                    'group_id' => $group->id,
                    'unit_id' => $unitId,
                    'semester_id' => $validated['semester_id'],
                    'program_id' => $unit->program_id ?? $class->program_id ?? null,
                    'school_id' => $unit->school_id ?? $class->school_id ?? null,
                ]);

                $enrollmentCount++;
                $enrolledUnits[] = $unit->name;

                Log::info('Student enrollment created', [
                    'enrollment_id' => $enrollment->id,
                    'student_code' => $studentCode,
                    'unit_name' => $unit->name,
                    'group_id' => $group->id
                ]);
            }

            if ($enrollmentCount > 0) {
                return redirect()->back()->with('success', 
                    "Successfully enrolled in {$enrollmentCount} unit(s): " . implode(', ', $enrolledUnits)
                );
            } else {
                return redirect()->back()->withErrors([
                    'error' => 'No new enrollments were created. You may already be enrolled in the selected units.'
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Student enrollment failed', [
                'error' => $e->getMessage(),
                'student_code' => $studentCode,
                'trace' => $e->getTraceAsString()
            ]);
            
            return redirect()->back()->withErrors([
                'error' => 'Failed to enroll in units: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * View current enrollments.
     */
    public function viewEnrollments(Request $request)
    {
        $user = Auth::user();
        
        // Get active semester or selected semester
        $semesterId = $request->input('semester_id');
        if (!$semesterId) {
            $activeSemester = Semester::where('is_active', true)->first();
            $semesterId = $activeSemester ? $activeSemester->id : null;
        }
        
        // Get enrollments for the student
        $enrollments = Enrollment::where('student_code', $user->code)
            ->when($semesterId, function ($query) use ($semesterId) {
                return $query->where('semester_id', $semesterId);
            })
            ->with(['unit' => function ($query) {
                $query->select('id', 'name', 'code', 'credits', 'semester_id');
            }, 'semester' => function ($query) {
                $query->select('id', 'name');
            }, 'group' => function ($query) {
                $query->select('id', 'name', 'capacity');
            }])
            ->orderBy('created_at', 'desc')
            ->get();
            
        // Get all semesters that have enrollments for this student or are active
        $semesters = Semester::where(function ($query) use ($user) {
                $query->whereIn('id', function ($subQuery) use ($user) {
                    $subQuery->select('semester_id')
                        ->from('enrollments')
                        ->where('student_code', $user->code);
                });
            })
            ->orWhere('is_active', true)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();
        
        // Calculate total credits for selected semester
        $totalCredits = $enrollments->sum(function ($enrollment) {
            return $enrollment->unit->credits ?? 0;
        });
        
        return Inertia::render('Student/MyEnrollments', [
            'enrollments' => $enrollments,
            'semesters' => $semesters,
            'selectedSemester' => $semesterId,
            'totalCredits' => $totalCredits,
            'user' => [
                'code' => $user->code,
                'name' => $user->first_name . ' ' . $user->last_name,
                'email' => $user->email
            ]
        ]);
    }
}