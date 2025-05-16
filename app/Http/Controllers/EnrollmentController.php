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

            // 1. First approach: Check if we have a direct relationship in the database
            // This could be a many-to-many relationship between classes and units
            
            // Check if class_unit pivot table exists
            if (Schema::hasTable('class_unit')) {
                $units = Unit::with(['program', 'school'])
                    ->join('class_unit', 'units.id', '=', 'class_unit.unit_id')
                    ->where('class_unit.class_id', $validated['class_id'])
                    ->select('units.*')
                    ->get();
                
                if (count($units) > 0) {
                    Log::info('Found ' . count($units) . ' units using class_unit pivot table');
                    return response()->json($units);
                }
            }
            
            // 2. Second approach: Check if there's a semester_unit_class table
            if (Schema::hasTable('semester_unit_class')) {
                $units = Unit::with(['program', 'school'])
                    ->join('semester_unit_class', 'units.id', '=', 'semester_unit_class.unit_id')
                    ->where('semester_unit_class.class_id', $validated['class_id'])
                    ->where('semester_unit_class.semester_id', $validated['semester_id'])
                    ->select('units.*')
                    ->get();
                
                if (count($units) > 0) {
                    Log::info('Found ' . count($units) . ' units using semester_unit_class table');
                    return response()->json($units);
                }
            }
            
            // 3. Third approach: Check if units have a direct class_id column
            if (Schema::hasColumn('units', 'class_id')) {
                $units = Unit::with(['program', 'school'])
                    ->where('class_id', $validated['class_id'])
                    ->get();
                
                if (count($units) > 0) {
                    Log::info('Found ' . count($units) . ' units using direct class_id column');
                    return response()->json($units);
                }
            }
            
            // 4. Fourth approach: Look for units that have been previously enrolled for this class
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
                
                if (count($units) > 0) {
                    Log::info('Found ' . count($units) . ' units from previous enrollments');
                    return response()->json($units);
                }
            }
            
            // 5. Fifth approach: Check if there's a specific table for this class
            // For example, if the class is "BBIT 1.1", check for a table named "bbit_1_1_units"
            $className = strtolower(str_replace([' ', '.'], ['_', '_'], $class->name));
            $tableName = $className . '_units';
            
            if (Schema::hasTable($tableName)) {
                $units = DB::table($tableName)
                    ->join('units', $tableName . '.unit_id', '=', 'units.id')
                    ->select('units.*')
                    ->get();
                
                if (count($units) > 0) {
                    Log::info('Found ' . count($units) . ' units using ' . $tableName . ' table');
                    // Convert to Unit models with relationships
                    $unitIds = collect($units)->pluck('id')->toArray();
                    $units = Unit::with(['program', 'school'])->whereIn('id', $unitIds)->get();
                    return response()->json($units);
                }
            }
            
            // 6. Sixth approach: Check if there's a view for this class
            $viewName = 'view_' . $className . '_units';
            
            try {
                $units = DB::table($viewName)->get();
                if (count($units) > 0) {
                    Log::info('Found ' . count($units) . ' units using ' . $viewName . ' view');
                    // Convert to Unit models with relationships
                    $unitIds = collect($units)->pluck('id')->toArray();
                    $units = Unit::with(['program', 'school'])->whereIn('id', $unitIds)->get();
                    return response()->json($units);
                }
            } catch (\Exception $e) {
                Log::warning('Error using view ' . $viewName . ': ' . $e->getMessage());
            }
            
            // 7. Seventh approach: Check if there's a stored procedure for this class
            try {
                $units = DB::select("CALL get_units_for_class(?, ?)", [$validated['class_id'], $validated['semester_id']]);
                if (count($units) > 0) {
                    Log::info('Found ' . count($units) . ' units using stored procedure');
                    // Convert to Unit models with relationships
                    $unitIds = collect($units)->pluck('id')->toArray();
                    $units = Unit::with(['program', 'school'])->whereIn('id', $unitIds)->get();
                    return response()->json($units);
                }
            } catch (\Exception $e) {
                Log::warning('Error using stored procedure: ' . $e->getMessage());
            }
            
            // 8. Eighth approach: If the class name contains a pattern like "BBIT 1.1", 
            // try to find units that match the class level
            if (preg_match('/(\w+)\s+(\d+\.\d+)/', $class->name, $matches)) {
                $program = $matches[1]; // e.g., "BBIT"
                $level = $matches[2];   // e.g., "1.1"
                
                // Extract the major level (e.g., "1" from "1.1")
                $majorLevel = explode('.', $level)[0];
                
                try {
                    // First check if the 'code' column exists
                    if (Schema::hasColumn('units', 'code')) {
                        // Look for units with codes that might match this class level
                        $query = Unit::with(['program', 'school']);
                        
                        $query->where(function($q) use ($program, $majorLevel) {
                            // Look for units with codes that start with the program and contain the level
                            $q->where('code', 'like', $program . '%' . $majorLevel . '%')
                              // Or units with codes that match common patterns for this level
                              ->orWhere('code', 'like', 'BIT' . $majorLevel . '%')
                              ->orWhere('code', 'like', 'CS' . $majorLevel . '%');
                        });
                        
                        // Only add semester_id condition if the column exists
                        if (Schema::hasColumn('units', 'semester_id')) {
                            $query->where('semester_id', $validated['semester_id']);
                        }
                        
                        $units = $query->get();
                        
                        if (count($units) > 0) {
                            Log::info('Found ' . count($units) . ' units using class level pattern matching');
                            return response()->json($units);
                        }
                    } else {
                        // If 'code' column doesn't exist, try to match by name instead
                        $query = Unit::with(['program', 'school']);
                        
                        // Try to find units by name patterns that might match this class
                        $query->where(function($q) use ($program, $majorLevel) {
                            $q->where('name', 'like', '%' . $program . '%')
                              ->orWhere('name', 'like', '%Level ' . $majorLevel . '%')
                              ->orWhere('name', 'like', '%Year ' . $majorLevel . '%')
                              ->orWhere('name', 'like', '%' . $majorLevel . '%');
                        });
                        
                        // Only add semester_id condition if the column exists
                        if (Schema::hasColumn('units', 'semester_id')) {
                            $query->where('semester_id', $validated['semester_id']);
                        }
                        
                        $units = $query->get();
                        
                        if (count($units) > 0) {
                            Log::info('Found ' . count($units) . ' units using name pattern matching');
                            return response()->json($units);
                        }
                    }
                } catch (\Exception $e) {
                    Log::error('Error in pattern matching approach: ' . $e->getMessage());
                    // Continue to next approach
                }
            }
            
            // 9. Last resort: Return all units for this semester with a warning
            $units = Unit::with(['program', 'school'])
                ->where('semester_id', $validated['semester_id'])
                ->get();
            
            Log::warning('No specific units found for class ' . $class->name . '. Returning all ' . count($units) . ' units for semester ' . $validated['semester_id']);
            
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
}
