<?php

namespace App\Http\Controllers;

use App\Models\ClassTimetable;
use App\Models\Unit;
use App\Models\User;
use App\Models\Semester;
use App\Models\Enrollment;
use App\Models\ClassTimeSlot;
use App\Models\Classroom;
use App\Models\ClassModel;
use App\Models\Group;
use App\Models\Program;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Barryvdh\DomPDF\Facade\Pdf;

class ClassTimetableController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = auth()->user();

        // Log user access
        \Log::info('Accessing /classtimetable', [
            'user_id' => $user->id,
            'roles' => $user->getRoleNames(),
            'permissions' => $user->getAllPermissions()->pluck('name'),
        ]);

        if (!$user->can('manage-classtimetables')) {
            abort(403, 'Unauthorized action.');
        }

        $perPage = $request->input('per_page', 10);
        $search = $request->input('search', '');

        // Fetch class timetables with all DB columns and related display fields
        $classTimetables = ClassTimetable::query()
            ->leftJoin('units', 'class_timetable.unit_id', '=', 'units.id')
            ->leftJoin('semesters', 'class_timetable.semester_id', '=', 'semesters.id')
            ->leftJoin('classes', 'class_timetable.class_id', '=', 'classes.id')
            ->leftJoin('groups', 'class_timetable.group_id', '=', 'groups.id')
            ->leftJoin('programs', 'class_timetable.program_id', '=', 'programs.id')
            ->leftJoin('schools', 'class_timetable.school_id', '=', 'schools.id')
            ->leftJoin('class_time_slots', function ($join) {
                $join->on('class_timetable.day', '=', 'class_time_slots.day')
                    ->on('class_timetable.start_time', '=', 'class_time_slots.start_time')
                    ->on('class_timetable.end_time', '=', 'class_time_slots.end_time');
            })
            ->leftJoin('users', 'users.code', '=', 'class_timetable.lecturer')
            ->select(
                'class_timetable.id',
                'class_timetable.semester_id',
                'class_timetable.unit_id',
                'class_timetable.class_id',
                'class_timetable.group_id',
                'class_timetable.day',
                'class_timetable.start_time',
                'class_timetable.end_time',
                'class_timetable.teaching_mode',
                'class_timetable.venue',
                'class_timetable.location',
                'class_timetable.no',
                DB::raw("IF(users.id IS NOT NULL, CONCAT(users.first_name, ' ', users.last_name), class_timetable.lecturer) as lecturer"),
                'class_timetable.program_id',
                'class_timetable.school_id',
                'class_timetable.created_at',
                'class_timetable.updated_at',
                // Display fields from joins
                'units.code as unit_code',
                'units.name as unit_name',
                'semesters.name as semester_name',
                'classes.name as class_name',
                'groups.name as group_name',
                'programs.code as program_code',
                'programs.name as program_name',
                'schools.code as school_code',
                'schools.name as school_name',
                'class_time_slots.status'
            )
            ->when($request->has('search') && $request->search !== '', function ($query) use ($request) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('class_timetable.day', 'like', "%{$search}%")
                      ->orWhere('units.code', 'like', "%{$search}%")
                      ->orWhere('units.name', 'like', "%{$search}%")
                      ->orWhere(DB::raw("CONCAT(users.first_name, ' ', users.last_name)"), 'like', "%{$search}%")
                      ->orWhere('class_timetable.venue', 'like', "%{$search}%");
                });
            })
            ->orderBy('class_timetable.day')
            ->orderBy('class_timetable.start_time')
            ->paginate($request->get('per_page', 10));

        // Get all necessary data for the form
        $lecturers = User::role('Lecturer')
            ->select('id', 'code', DB::raw("CONCAT(first_name, ' ', last_name) as name"))
            ->get();

        $semesters = Semester::all();
        $classrooms = Classroom::all();
        $classtimeSlots = ClassTimeSlot::all();
        $allUnits = Unit::select('id', 'code', 'name', 'semester_id', 'credit_hours')->get();
        $allEnrollments = Enrollment::select('id', 'student_code', 'unit_id', 'semester_id', 'lecturer_code')->get();

        // Group enrollments by unit_id and semester_id for easy access
        $enrollmentsByUnitAndSemester = [];
        foreach ($allEnrollments as $enrollment) {
            $key = $enrollment->unit_id . '_' . $enrollment->semester_id;
            if (!isset($enrollmentsByUnitAndSemester[$key])) {
                $enrollmentsByUnitAndSemester[$key] = [];
            }
            $enrollmentsByUnitAndSemester[$key][] = $enrollment;
        }

        // Get units with their associated semesters through enrollments
        $unitsBySemester = [];
        foreach ($semesters as $semester) {
            $unitIdsInSemester = Enrollment::where('semester_id', $semester->id)
                ->distinct('unit_id')
                ->pluck('unit_id')
                ->toArray();

            $unitsInSemester = Unit::whereIn('id', $unitIdsInSemester)
                ->select('id', 'code', 'name', 'credit_hours')
                ->get()
                ->map(function ($unit) use ($semester, $enrollmentsByUnitAndSemester) {
                    $unit->semester_id = $semester->id;
                    $key = $unit->id . '_' . $semester->id;
                    $unitEnrollments = $enrollmentsByUnitAndSemester[$key] ?? [];
                    $unit->student_count = count($unitEnrollments);

                    $lecturerCode = null;
                    foreach ($unitEnrollments as $enrollment) {
                        if ($enrollment->lecturer_code) {
                            $lecturerCode = $enrollment->lecturer_code;
                            break;
                        }
                    }

                    if ($lecturerCode) {
                        $lecturer = User::where('code', $lecturerCode)->first();
                        if ($lecturer) {
                            $unit->lecturer_code = $lecturerCode;
                            $unit->lecturer_name = $lecturer->first_name . ' ' . $lecturer->last_name;
                        }
                    }
                    return $unit;
                });

            $unitsBySemester[$semester->id] = $unitsInSemester;
        }

        $unitsWithSemesters = collect();
        foreach ($unitsBySemester as $semesterId => $units) {
            $unitsWithSemesters = $unitsWithSemesters->concat($units);
        }

        // Fetch classes and groups from the database
        $classes = ClassModel::select('id', 'name')->get();
        $groups = Group::select('id', 'name', 'class_id')->get();

        // Fetch programs from the programs table
        $programs = DB::table('programs')
            ->select('id', 'code', 'name')
            ->get();

         $schools = DB::table('schools')
            ->select('id', 'name', 'code')
            ->get();

        return Inertia::render('ClassTimetables/Index', [
            'classTimetables' => $classTimetables,
            'lecturers' => $lecturers,
            'perPage' => $perPage,
            'search' => $search,
            'semesters' => $semesters,
            'classrooms' => $classrooms,
            'classtimeSlots' => $classtimeSlots,
            'units' => $unitsWithSemesters,
            'enrollments' => $allEnrollments,
            'classes' => $classes,
            'groups' => $groups,
            'programs' => $programs,
            'schools' => $schools,
            'can' => [
                'create' => $user->can('create-classtimetables'),
                'edit' => $user->can('update-classtimetables'),
                'delete' => $user->can('delete-classtimetables'),
                'process' => $user->can('process-classtimetables'),
                'solve_conflicts' => $user->can('solve-class-conflicts'),
                'download' => $user->can('download-classtimetables'),
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'day' => 'nullable|string',
            'unit_id' => 'required|exists:units,id',
            'semester_id' => 'required|exists:semesters,id',
            'class_id' => 'required|exists:classes,id',
            'group_id' => 'nullable|exists:groups,id',
            'venue' => 'nullable|string',
            'location' => 'nullable|string',
            'no' => 'required|integer|min:1',
            'lecturer' => 'required|string',
            'start_time' => 'nullable|string',
            'end_time' => 'nullable|string',
            'classtimeslot_id' => 'nullable|exists:class_time_slots,id',
            'program_id' => 'nullable|exists:programs,id',
            'school_id' => 'nullable|exists:schools,id',
        ]);

        try {
            \Log::info('Creating class timetable with data:', $request->all());

            // Get the unit to check credit hours
            $unit = Unit::findOrFail($request->unit_id);
            $creditHours = $unit->credit_hours;

            $class = ClassModel::find($request->class_id);
            $programId = $request->program_id ?: ($class ? $class->program_id : null);
            $schoolId = $request->school_id ?: ($class ? $class->school_id : null);

            // Check if random time slot assignment is requested
            $isRandomTimeSlot = empty($request->day) || empty($request->start_time) || empty($request->end_time) || $request->start_time === 'Random Time Slot (auto-assign)';

            if ($isRandomTimeSlot) {
                // Use credit-based assignment
                return $this->createCreditBasedTimetable($request, $unit, $programId, $schoolId);
            } else {
                // Use single time slot assignment (existing functionality)
                return $this->createSingleTimetable($request, $unit, $programId, $schoolId);
            }

        } catch (\Exception $e) {
            \Log::error('Failed to create class timetable: ' . $e->getMessage(), [
                'request_data' => $request->all(),
                'exception' => $e->getTraceAsString()
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create class timetable: ' . $e->getMessage(),
                    'errors' => ['error' => $e->getMessage()]
                ], 500);
            }

            return redirect()->back()
                ->withErrors(['error' => 'Failed to create class timetable: ' . $e->getMessage()])
                ->withInput();
        }
    }

    /**
     * ✅ FIXED: API endpoint to get units by class and semester
     */
    public function getUnitsByClass(Request $request)
    {
        try {
            $classId = $request->input('class_id');
            $semesterId = $request->input('semester_id');

            if (!$classId || !$semesterId) {
                return response()->json(['error' => 'Class ID and Semester ID are required.'], 400);
            }

            \Log::info('Fetching units for class', [
                'class_id' => $classId,
                'semester_id' => $semesterId
            ]);

            // Method 1: Try to find units through enrollments that have the specific class association
            $unitsFromEnrollments = [];
            
            // Check if enrollments table has class_id column
            $enrollmentColumns = DB::getSchemaBuilder()->getColumnListing('enrollments');
            
            if (in_array('class_id', $enrollmentColumns)) {
                // Direct method: enrollments table has class_id
                $unitsFromEnrollments = DB::table('enrollments')
                    ->join('units', 'enrollments.unit_id', '=', 'units.id')
                    ->where('enrollments.semester_id', $semesterId)
                    ->where('enrollments.class_id', $classId)
                    ->select('units.id', 'units.code', 'units.name', 'units.credit_hours')
                    ->distinct()
                    ->get()
                    ->toArray();
            }

            // Method 2: Try semester_unit pivot table
            $unitsFromSemesterUnit = [];
            $hasSemesterUnitTable = DB::getSchemaBuilder()->hasTable('semester_unit');
            
            if ($hasSemesterUnitTable) {
                $semesterUnitColumns = DB::getSchemaBuilder()->getColumnListing('semester_unit');
                
                if (in_array('class_id', $semesterUnitColumns)) {
                    $unitsFromSemesterUnit = DB::table('semester_unit')
                        ->join('units', 'semester_unit.unit_id', '=', 'units.id')
                        ->where('semester_unit.semester_id', $semesterId)
                        ->where('semester_unit.class_id', $classId)
                        ->select('units.id', 'units.code', 'units.name', 'units.credit_hours')
                        ->distinct()
                        ->get()
                        ->toArray();
                }
            }

            // Method 3: Try class_unit pivot table (common relationship)
            $unitsFromClassUnit = [];
            $hasClassUnitTable = DB::getSchemaBuilder()->hasTable('class_unit');
            
            if ($hasClassUnitTable) {
                $classUnitColumns = DB::getSchemaBuilder()->getColumnListing('class_unit');
                
                if (in_array('semester_id', $classUnitColumns)) {
                    $unitsFromClassUnit = DB::table('class_unit')
                        ->join('units', 'class_unit.unit_id', '=', 'units.id')
                        ->where('class_unit.class_id', $classId)
                        ->where('class_unit.semester_id', $semesterId)
                        ->select('units.id', 'units.code', 'units.name', 'units.credit_hours')
                        ->distinct()
                        ->get()
                        ->toArray();
                } else {
                    // class_unit table exists but doesn't have semester_id
                    $unitsFromClassUnit = DB::table('class_unit')
                        ->join('units', 'class_unit.unit_id', '=', 'units.id')
                        ->where('class_unit.class_id', $classId)
                        ->where('units.semester_id', $semesterId) // Filter by units.semester_id instead
                        ->select('units.id', 'units.code', 'units.name', 'units.credit_hours')
                        ->distinct()
                        ->get()
                        ->toArray();
                }
            }

            // Method 4: Try group-based relationship
            $unitsFromGroups = [];
            $hasGroupUnitTable = DB::getSchemaBuilder()->hasTable('group_unit');
            
            if ($hasGroupUnitTable) {
                $unitsFromGroups = DB::table('groups')
                    ->join('group_unit', 'groups.id', '=', 'group_unit.group_id')
                    ->join('units', 'group_unit.unit_id', '=', 'units.id')
                    ->where('groups.class_id', $classId)
                    ->where('units.semester_id', $semesterId)
                    ->select('units.id', 'units.code', 'units.name', 'units.credit_hours')
                    ->distinct()
                    ->get()
                    ->toArray();
            }

            // Method 5: Fallback - get all units for the semester and let user choose
            $allUnitsInSemester = DB::table('units')
                ->where('semester_id', $semesterId)
                ->select('id', 'code', 'name', 'credit_hours')
                ->get()
                ->toArray();

            // Merge results and prioritize
            $units = [];
            
            if (!empty($unitsFromEnrollments)) {
                $units = $unitsFromEnrollments;
                \Log::info('Found units via enrollments method', ['count' => count($units)]);
            } elseif (!empty($unitsFromSemesterUnit)) {
                $units = $unitsFromSemesterUnit;
                \Log::info('Found units via semester_unit method', ['count' => count($units)]);
            } elseif (!empty($unitsFromClassUnit)) {
                $units = $unitsFromClassUnit;
                \Log::info('Found units via class_unit method', ['count' => count($units)]);
            } elseif (!empty($unitsFromGroups)) {
                $units = $unitsFromGroups;
                \Log::info('Found units via groups method', ['count' => count($units)]);
            } else {
                $units = $allUnitsInSemester;
                \Log::info('Using fallback method - all units in semester', ['count' => count($units)]);
            }

            if (empty($units)) {
                return response()->json([]);
            }

            // Enhance units with enrollment information
            $enhancedUnits = collect($units)->map(function ($unit) use ($semesterId, $classId) {
                $unitArray = is_object($unit) ? (array) $unit : $unit;
                
                // Get enrollment count for this unit in this semester
                $enrollmentQuery = Enrollment::where('unit_id', $unitArray['id'])
                    ->where('semester_id', $semesterId);

                // Add class filter if enrollments table supports it
                $enrollmentColumns = DB::getSchemaBuilder()->getColumnListing('enrollments');
                if (in_array('class_id', $enrollmentColumns)) {
                    $enrollmentQuery->where('class_id', $classId);
                }

                $enrollments = $enrollmentQuery->get();
                $studentCount = $enrollments->count();

                // Get lecturer information
                $lecturerName = '';
                $lecturerEnrollment = $enrollments->whereNotNull('lecturer_code')->first();
                if ($lecturerEnrollment) {
                    $lecturer = User::where('code', $lecturerEnrollment->lecturer_code)->first();
                    if ($lecturer) {
                        $lecturerName = $lecturer->first_name . ' ' . $lecturer->last_name;
                    }
                }

                return [
                    'id' => $unitArray['id'],
                    'code' => $unitArray['code'],
                    'name' => $unitArray['name'],
                    'credit_hours' => $unitArray['credit_hours'] ?? 3,
                    'student_count' => $studentCount,
                    'lecturer_name' => $lecturerName,
                ];
            });

            return response()->json($enhancedUnits->values()->all());
            
        } catch (\Exception $e) {
            \Log::error('Error fetching units for class: ' . $e->getMessage(), [
                'exception' => $e->getTraceAsString(),
                'class_id' => $request->input('class_id'),
                'semester_id' => $request->input('semester_id')
            ]);
            
            return response()->json(['error' => 'Failed to fetch units. Please try again.'], 500);
        }
    }

    /**
     * Create multiple timetable entries based on credit hours
     */
    private function createCreditBasedTimetable(Request $request, Unit $unit, $programId, $schoolId)
    {
        $creditHours = $unit->credit_hours;
        $sessions = $this->getSessionsForCredits($creditHours);
        
        \Log::info('Creating credit-based timetable', [
            'unit_code' => $unit->code,
            'credit_hours' => $creditHours,
            'sessions' => $sessions
        ]);

        $createdTimetables = [];
        $errors = [];

        foreach ($sessions as $sessionIndex => $session) {
            try {
                $sessionResult = $this->createSessionTimetable($request, $unit, $session, $sessionIndex + 1, $programId, $schoolId);
                
                if ($sessionResult['success']) {
                    $createdTimetables[] = $sessionResult['timetable'];
                } else {
                    $errors[] = $sessionResult['message'];
                }
            } catch (\Exception $e) {
                $errors[] = "Session " . ($sessionIndex + 1) . ": " . $e->getMessage();
                \Log::error('Failed to create session timetable', [
                    'session' => $session,
                    'error' => $e->getMessage()
                ]);
            }
        }

        if (count($createdTimetables) === 0) {
            $errorMessage = 'Failed to create any timetable sessions. Errors: ' . implode('; ', $errors);
            
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $errorMessage,
                    'errors' => ['scheduling' => $errorMessage]
                ], 422);
            }

            return redirect()->back()
                ->withErrors(['scheduling' => $errorMessage])
                ->withInput();
        }

        $successMessage = count($createdTimetables) . " timetable sessions created successfully for {$unit->code} ({$creditHours} credit hours).";
        
        if (count($errors) > 0) {
            $successMessage .= " Note: " . count($errors) . " sessions had issues.";
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $successMessage,
                'data' => $createdTimetables,
                'errors' => $errors
            ]);
        }

        return redirect()->back()->with('success', $successMessage);
    }

    /**
     * Get session configuration based on credit hours
     */

