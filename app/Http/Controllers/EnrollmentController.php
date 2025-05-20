<?php

namespace App\Http\Controllers;

use App\Models\Enrollment;
use App\Models\Unit;
use App\Models\Semester;
use App\Models\Program;
use App\Models\School;
use App\Models\User;
use App\Models\Group;
use App\Models\ClassModel;
use App\Services\EnrollmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
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
        // Only apply authorization to resource methods, not to API methods
        $this->middleware(function ($request, $next) {
            if (!$request->is('api/*')) {
                $this->authorizeResource(Enrollment::class, 'enrollment');
            }
            return $next($request);
        });
        $this->enrollmentService = $enrollmentService;
    }

    /**
     * Display a listing of the enrollments.
     *
     * @return \Inertia\Response
     */
    public function index()
    {
        $enrollments = Enrollment::with([
            'student:id,code,first_name,last_name',
            'unit',
            'group',
            'group.class',
            'program',
            'school',
        ])
        ->orderBy('id', 'desc')
        ->paginate(10);

        $lecturerAssignments = Enrollment::whereNotNull('lecturer_code')
            ->with('unit:id,name')
            ->select('unit_id', 'lecturer_code')
            ->distinct()
            ->paginate(10) // Apply pagination here
            ->through(function ($assignment) {
                return [
                    'unit_id' => $assignment->unit_id,
                    'unit_name' => $assignment->unit->name ?? 'N/A',
                    'lecturer_code' => $assignment->lecturer_code,
                    'lecturer_name' => User::where('code', $assignment->lecturer_code)->value('first_name') ?? 'N/A',
                ];
            });

        if ($enrollments->count() > 0) {
            Log::info('First enrollment data for debugging:', [
                'id' => $enrollments[0]->id,
                'student_code' => $enrollments[0]->student_code,
                'student' => $enrollments[0]->student,
                'student_relation_loaded' => $enrollments[0]->relationLoaded('student'),
            ]);
        }

        $semesters = Semester::all();
        $classes = ClassModel::with('semester')->get(); // This will now work
        $groups = Group::with('class')->get();
        $units = Unit::with(['program', 'school'])->get();

        return inertia('Enrollments/Index', [
            'enrollments' => $enrollments,
            'semesters' => $semesters,
            'classes' => $classes,
            'groups' => $groups,
            'units' => $units,
            'lecturerAssignments' => $lecturerAssignments, // Pass paginated data
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
        // Validate the incoming request
        $validated = $request->validate([
            'group_id' => 'required|exists:groups,id',
            'code' => 'required|string|exists:users,code', // Ensure the student_code exists in the users table
            'unit_ids' => 'required|array', // Allow multiple units
            'unit_ids.*' => 'exists:units,id',
            'semester_id' => 'required|exists:semesters,id',
        ]);

        // Check if the group exists
        $group = Group::findOrFail($validated['group_id']);

        // Count the number of unique students already enrolled in the group
        $currentEnrollmentCount = Enrollment::where('group_id', $group->id)
            ->distinct('student_code')
            ->count('student_code');

        // Check if adding this student would exceed the group's capacity
        if ($currentEnrollmentCount >= $group->capacity) {
            return redirect()->back()->withErrors([
                'group_id' => "This group is already full. Capacity: {$group->capacity}",
            ]);
        }

        // Check if the student is already enrolled in the group
        $studentAlreadyEnrolled = Enrollment::where('group_id', $group->id)
            ->where('student_code', $validated['code'])
            ->exists();

        if ($studentAlreadyEnrolled) {
            return redirect()->back()->withErrors([
                'code' => 'This student is already enrolled in the selected group.',
            ]);
        }

        // Create enrollments for each unit
        foreach ($validated['unit_ids'] as $unitId) {
            Enrollment::create([
                'student_code' => $validated['code'],
                'group_id' => $group->id,
                'unit_id' => $unitId,
                'semester_id' => $validated['semester_id'],
            ]);
        }

        return redirect()->back()->with('success', 'Student enrolled successfully!');
    }

    /**
     * Remove the specified enrollment from storage.
     *
     * @param  \App\Models\Enrollment  $enrollment
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(Enrollment $enrollment)
    {
        try {
            $enrollment->delete();
            return redirect()->back()->with('success', 'Enrollment removed successfully!');
        } catch (\Exception $e) {
            Log::error('Error deleting enrollment: ' . $e->getMessage());
            return redirect()->back()->withErrors(['error' => 'Failed to delete enrollment: ' . $e->getMessage()]);
        }
    }

    /**
     * Get units by class and semester.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUnitsByClassAndSemester(Request $request)
    {
        try {
            $validated = $request->validate([
                'semester_id' => 'required|exists:semesters,id',
                'class_id' => 'required|exists:classes,id',
            ]);

            // Fetch units directly linked to the selected class and semester
            $units = DB::table('semester_unit')
                ->join('units', 'semester_unit.unit_id', '=', 'units.id')
                ->where('semester_unit.semester_id', $validated['semester_id'])
                ->where('semester_unit.class_id', $validated['class_id'])
                ->select('units.*')
                ->get();

            if ($units->isEmpty()) {
                return response()->json([
                    'error' => 'No units found for the selected class and semester.',
                ], 404);
            }

            return response()->json($units);
        } catch (\Exception $e) {
            Log::error('Error fetching units for class and semester: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch units. Please try again.'], 500);
        }
    }
    
    /**
     * Get unit details including program and school.
     *
     * @param  int  $unitId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUnitDetails($unitId)
    {
        try {
            $unit = Unit::with(['program', 'school'])->findOrFail($unitId);
            
            return response()->json([
                'success' => true,
                'unit' => $unit,
                'program_id' => $unit->program_id ?? ($unit->program ? $unit->program->id : null),
                'school_id' => $unit->school_id ?? ($unit->school ? $unit->school->id : null),
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching unit details: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch unit details.'], 500);
        }
    }

    // Add this to your EnrollmentController.php or similar file
public function getUnitsForClass(Request $request)
{
    $semesterId = $request->input('semester_id');
    $classId = $request->input('class_id');
    
    // Log the request parameters
    Log::info('Fetching units for class', [
        'semester_id' => $semesterId,
        'class_id' => $classId
    ]);
    
    // Get all units assigned to this class and semester
    $units = DB::table('units')
        ->where('semester_id', $semesterId)
        ->where('class_id', $classId)
        ->get();
    
    Log::info('Units found for class', [
        'count' => count($units),
        'units' => $units
    ]);
    
    return response()->json([
        'units' => $units
    ]);
}
}
