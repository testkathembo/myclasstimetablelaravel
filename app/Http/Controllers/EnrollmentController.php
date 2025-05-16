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
        // Load enrollments with all related data
        $enrollments = Enrollment::with([
            'student:id,code,first_name,last_name', // Explicitly select student fields
            'unit',    // Load unit relationship
            'group',   // Load group relationship
            'group.class', // Load class through group relationship
            'program', // Load program relationship
            'school',  // Load school relationship
        ])
        ->orderBy('id', 'desc')
        ->paginate(10);

        // Log the first enrollment for debugging
        if ($enrollments->count() > 0) {
            Log::info('First enrollment data for debugging:', [
                'id' => $enrollments[0]->id,
                'student_code' => $enrollments[0]->student_code,
                'student' => $enrollments[0]->student,
                'student_relation_loaded' => $enrollments[0]->relationLoaded('student'),
            ]);
        }

        $semesters = Semester::all(); // Fetch all semesters
        $classes = ClassModel::with('semester')->get(); // Fetch all classes with their semesters
        $groups = Group::with('class')->get(); // Fetch all groups with their classes
        $units = Unit::with(['program', 'school'])->get(); // Fetch all units with their program and school

        return inertia('Enrollments/Index', [
            'enrollments' => $enrollments,
            'semesters' => $semesters,
            'classes' => $classes,
            'groups' => $groups,
            'units' => $units,
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
        // Log the incoming request data for debugging
        Log::info('Enrollment store request data:', $request->all());

        try {
            $validated = $request->validate([
                'group_id' => 'required|exists:groups,id',
                'code' => 'required|string', // Student code
                'unit_id' => 'required|exists:units,id',
                'semester_id' => 'required|exists:semesters,id',
            ]);

            // Trim the student code to remove any whitespace
            $studentCode = trim($validated['code']);
            
            if (empty($studentCode)) {
                Log::error('Empty student code provided');
                return redirect()->back()->withErrors(['code' => 'Student code cannot be empty']);
            }

            // Find the student by code
            $student = User::where('code', $studentCode)->first();

            if (!$student) {
                Log::error('Student not found with code: ' . $studentCode);
                return redirect()->back()->withErrors(['code' => 'Student not found with code: ' . $studentCode]);
            }

            // Log the found student
            Log::info('Found student:', [
                'id' => $student->id,
                'code' => $student->code,
                'name' => $student->first_name . ' ' . $student->last_name,
            ]);

            // Check if student is already enrolled in this unit
            $existingEnrollment = Enrollment::where('student_code', $studentCode)
                ->where('unit_id', $validated['unit_id'])
                ->where('semester_id', $validated['semester_id'])
                ->first();
                
            if ($existingEnrollment) {
                Log::warning('Student already enrolled in this unit', [
                    'student_code' => $studentCode,
                    'unit_id' => $validated['unit_id'],
                    'semester_id' => $validated['semester_id'],
                ]);
                return redirect()->back()->withErrors(['error' => 'Student is already enrolled in this unit for this semester']);
            }

            // Check group capacity - COUNT UNIQUE STUDENTS, not total enrollments
            $group = Group::findOrFail($validated['group_id']);
            
            // Count unique student codes in this group
            $uniqueStudentsCount = Enrollment::where('group_id', $validated['group_id'])
                ->distinct('student_code')
                ->count('student_code');
                
            // Check if the current student is already in this group
            $studentAlreadyInGroup = Enrollment::where('group_id', $validated['group_id'])
                ->where('student_code', $studentCode)
                ->exists();
                
            // Only count this student toward capacity if they're not already in the group
            $effectiveStudentCount = $studentAlreadyInGroup ? $uniqueStudentsCount : $uniqueStudentsCount + 1;

            Log::info('Group capacity check:', [
                'group_id' => $group->id,
                'group_name' => $group->name,
                'unique_students' => $uniqueStudentsCount,
                'student_already_in_group' => $studentAlreadyInGroup,
                'effective_student_count' => $effectiveStudentCount,
                'capacity' => $group->capacity,
                'is_full' => $effectiveStudentCount > $group->capacity
            ]);

            if ($effectiveStudentCount > $group->capacity) {
                return redirect()->back()->withErrors([
                    'group_id' => "This group is already full. Unique students: {$uniqueStudentsCount}, Capacity: {$group->capacity}"
                ]);
            }

            // Get the class associated with the group to find its school
            $class = null;
            if ($group && $group->class_id) {
                $class = ClassModel::with('school')->find($group->class_id);
                Log::info('Class data for enrollment:', [
                    'class_id' => $class ? $class->id : null,
                    'class_name' => $class ? $class->name : null,
                    'school_id' => $class && $class->school ? $class->school->id : null,
                ]);
            }

            try {
                // Get the unit with its program and school
                $unit = Unit::with(['program', 'school'])->find($validated['unit_id']);
                
                if (!$unit) {
                    return redirect()->back()->withErrors(['unit_id' => 'Unit not found.']);
                }
                
                // Log the unit data
                Log::info('Unit data for enrollment:', [
                    'unit_id' => $unit->id,
                    'unit_name' => $unit->name,
                    'program_id' => $unit->program_id ?? ($unit->program ? $unit->program->id : null),
                    'school_id' => $unit->school_id ?? ($unit->school ? $unit->school->id : null),
                ]);

                // Create the enrollment with all required fields
                $enrollment = Enrollment::create([
                    'student_code' => $student->code, // Store the student code
                    'group_id' => $validated['group_id'],
                    'unit_id' => $validated['unit_id'],
                    'semester_id' => $validated['semester_id'],
                    'program_id' => $unit->program_id ?? ($unit->program ? $unit->program->id : null),
                    // Prioritize school from class if available, otherwise use unit's school
                    'school_id' => ($class && $class->school) ? $class->school->id : 
                                  ($unit->school_id ?? ($unit->school ? $unit->school->id : null)),
                ]);

                // Log the created enrollment
                Log::info('Created enrollment:', $enrollment->toArray());

                return redirect()->back()->with('success', 'Student enrolled successfully!');
            } catch (\Exception $e) {
                Log::error('Error creating enrollment: ' . $e->getMessage());
                return redirect()->back()->withErrors(['error' => 'Failed to create enrollment: ' . $e->getMessage()]);
            }
        } catch (\Exception $e) {
            Log::error('Validation error: ' . $e->getMessage());
            return redirect()->back()->withErrors(['error' => 'Validation failed: ' . $e->getMessage()]);
        }
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
                'name' => $class->name,
                'semester_id' => $class->semester_id
            ]);

            // Try multiple approaches to find units
            
            // 1. First try: Check if class_unit pivot table exists and has entries
            $pivotUnits = [];
            try {
                if (Schema::hasTable('class_unit')) {
                    $pivotUnits = DB::table('units')
                        ->join('class_unit', 'units.id', '=', 'class_unit.unit_id')
                        ->where('class_unit.class_id', $validated['class_id'])
                        ->select('units.*')
                        ->get();
                    
                    Log::info('Pivot table approach found ' . count($pivotUnits) . ' units');
                }
            } catch (\Exception $e) {
                Log::warning('Error using pivot table approach: ' . $e->getMessage());
            }
            
            if (count($pivotUnits) > 0) {
                // Convert to Unit models with relationships
                $unitIds = $pivotUnits->pluck('id')->toArray();
                $units = Unit::with(['program', 'school'])->whereIn('id', $unitIds)->get();
                Log::info('Returning ' . count($units) . ' units from pivot table approach');
                return response()->json($units);
            }
            
            // 2. Try using the relationship approach
            try {
                $relationshipUnits = Unit::with(['program', 'school'])
                    ->whereHas('classes', function($query) use ($validated) {
                        $query->where('classes.id', $validated['class_id']);
                    })
                    ->get();
                
                Log::info('Relationship approach found ' . count($relationshipUnits) . ' units');
                
                if (count($relationshipUnits) > 0) {
                    return response()->json($relationshipUnits);
                }
            } catch (\Exception $e) {
                Log::warning('Error using relationship approach: ' . $e->getMessage());
            }
            
            // 3. Try direct column approach if units table has class_id
            try {
                if (Schema::hasColumn('units', 'class_id')) {
                    $directUnits = Unit::with(['program', 'school'])
                        ->where('class_id', $validated['class_id'])
                        ->get();
                    
                    Log::info('Direct column approach found ' . count($directUnits) . ' units');
                    
                    if (count($directUnits) > 0) {
                        return response()->json($directUnits);
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Error using direct column approach: ' . $e->getMessage());
            }
            
            // 4. Fallback: Get units by semester
            $semesterUnits = Unit::with(['program', 'school'])
                ->where('semester_id', $validated['semester_id'])
                ->get();
            
            Log::info('Fallback to semester approach found ' . count($semesterUnits) . ' units');
            
            // 5. Special case for BBIT 1.1 class
            if ($class && $class->name === 'BBIT 1.1') {
                // Filter units that might be for BBIT 1.1 based on their names or codes
                $bbitUnits = Unit::with(['program', 'school'])
                    ->where(function($query) {
                        $query->where('name', 'like', '%Introduction to Information Technology%')
                              ->orWhere('name', 'like', '%Database Systems%')
                              ->orWhere('name', 'like', '%Software Engineering%')
                              ->orWhere('name', 'like', '%Programming%')
                              ->orWhere('name', 'like', '%Computer Science%')
                              ->orWhere('code', 'like', 'BIT%')
                              ->orWhere('code', 'like', 'CS%');
                    })
                    ->get();
                
                Log::info('BBIT 1.1 special case found ' . count($bbitUnits) . ' units');
                
                if (count($bbitUnits) > 0) {
                    return response()->json($bbitUnits);
                }
            }
            
            // If we still have no units, return the semester units as fallback
            if (count($semesterUnits) > 0) {
                return response()->json($semesterUnits);
            }
            
            // Last resort: return all units
            $allUnits = Unit::with(['program', 'school'])->get();
            Log::info('Last resort: returning all ' . count($allUnits) . ' units');
            
            return response()->json($allUnits);
            
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
}