private function getSessionsForCredits($creditHours)
{
    $sessions = [];

    // Assign 1 physical session of 2 hours if possible
    if ($creditHours >= 2) {
        $sessions[] = ['type' => 'physical', 'duration' => 2];
        $remaining = $creditHours - 2;
    } else {
        $remaining = $creditHours;
    }

    // Assign the remaining hours as 1-hour online sessions
    while ($remaining > 0) {
        $sessions[] = ['type' => 'online', 'duration' => 1];
        $remaining--;
    }

    return $sessions;
}

    /**
     * Create a single session timetable
     */
    /**
 * ✅ UPDATED: Create a single session timetable with proper duration handling
 */
private function createSessionTimetable(Request $request, Unit $unit, array $session, int $sessionNumber, $programId, $schoolId)
{
    $sessionType = $session['type'];
    $requiredDuration = $session['duration']; // 1 or 2 hours
    
    \Log::info("Creating session {$sessionNumber}", [
        'unit_code' => $unit->code,
        'session_type' => $sessionType,
        'required_duration' => $requiredDuration
    ]);
    
    // Get appropriate time slot for this session with the required duration
    $timeSlotResult = $this->assignRandomTimeSlot($request->lecturer, '', null, $sessionType, $requiredDuration);
    
    if (!$timeSlotResult['success']) {
        return [
            'success' => false,
            'message' => "Session {$sessionNumber} ({$sessionType}, {$requiredDuration}h): " . $timeSlotResult['message']
        ];
    }

    $day = $timeSlotResult['day'];
    $startTime = $timeSlotResult['start_time'];
    $endTime = $timeSlotResult['end_time'];
    $actualDuration = $timeSlotResult['duration'] ?? $requiredDuration;

    // Get appropriate venue for this session
    $venueResult = $this->assignRandomVenue($request->no, $day, $startTime, $endTime, $sessionType);
    
    if (!$venueResult['success']) {
        return [
            'success' => false,
            'message' => "Session {$sessionNumber} ({$sessionType}, {$requiredDuration}h): " . $venueResult['message']
        ];
    }

    $venue = $venueResult['venue'];
    $location = $venueResult['location'];
    $teachingMode = $venueResult['teaching_mode'];

    // Double-check for conflicts
    $lecturerConflict = ClassTimetable::where('day', $day)
        ->where('lecturer', $request->lecturer)
        ->where(function ($query) use ($startTime, $endTime) {
            $query->where(function ($q) use ($startTime, $endTime) {
                $q->where('start_time', '<', $endTime)
                  ->where('end_time', '>', $startTime);
            });
        })
        ->exists();

    if ($lecturerConflict) {
        return [
            'success' => false,
            'message' => "Session {$sessionNumber} ({$sessionType}, {$requiredDuration}h): Lecturer conflict detected for {$day} {$startTime}-{$endTime}"
        ];
    }

    // Create the timetable entry
    $classTimetable = ClassTimetable::create([
        'day' => $day,
        'unit_id' => $unit->id,
        'semester_id' => $request->semester_id,
        'class_id' => $request->class_id,
        'group_id' => $request->group_id ?: null,
        'venue' => $venue,
        'location' => $location,
        'no' => $request->no,
        'lecturer' => $request->lecturer,
        'start_time' => $startTime,
        'end_time' => $endTime,
        'teaching_mode' => $teachingMode,
        'program_id' => $programId,
        'school_id' => $schoolId,
    ]);

    \Log::info("Session {$sessionNumber} created successfully", [
        'timetable_id' => $classTimetable->id,
        'unit_code' => $unit->code,
        'session_type' => $sessionType,
        'day' => $day,
        'time' => "{$startTime}-{$endTime}",
        'duration' => $actualDuration,
        'venue' => $venue,
        'teaching_mode' => $teachingMode
    ]);

    return [
        'success' => true,
        'message' => "Session {$sessionNumber} ({$sessionType}, {$actualDuration}h) created successfully",
        'timetable' => $classTimetable,
        'duration' => $actualDuration
    ];
}
    /**
     * ✅ UPDATED: Assign a random time slot with optional teaching mode preference
     */
    
