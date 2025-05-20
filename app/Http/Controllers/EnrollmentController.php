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
            'code' => 'required|string', // Student code
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

            Log::info('Fetching units for semester_id: ' . $validated['semester_id'] . ' and class_id: ' . $validated['class_id']);
            
            // Get the class details for debugging
            $class = ClassModel::find($validated['class_id']);
            Log::info('Class details:', [
                'id' => $class->id,
                'name' => $class->name
            ]);

            // First approach: Use the relationship defined in ClassModel to get units for this class and semester
            $classWithUnits = ClassModel::with(['units' => function($query) use ($validated) {
                $query->whereHas('semesters', function($q) use ($validated) {
                    $q->where('semesters.id', $validated['semester_id']);
                });
            }])->find($validated['class_id']);
        
            if ($classWithUnits && $classWithUnits->units->count() > 0) {
                Log::info('Found ' . $classWithUnits->units->count() . ' units using relationship');
                return response()->json($classWithUnits->units);
            }
        
            // Second approach: Query the semester_unit table directly
            $unitIds = DB::table('semester_unit')
                ->where('semester_id', $validated['semester_id'])
                ->where('class_id', $validated['class_id'])
                ->pluck('unit_id')
                ->toArray();
            
            if (!empty($unitIds)) {
                $units = Unit::with(['program', 'school'])
                    ->whereIn('id', $unitIds)
                    ->get();
                
                if ($units->count() > 0) {
                    Log::info('Found ' . $units->count() . ' units using direct query');
                    return response()->json($units);
                }
            }
            
            // Third approach: Look for units that have been previously enrolled for this class
            $enrolledUnitIds = DB::table('enrollments')
                ->join('groups', 'enrollments.group_id', '=', 'groups.id')
                ->where('groups.class_id', $validated['class_id'])
                ->where('enrollments.semester_id', $validated['semester_id'])
                ->distinct()
                ->pluck('enrollments.unit_id')
                ->toArray();
        
            if (!empty($enrolledUnitIds)) {
                $units = Unit::with(['program', 'school'])
                    ->whereIn('id', $enrolledUnitIds)
                    ->get();
            
                if ($units->count() > 0) {
                    Log::info('Found ' . $units->count() . ' units from previous enrollments');
                    return response()->json($units);
                }
            }
        
            // Fourth approach: Try to find units based on class name pattern matching
            if (preg_match('/(\w+)\s+(\d+\.\d+)/', $class->name, $matches)) {
                $program = $matches[1]; // e.g., "BBIT"
                $level = $matches[2];   // e.g., "1.1"
            
                // Extract the major level (e.g., "1" from "1.1")
                $majorLevel = explode('.', $level)[0];
            
                try {
                    // Check if the 'code' column exists
                    if (Schema::hasColumn('units', 'code')) {
                        // Look for units with codes that might match this class level
                        $query = Unit::with(['program', 'school']);
                    
                        $query->where(function($q) use ($program, $level, $majorLevel) {
                            // Look for units with codes that match this specific class
                            $q->where('code', 'like', $program . $level . '%')
                              // Or units with codes that match this program and level
                              ->orWhere('code', 'like', $program . '%' . str_replace('.', '', $level) . '%')
                              // Or units with codes that match common patterns for this level
                              ->orWhere('code', 'like', $program . $majorLevel . '%');
                        });
                    
                        $units = $query->get();
                    
                        if ($units->count() > 0) {
                            Log::info('Found ' . $units->count() . ' units using class level pattern matching');
                            return response()->json($units);
                        }
                    }
                } catch (\Exception $e) {
                    Log::error('Error in pattern matching approach: ' . $e->getMessage());
                }
            }
        
            // Last resort: Return all units with a warning
            $units = Unit::with(['program', 'school'])->get();
        
            Log::warning('No specific units found for class ' . $class->name . '. Returning all ' . $units->count() . ' units.');
        
            return response()->json([
                'units' => $units,
                'warning' => 'No specific units found for this class. Showing all units for the semester.'
            ]);
        
        } catch (\Exception $e) {
            Log::error('Error in getUnitsByClassAndSemester: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            return response()->json(['error' => 'Failed to fetch units: ' . $e->getMessage()], 500);
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
