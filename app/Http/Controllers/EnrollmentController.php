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
use Illuminate\Support\Facades\Auth;
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
        // Only apply authorization to resource methods, not to API methods or self-enrollment
        $this->middleware(function ($request, $next) {
            if (!$request->is('api/*') && !$request->is('enroll')) {
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
            ->paginate(10)
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
        $classes = ClassModel::with('semester')->get();
        $groups = Group::with('class')->get();
        $units = Unit::with(['program', 'school'])->get();

        return inertia('Enrollments/Index', [
            'enrollments' => $enrollments,
            'semesters' => $semesters,
            'classes' => $classes,
            'groups' => $groups,
            'units' => $units,
            'lecturerAssignments' => $lecturerAssignments,
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
        // For self-enrollment, use the authenticated user's code
        $studentCode = $request->has('code') ? $request->code : Auth::user()->code;

        // Validate the incoming request
        $validated = $request->validate([
            'group_id' => 'required|exists:groups,id',
            'code' => 'sometimes|string|exists:users,code', // Optional for self-enrollment
            'unit_ids' => 'required|array',
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
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => "This group is already full. Capacity: {$group->capacity}",
                ], 422);
            }
            return redirect()->back()->withErrors([
                'group_id' => "This group is already full. Capacity: {$group->capacity}",
            ]);
        }

        // Check if the student is already enrolled in the group
        $studentAlreadyEnrolled = Enrollment::where('group_id', $group->id)
            ->where('student_code', $studentCode)
            ->exists();

        if ($studentAlreadyEnrolled) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => 'This student is already enrolled in the selected group.',
                ], 422);
            }
            return redirect()->back()->withErrors([
                'code' => 'This student is already enrolled in the selected group.',
            ]);
        }

        try {
            // Create enrollments for each unit
            foreach ($validated['unit_ids'] as $unitId) {
                Enrollment::create([
                    'student_code' => $studentCode,
                    'group_id' => $group->id,
                    'unit_id' => $unitId,
                    'semester_id' => $validated['semester_id'],
                ]);
            }

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Student enrolled successfully!',
                ]);
            }

            return redirect()->back()->with('success', 'Student enrolled successfully!');
        } catch (\Exception $e) {
            Log::error('Error creating enrollment: ' . $e->getMessage());
            
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => 'Failed to enroll student. Please try again.',
                ], 500);
            }

            return redirect()->back()->withErrors(['error' => 'Failed to enroll student. Please try again.']);
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

            Log::info('Fetching units by class and semester:', $validated);
            
            // Check what tables exist in your database
            $tables = DB::select('SHOW TABLES');
            Log::info('Available tables:', $tables);

            $units = collect();

            // Method 1: Try semester_unit pivot table
            if (Schema::hasTable('semester_unit')) {
                Log::info('Trying semester_unit pivot table...');
                $units = DB::table('semester_unit')
                    ->join('units', 'semester_unit.unit_id', '=', 'units.id')
                    ->where('semester_unit.semester_id', $validated['semester_id'])
                    ->where('semester_unit.class_id', $validated['class_id'])
                    ->select('units.*')
                    ->get();
                Log::info('Units from semester_unit table:', ['count' => $units->count()]);
            }

            // Method 2: Try class_unit pivot table
            if ($units->isEmpty() && Schema::hasTable('class_unit')) {
                Log::info('Trying class_unit pivot table...');
                $units = DB::table('class_unit')
                    ->join('units', 'class_unit.unit_id', '=', 'units.id')
                    ->where('class_unit.class_id', $validated['class_id'])
                    ->select('units.*')
                    ->get();
                Log::info('Units from class_unit table:', ['count' => $units->count()]);
            }

            // Method 3: Try direct relationship on units table
            if ($units->isEmpty()) {
                Log::info('Trying direct relationship on units table...');
                
                // Check if units table has class_id and semester_id columns
                $unitColumns = Schema::getColumnListing('units');
                Log::info('Units table columns:', $unitColumns);
                
                $query = Unit::query();
                
                if (in_array('class_id', $unitColumns)) {
                    $query->where('class_id', $validated['class_id']);
                }
                
                if (in_array('semester_id', $unitColumns)) {
                    $query->where('semester_id', $validated['semester_id']);
                }
                
                $units = $query->get();
                Log::info('Units from direct relationship:', ['count' => $units->count()]);
            }

            // Method 4: Try getting all units and let frontend filter (fallback)
            if ($units->isEmpty()) {
                Log::info('No specific units found, getting all units as fallback...');
                $units = Unit::all();
                Log::info('All units returned as fallback:', ['count' => $units->count()]);
            }

            // Log the actual data structure
            if ($units->isNotEmpty()) {
                Log::info('Sample unit data:', ['first_unit' => $units->first()]);
            }

            return response()->json([
                'success' => true,
                'units' => $units,
                'count' => $units->count(),
                'debug' => [
                    'semester_id' => $validated['semester_id'],
                    'class_id' => $validated['class_id'],
                    'method_used' => $units->isEmpty() ? 'none' : 'found'
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error fetching units for class and semester: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch units. Please try again.',
                'debug' => $e->getMessage()
            ], 500);
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

    /**
     * Debug endpoint to check database structure and data
     */
    public function debugDatabase()
    {
        try {
            $debug = [];
            
            // Check tables
            $tables = DB::select('SHOW TABLES');
            $debug['tables'] = array_map(function($table) {
                return array_values((array)$table)[0];
            }, $tables);
            
            // Check units table structure
            $debug['units_columns'] = Schema::getColumnListing('units');
            $debug['units_count'] = Unit::count();
            $debug['units_sample'] = Unit::take(3)->get();
            
            // Check classes table
            $debug['classes_columns'] = Schema::getColumnListing('classes');
            $debug['classes_count'] = ClassModel::count();
            $debug['classes_sample'] = ClassModel::take(3)->get();
            
            // Check if pivot tables exist
            $debug['has_semester_unit_table'] = Schema::hasTable('semester_unit');
            $debug['has_class_unit_table'] = Schema::hasTable('class_unit');
            
            if (Schema::hasTable('semester_unit')) {
                $debug['semester_unit_columns'] = Schema::getColumnListing('semester_unit');
                $debug['semester_unit_sample'] = DB::table('semester_unit')->take(3)->get();
            }
            
            if (Schema::hasTable('class_unit')) {
                $debug['class_unit_columns'] = Schema::getColumnListing('class_unit');
                $debug['class_unit_sample'] = DB::table('class_unit')->take(3)->get();
            }
            
            return response()->json($debug);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }
    public function showEnrollmentForm()
    {
        $student = Auth::user();
        
        // Get student's current enrollments
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
    public function getUnitsForClass(Request $request)
    {
        $semesterId = $request->input('semester_id');
        $classId = $request->input('class_id');
        
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


// In EnrollmentController.php
public function create()
{
    $semesters = Semester::all();
    $classes = ClassModel::with('semester')->get();
    $groups = Group::with('class')->get();
    $units = Unit::with(['program', 'school'])->get();

    // For admin: return Inertia::render('Enrollments/Create', [...]);
    // For student self-enroll:
    $student = auth()->user(); // or however you get the student
    $enrollments = Enrollment::where('student_code', $student->code)->with(['unit', 'group'])->get();

    return Inertia::render('Student/Enroll', [
        'semesters' => $semesters,
        'classes' => $classes,
        'groups' => $groups,
        'units' => $units,
        'student' => $student,
        'enrollments' => $enrollments,
    ]);
}
}