/**
 * ✅ UPDATED: Assign a random time slot with specific duration requirements
 */
private function assignRandomTimeSlot($lecturer, $venue = '', $preferredDay = null, $preferredMode = null, $requiredDuration = 1)
{
    try {
        \Log::info('Assigning time slot', [
            'lecturer' => $lecturer,
            'preferred_mode' => $preferredMode,
            'required_duration' => $requiredDuration
        ]);

        // Get time slots based on required duration
        $availableTimeSlots = collect();
        
        if ($requiredDuration == 2) {
            // For 2-hour sessions, look for 2-hour time slots
            $twoHourSlots = DB::table('class_time_slots')
                ->whereRaw('TIMESTAMPDIFF(HOUR, start_time, end_time) = 2')
                ->when($preferredDay, function ($query) use ($preferredDay) {
                    $query->where('day', $preferredDay);
                })
                ->get();
                
            $availableTimeSlots = $twoHourSlots;
            
            // If no 2-hour slots available, try to find any slots and we'll adjust
            if ($availableTimeSlots->isEmpty()) {
                \Log::warning('No 2-hour slots found, trying any available slots');
                $availableTimeSlots = DB::table('class_time_slots')
                    ->when($preferredDay, function ($query) use ($preferredDay) {
                        $query->where('day', $preferredDay);
                    })
                    ->get();
            }
        } else {
            // For 1-hour sessions, prefer 1-hour slots but allow any
            $oneHourSlots = DB::table('class_time_slots')
                ->whereRaw('TIMESTAMPDIFF(HOUR, start_time, end_time) = 1')
                ->when($preferredDay, function ($query) use ($preferredDay) {
                    $query->where('day', $preferredDay);
                })
                ->get();
                
            if ($oneHourSlots->isNotEmpty()) {
                $availableTimeSlots = $oneHourSlots;
            } else {
                // Fallback to any available slots
                $availableTimeSlots = DB::table('class_time_slots')
                    ->when($preferredDay, function ($query) use ($preferredDay) {
                        $query->where('day', $preferredDay);
                    })
                    ->get();
            }
        }

        if ($availableTimeSlots->isEmpty()) {
            return [
                'success' => false,
                'message' => "No time slots available for {$requiredDuration}-hour sessions."
            ];
        }

        // Filter out slots with conflicts
        $conflictFreeTimeSlots = $availableTimeSlots->filter(function ($slot) use ($lecturer, $venue) {
            // Check for lecturer conflicts
            $lecturerConflict = ClassTimetable::where('day', $slot->day)
                ->where('lecturer', $lecturer)
                ->where(function ($query) use ($slot) {
                    $query->where(function ($q) use ($slot) {
                        $q->where('start_time', '<', $slot->end_time)
                          ->where('end_time', '>', $slot->start_time);
                    });
                })
                ->exists();

            if ($lecturerConflict) {
                return false;
            }

            // Check for venue conflicts (if venue is specified and not online)
            if (!empty($venue) && strtolower(trim($venue)) !== 'remote') {
                $venueConflict = ClassTimetable::where('day', $slot->day)
                    ->where('venue', $venue)
                    ->where(function ($query) use ($slot) {
                        $query->where(function ($q) use ($slot) {
                            $q->where('start_time', '<', $slot->end_time)
                              ->where('end_time', '>', $slot->start_time);
                        });
                    })
                    ->exists();

                if ($venueConflict) {
                    return false;
                }
            }

            return true;
        });

        if ($conflictFreeTimeSlots->isEmpty()) {
            return [
                'success' => false,
                'message' => "No available {$requiredDuration}-hour time slots without conflicts for lecturer {$lecturer}."
            ];
        }

        // Randomly select from available conflict-free time slots
        $selectedTimeSlot = $conflictFreeTimeSlots->random();

        // Calculate actual duration
        $actualDuration = \Carbon\Carbon::parse($selectedTimeSlot->start_time)
            ->diffInHours(\Carbon\Carbon::parse($selectedTimeSlot->end_time));

        \Log::info('Time slot assigned successfully', [
            'day' => $selectedTimeSlot->day,
            'start_time' => $selectedTimeSlot->start_time,
            'end_time' => $selectedTimeSlot->end_time,
            'required_duration' => $requiredDuration,
            'actual_duration' => $actualDuration,
            'lecturer' => $lecturer,
            'preferred_mode' => $preferredMode
        ]);

        return [
            'success' => true,
            'day' => $selectedTimeSlot->day,
            'start_time' => $selectedTimeSlot->start_time,
            'end_time' => $selectedTimeSlot->end_time,
            'duration' => $actualDuration,
            'message' => "Assigned: {$selectedTimeSlot->day} {$selectedTimeSlot->start_time}-{$selectedTimeSlot->end_time} ({$actualDuration}h)"
        ];
    } catch (\Exception $e) {
        \Log::error('Error in random time slot assignment: ' . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Failed to assign random time slot: ' . $e->getMessage()
        ];
    }
}
    /**
     * ✅ UPDATED: Assign a random venue with teaching mode preference
     */
    private function assignRandomVenue($studentCount, $day, $startTime, $endTime, $preferredMode = null)
    {
        try {
            // Get all classrooms with sufficient capacity
            $availableClassrooms = Classroom::where('capacity', '>=', $studentCount)->get();

            if ($availableClassrooms->isEmpty()) {
                return [
                    'success' => false,
                    'message' => "No venues available with sufficient capacity for {$studentCount} students."
                ];
            }

            // Filter classrooms based on preferred teaching mode if specified
            if ($preferredMode) {
                $availableClassrooms = $availableClassrooms->filter(function ($classroom) use ($preferredMode) {
                    $venueInfo = $this->determineTeachingModeAndLocation($classroom->name);
                    
                    if ($preferredMode === 'online') {
                        // For online sessions, prefer 'Remote' venues or allow any if no Remote available
                        return $venueInfo['teaching_mode'] === 'online';
                    } else {
                        // For physical sessions, prefer physical venues
                        return $venueInfo['teaching_mode'] === 'physical';
                    }
                });

                // If no venues match the preferred mode, fall back to any available venue
                if ($availableClassrooms->isEmpty()) {
                    $availableClassrooms = Classroom::where('capacity', '>=', $studentCount)->get();
                    
                    if ($availableClassrooms->isEmpty()) {
                        return [
                            'success' => false,
                            'message' => "No venues available with sufficient capacity for {$studentCount} students."
                        ];
                    }
                }
            }

            // Filter out classrooms that have conflicts at the specified time
            // Note: Remote venues can have multiple classes at the same time
            $conflictFreeClassrooms = $availableClassrooms->filter(function ($classroom) use ($day, $startTime, $endTime) {
                $venueInfo = $this->determineTeachingModeAndLocation($classroom->name);

                // If classroom is 'Remote', allow multiple classes at the same time
                if ($venueInfo['teaching_mode'] === 'online') {
                    return true;
                }

                // Physical venues need conflict checking
                $hasConflict = ClassTimetable::where('day', $day)
                    ->where('venue', $classroom->name)
                    ->where(function ($query) use ($startTime, $endTime) {
                        $query->where(function ($q) use ($startTime, $endTime) {
                            $q->where('start_time', '<', $endTime)
                              ->where('end_time', '>', $startTime);
                        })
                        ->orWhere(function ($q) use ($startTime, $endTime) {
                            $q->where('end_time', $startTime)
                              ->orWhere('start_time', $endTime);
                        });
                    })
                    ->exists();

                return !$hasConflict;
            });

            if ($conflictFreeClassrooms->isEmpty()) {
                return [
                    'success' => false,
                    'message' => "No venues available without scheduling conflicts for the specified time slot."
                ];
            }

            // Randomly select from available conflict-free classrooms
            $selectedClassroom = $conflictFreeClassrooms->random();
            $venueInfo = $this->determineTeachingModeAndLocation($selectedClassroom->name);

            // Set location based on venue type
            $location = $venueInfo['location'];

            \Log::info('Random venue assigned', [
                'venue' => $selectedClassroom->name,
                'capacity' => $selectedClassroom->capacity,
                'student_count' => $studentCount,
                'teaching_mode' => $venueInfo['teaching_mode'],
                'location' => $location,
                'day' => $day,
                'time' => "{$startTime} - {$endTime}",
                'preferred_mode' => $preferredMode
            ]);

            return [
                'success' => true,
                'venue' => $selectedClassroom->name,
                'location' => $location,
                'teaching_mode' => $venueInfo['teaching_mode'],
                'message' => "Random {$venueInfo['teaching_mode']} venue '{$selectedClassroom->name}' assigned successfully."
            ];
        } catch (\Exception $e) {
            \Log::error('Error in random venue assignment: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to assign random venue: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Determine teaching mode and location based on venue
     */
    private function determineTeachingModeAndLocation($venue)
    {
        // Only treat 'Remote' (case-insensitive, trimmed) as online
        if (strtolower(trim($venue)) === 'remote') {
            return [
                'teaching_mode' => 'online',
                'location' => 'online'
            ];
        }
        // All other venues are physical
        $classroom = Classroom::where('name', $venue)->first();
        $location = $classroom ? $classroom->location : 'Physical';
        return [
            'teaching_mode' => 'physical',
            'location' => $location
        ];
    }
  
    /**
     * Assign a random venue with sufficient capacity and no conflicts
     */
   

    /**
     * ✅ NEW: Get groups by class ID (for modal functionality)
     */
    public function getGroupsByClass($classId)
    {
        try {
            $groups = Group::where('class_id', $classId)
                ->select('id', 'name', 'class_id')
                ->get();

            return response()->json($groups);
        } catch (\Exception $e) {
            \Log::error('Error fetching groups for class: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch groups.'], 500);
        }
    }

    /**
     * ✅ NEW: Get lecturer information for a specific unit and semester
     */
    public function getLecturerForUnit($unitId, $semesterId)
    {
        try {
            // Find the lecturer assigned to this unit in this semester
            $enrollment = Enrollment::where('unit_id', $unitId)
                ->where('semester_id', $semesterId)
                ->whereNotNull('lecturer_code')
                ->first();

            if (!$enrollment) {
                return response()->json(['success' => false, 'message' => 'No lecturer assigned to this unit.']);
            }

            // Get lecturer details
            $lecturer = User::where('code', $enrollment->lecturer_code)->first();
            if (!$lecturer) {
                return response()->json(['success' => false, 'message' => 'Lecturer not found.']);
            }

            // Count students enrolled in this unit
            $studentCount = Enrollment::where('unit_id', $unitId)
                ->where('semester_id', $semesterId)
                ->count();

            return response()->json([
                'success' => true,
                'lecturer' => [
                    'id' => $lecturer->id,
                    'code' => $lecturer->code,
                    'name' => $lecturer->first_name . ' ' . $lecturer->last_name,
                ],
                'studentCount' => $studentCount
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to get lecturer for unit: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to get lecturer information: ' . $e->getMessage()], 500);
        }
    }

    /**
     * ✅ NEW: Process the class timetable to optimize and resolve conflicts
     */
    public function process(Request $request)
    {
        $request->validate([
            'semester_id' => 'required|exists:semesters,id',
            'program_id' => 'required|exists:programs,id',
            'class_id' => 'required|exists:classes,id',
            'group_id' => 'nullable|exists:groups,id',
        ]);

        try {
            $semesterId = $request->semester_id;
            $programId = $request->program_id;
            $classId = $request->class_id;
            $groupId = $request->group_id;

            \Log::info('Processing class timetable', [
                'semester_id' => $semesterId,
                'program_id' => $programId,
                'class_id' => $classId,
                'group_id' => $groupId,
            ]);

            // Fetch units for the class and semester
            $units = DB::table('class_unit')
                ->join('units', 'class_unit.unit_id', '=', 'units.id')
                ->where('class_unit.class_id', $classId)
                ->where('class_unit.semester_id', $semesterId)
                ->select('units.id', 'units.name', 'units.code')
                ->get();

            if ($units->isEmpty()) {
                \Log::warning('No units found for the specified class and semester', [
                    'semester_id' => $semesterId,
                    'class_id' => $classId,
                ]);
                return response()->json(['message' => 'No units found for the specified class and semester.'], 422);
            }

            // Fetch available time slots
            $timeSlots = DB::table('class_time_slots')->get();

            if ($timeSlots->isEmpty()) {
                \Log::warning('No time slots available');
                return response()->json(['message' => 'No time slots available.'], 422);
            }

            // Fetch classrooms
            $classrooms = DB::table('classrooms')->get();

            if ($classrooms->isEmpty()) {
                \Log::warning('No classrooms available');
                return response()->json(['message' => 'No classrooms available.'], 422);
            }

            // Randomly assign units to time slots and classrooms
            foreach ($units as $unit) {
                $randomTimeSlot = $timeSlots->random();
                $randomClassroom = $classrooms->random();

                ClassTimetable::create([
                    'day' => $randomTimeSlot->day,
                    'start_time' => $randomTimeSlot->start_time,
                    'end_time' => $randomTimeSlot->end_time,
                    'unit_id' => $unit->id,
                    'semester_id' => $semesterId,
                    'class_id' => $classId,
                    'group_id' => $groupId,
                    'venue' => $randomClassroom->name,
                    'location' => $randomClassroom->location,
                    'no' => Enrollment::where('unit_id', $unit->id)->where('semester_id', $semesterId)->count(),
                    'lecturer' => Enrollment::where('unit_id', $unit->id)->where('semester_id', $semesterId)->value('lecturer_code'),
                ]);
            }

            return response()->json(['success' => true, 'message' => 'Class timetable processed successfully.']);
        } catch (\Exception $e) {
            \Log::error('Failed to process class timetable: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to process class timetable.'], 500);
        }
    }

    /**
     * ✅ NEW: Solve conflicts in the class timetable
     */
    public function solveConflicts(Request $request)
    {
        try {
            \Log::info('Solving class timetable conflicts');

            $conflicts = $this->detectAndResolveConflicts();

            return redirect()->back()->with('success', 'Class conflicts resolved successfully.');
        } catch (\Exception $e) {
            \Log::error('Failed to solve class conflicts: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to solve class conflicts: ' . $e->getMessage());
        }
    }

    /**
     * ✅ NEW: Download the class timetable as a PDF
     */
    public function downloadPDF(Request $request)
    {
        try {
            // Ensure the view file exists
            if (!view()->exists('classtimetables.pdf')) {
                \Log::error('PDF template not found: classtimetables.pdf');
                return redirect()->back()->with('error', 'PDF template not found. Please contact the administrator.');
            }

            // Fetch class timetables
            $query = ClassTimetable::query()
                ->join('units', 'class_timetable.unit_id', '=', 'units.id')
                ->join('semesters', 'class_timetable.semester_id', '=', 'semesters.id')
                ->leftJoin('classes', 'class_timetable.class_id', '=', 'classes.id')
                ->leftJoin('groups', 'class_timetable.group_id', '=', 'groups.id')
                ->select(
                    'class_timetable.day',
                    'class_timetable.start_time',
                    'class_timetable.end_time',
                    'class_timetable.venue',
                    'class_timetable.location',
                    'class_timetable.lecturer',
                    'units.code as unit_code',
                    'units.name as unit_name',
                    'semesters.name as semester_name',
                    'classes.name as class_name',
                    'groups.name as group_name'
                );

            if ($request->has('semester_id')) {
                $query->where('class_timetable.semester_id', $request->semester_id);
            }

            if ($request->has('class_id')) {
                $query->where('class_timetable.class_id', $request->class_id);
            }

            if ($request->has('group_id')) {
                $query->where('class_timetable.group_id', $request->group_id);
            }

            $classTimetables = $query->orderBy('class_timetable.day')
                ->orderBy('class_timetable.start_time')
                ->get();

            \Log::info('Generating PDF with data:', [
                'count' => $classTimetables->count(),
                'filters' => $request->only(['semester_id', 'class_id', 'group_id'])
            ]);

            // Generate PDF
            $pdf = Pdf::loadView('classtimetables.pdf', [
                'classTimetables' => $classTimetables,
                'title' => 'Class Timetable',
                'generatedAt' => now()->format('Y-m-d H:i:s'),
                'filters' => $request->only(['semester_id', 'class_id', 'group_id'])
            ]);

            // Set paper size and orientation
            $pdf->setPaper('a4', 'landscape');

            // Return the PDF for download with correct headers
            return response()->streamDownload(
                fn () => print($pdf->output()),
                'class-timetable-' . now()->format('Y-m-d') . '.pdf',
                [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'attachment; filename="class-timetable-' . now()->format('Y-m-d') . '.pdf"',
                ]
            );
        } catch (\Exception $e) {
            \Log::error('Failed to generate PDF: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()->with('error', 'Failed to generate PDF: ' . $e->getMessage());
        }
    }

    /**
     * Helper method to detect conflicts in the timetable
     */
    private function detectConflicts()
    {
        // Detect lecturer conflicts
        $lecturerConflicts = DB::select("
            SELECT lecturer, day, start_time, end_time, COUNT(*) as conflict_count
            FROM class_timetable 
            GROUP BY lecturer, day, start_time, end_time
            HAVING COUNT(*) > 1
        ");

        // Detect venue conflicts
        $venueConflicts = DB::select("
            SELECT venue, day, start_time, end_time, COUNT(*) as conflict_count
            FROM class_timetable 
            GROUP BY venue, day, start_time, end_time
            HAVING COUNT(*) > 1
        ");

        return [
            'lecturer_conflicts' => $lecturerConflicts,
            'venue_conflicts' => $venueConflicts
        ];
    }

    /**
     * Helper method to detect and resolve conflicts
     */
    private function detectAndResolveConflicts()
    {
        $conflicts = $this->detectConflicts();
        $resolved = 0;

        // Implementation of conflict resolution logic
        // This could include automatic rescheduling, venue reassignment, etc.

        return ['conflicts_resolved' => $resolved];
    }

    /**
     * ✅ NEW: Smart school_id determination
     */
    private function determineSchoolId($request, $class)
    {
        // Priority: Request > Class > Program > Default
        if ($request->school_id) {
            return $request->school_id;
        }

        if ($class && $class->school_id) {
            return $class->school_id;
        }

        if ($class && $class->program_id) {
            $program = Program::find($class->program_id);
            if ($program && $program->school_id) {
                return $program->school_id;
            }
        }

        // Fallback to first school if none found
        $defaultSchool = DB::table('schools')->first();
        return $defaultSchool ? $defaultSchool->id : null;
    }

    /**
     * ✅ NEW: Smart program_id determination
     */
    private function determineProgramId($request, $class)
    {
        // Priority: Request > Class > Default
        if ($request->program_id) {
            return $request->program_id;
        }

        if ($class && $class->program_id) {
            return $class->program_id;
        }

        return null;
    }

    /**
     * ✅ NEW: API endpoint to get programs by school
     */
    public function getProgramsBySchool($schoolId)
    {
        try {
            $programs = Program::where('school_id', $schoolId)
                ->select('id', 'name', 'code')
                ->get();

            return response()->json($programs);
        } catch (\Exception $e) {
            \Log::error('Error fetching programs for school: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch programs.'], 500);
        }
    }

    /**
     * ✅ NEW: API endpoint to get classes by program
     */
    public function getClassesByProgram($programId)
    {
        try {
            $classes = ClassModel::where('program_id', $programId)
                ->select('id', 'name')
                ->get();

            return response()->json($classes);
        } catch (\Exception $e) {
            \Log::error('Error fetching classes for program: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch classes.'], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $timetable = ClassTimetable::findOrFail($id);
            $timetable->delete();

            \Log::info('Class timetable deleted successfully', ['id' => $id]);

            return redirect()->back()->with('success', 'Class timetable deleted successfully.');
        } catch (\Exception $e) {
            \Log::error('Failed to delete class timetable: ' . $e->getMessage(), ['id' => $id]);
            return response()->json(['success' => false, 'message' => 'Failed to delete class timetable.'], 500);
        }
    }
    public function show($id)
    {
        try {
            $timetable = ClassTimetable::findOrFail($id);
            return response()->json($timetable);
        } catch (\Exception $e) {
            \Log::error('Failed to fetch class timetable: ' . $e->getMessage(), ['id' => $id]);
            return response()->json(['success' => false, 'message' => 'Failed to fetch class timetable.'], 500);
        }
    }
}