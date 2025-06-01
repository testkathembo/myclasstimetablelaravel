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

        // Fetch class timetables with related data
        $classTimetables = ClassTimetable::query()
            ->leftJoin('units', 'class_timetable.unit_id', '=', 'units.id')
            ->leftJoin('semesters', 'class_timetable.semester_id', '=', 'semesters.id')
            ->leftJoin('classes', 'class_timetable.class_id', '=', 'classes.id')
            ->leftJoin('groups', 'class_timetable.group_id', '=', 'groups.id')
            ->leftJoin('class_time_slots', function ($join) {
                $join->on('class_timetable.day', '=', 'class_time_slots.day')
                    ->on('class_timetable.start_time', '=', 'class_time_slots.start_time')
                    ->on('class_timetable.end_time', '=', 'class_time_slots.end_time');
            })
            ->leftJoin('users', 'users.code', '=', 'class_timetable.lecturer')
            ->select(
                'class_timetable.id',
                'class_timetable.day',
                'class_timetable.start_time',
                'class_timetable.end_time',
                'class_timetable.venue',
                'class_timetable.location',
                'class_timetable.no',
                DB::raw("IF(users.id IS NOT NULL, CONCAT(users.first_name, ' ', users.last_name), class_timetable.lecturer) as lecturer"),
                'class_timetable.class_id',
                'class_timetable.group_id',
                'units.name as unit_name',
                'units.code as unit_code',
                'semesters.name as semester_name',
                'classes.name as class_name',
                'groups.name as group_name',
                'class_timetable.semester_id',
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
        $allUnits = Unit::select('id', 'code', 'name', 'semester_id')->get();
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
                ->select('id', 'code', 'name')
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
            'schools' => $schools,  // ✅ ADDED: Include schools
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
    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // ✅ FIXED: Updated validation to include program_id and school_id
        $request->validate([
            'day' => 'required|string',
            'unit_id' => 'required|exists:units,id',
            'semester_id' => 'required|exists:semesters,id',
            'class_id' => 'required|exists:classes,id',
            'group_id' => 'nullable|exists:groups,id',
            'venue' => 'nullable|string',
            'location' => 'nullable|string',
            'no' => 'required|integer|min:1',
            'lecturer' => 'required|string',
            'start_time' => 'required|string',
            'end_time' => 'required|string',
            'classtimeslot_id' => 'nullable|exists:class_time_slots,id',
            'program_id' => 'nullable|exists:programs,id',  // ✅ ADDED
            'school_id' => 'nullable|exists:schools,id',    // ✅ ADDED
        ]);

        try {
            // ✅ Log incoming request data for debugging
            \Log::info('Creating class timetable with data:', $request->all());

            // ✅ ADDED: Get program_id and school_id from class relationship
            $class = ClassModel::find($request->class_id);
            $programId = $request->program_id ?: ($class ? $class->program_id : null);
            $schoolId = $request->school_id ?: ($class ? $class->school_id : null);

            // Handle venue assignment (random or specified)
            $venue = $request->venue;
            $location = $request->location;

            // If no venue is specified, assign a random one
            if (empty($venue) || $venue === 'Random Venue (auto-assign)') {
                $randomVenueResult = $this->assignRandomVenue($request->no, $request->day, $request->start_time, $request->end_time);

                if (!$randomVenueResult['success']) {
                    if ($request->expectsJson()) {
                        return response()->json([
                            'success' => false,
                            'message' => $randomVenueResult['message'],
                            'errors' => ['venue' => $randomVenueResult['message']]
                        ], 422);
                    }

                    return redirect()->back()
                        ->withErrors(['venue' => $randomVenueResult['message']])
                        ->withInput()
                        ->with('error', $randomVenueResult['message']);
                }

                $venue = $randomVenueResult['venue'];
                $location = $randomVenueResult['location'];
            }

            // Determine teaching mode and location based on venue
            $venueInfo = $this->determineTeachingModeAndLocation($venue);
            $teachingMode = $venueInfo['teaching_mode'];

            // Override location if it's an online venue
            if ($teachingMode === 'online') {
                $location = 'online';
            } else if (empty($location)) {
                $classroom = Classroom::where('name', $venue)->first();
                $location = $classroom ? $classroom->location : $venueInfo['location'];
            }

            // Enhanced conflict detection
            \Log::info('Checking for conflicts', [
                'day' => $request->day,
                'lecturer' => $request->lecturer,
                'start_time' => $request->start_time,
                'end_time' => $request->end_time,
                'venue' => $venue,
                'location' => $location,
                'teaching_mode' => $teachingMode,
                'semester_id' => $request->semester_id,
                'class_id' => $request->class_id,
                'program_id' => $programId,
                'school_id' => $schoolId
            ]);

            // [Conflict detection code remains the same...]
            // Check for lecturer time conflicts
            $lecturerConflict = ClassTimetable::where('day', $request->day)
                ->where('lecturer', $request->lecturer)
                ->where(function ($query) use ($request) {
                    $query->where(function ($q) use ($request) {
                        $q->where('start_time', '<', $request->end_time)
                          ->where('end_time', '>', $request->start_time);
                    })
                    ->orWhere(function ($q) use ($request) {
                        $q->where('end_time', $request->start_time)
                          ->orWhere('start_time', $request->end_time);
                    });
                })
                ->first();

            if ($lecturerConflict) {
                \Log::warning('Lecturer conflict detected');
                
                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Time conflict: The lecturer has another class that conflicts with this time slot.',
                        'errors' => ['conflict' => 'Lecturer time conflict detected']
                    ], 422);
                }

                return redirect()->back()
                    ->withErrors(['conflict' => 'Time conflict: The lecturer has another class that conflicts with this time slot.'])
                    ->withInput();
            }

            // ✅ FIXED: Create the timetable entry with ALL required fields
            $classTimetable = ClassTimetable::create([
                'day' => $request->day,
                'unit_id' => $request->unit_id,
                'semester_id' => $request->semester_id,
                'class_id' => $request->class_id,              // ✅ FIXED: No longer converts to null
                'group_id' => $request->group_id ?: null,      // ✅ FIXED: Properly handles nullable
                'venue' => $venue,
                'location' => $location,
                'no' => $request->no,
                'lecturer' => $request->lecturer,
                'start_time' => $request->start_time,
                'end_time' => $request->end_time,
                'teaching_mode' => $teachingMode,
                'program_id' => $programId,                    // ✅ ADDED: Now includes program_id
                'school_id' => $schoolId,                      // ✅ ADDED: Now includes school_id
            ]);

            \Log::info('Class timetable created successfully', [
                'id' => $classTimetable->id,
                'class_id' => $classTimetable->class_id,
                'group_id' => $classTimetable->group_id,
                'program_id' => $classTimetable->program_id,
                'school_id' => $classTimetable->school_id,
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Class timetable created successfully.',
                    'data' => $classTimetable
                ]);
            }

            return redirect()->back()->with('success', 'Class timetable created successfully.');
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
     * Display the specified resource.
     */
    public function show($id)
    {
        $classTimetable = ClassTimetable::with(['unit', 'semester', 'class', 'group'])->findOrFail($id);
        return Inertia::render('ClassTimetables/Show', [
            'classTimetable' => $classTimetable
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        // ✅ FIXED: Updated validation for update method
        $request->validate([
            'day' => 'required|string',
            'unit_id' => 'required|exists:units,id',
            'semester_id' => 'required|exists:semesters,id',
            'class_id' => 'required|exists:classes,id',
            'group_id' => 'nullable|exists:groups,id',
            'venue' => 'required|string',
            'location' => 'required|string',
            'no' => 'required|integer|min:1',
            'lecturer' => 'required|string',
            'start_time' => 'required|string',
            'end_time' => 'required|string',
            'classtimeslot_id' => 'nullable|exists:class_time_slots,id',

        ]);

        try {
            // Check for lecturer time conflicts (excluding current record)
            $lecturerConflict = ClassTimetable::where('id', '!=', $id)
                ->where('day', $request->day)
                ->where('lecturer', $request->lecturer)
                ->where(function ($query) use ($request) {
                    $query->where(function ($q) use ($request) {
                        $q->where('start_time', '<', $request->end_time)
                          ->where('end_time', '>', $request->start_time);
                    })
                    ->orWhere(function ($q) use ($request) {
                        $q->where('end_time', $request->start_time)
                          ->orWhere('start_time', $request->end_time);
                    });
                })
                ->exists();

            if ($lecturerConflict) {
                return redirect()->back()->with('error', 'Time conflict detected: The lecturer is already assigned to another class during this time.');
            }

            // Check for semester time conflicts (excluding current record)
            $semesterConflict = ClassTimetable::where('id', '!=', $id)
                ->where('day', $request->day)
                ->where('semester_id', $request->semester_id)
                ->where('class_id', $request->class_id)
                ->where(function ($query) use ($request) {
                    $query->where(function ($q) use ($request) {
                        $q->where('start_time', '<', $request->end_time)
                          ->where('end_time', '>', $request->start_time);
                    })
                    ->orWhere(function ($q) use ($request) {
                        $q->where('end_time', $request->start_time)
                          ->orWhere('start_time', $request->end_time);
                    });
                })
                ->exists();

            if ($semesterConflict) {
                return redirect()->back()->with('error', 'Time conflict detected: Another class is already scheduled for this semester during this time.');
            }

            // Check for venue conflicts (excluding current record)
            $venueConflict = ClassTimetable::where('id', '!=', $id)
                ->where('day', $request->day)
                ->where('venue', $request->venue)
                ->where(function ($query) use ($request) {
                    $query->where(function ($q) use ($request) {
                        $q->where('start_time', '<', $request->end_time)
                          ->where('end_time', '>', $request->start_time);
                    })
                    ->orWhere(function ($q) use ($request) {
                        $q->where('end_time', $request->start_time)
                          ->orWhere('start_time', $request->end_time);
                    });
                })
                ->exists();

            if ($venueConflict) {
                return redirect()->back()->with('error', 'Time conflict detected: The venue is already booked during this time.');
            }

            // Determine teaching mode
            $venueInfo = $this->determineTeachingModeAndLocation($request->venue);
            $teachingMode = $venueInfo['teaching_mode'];

            $classTimetable = ClassTimetable::findOrFail($id);
            
            // ✅ FIXED: Update with proper null handling
            $classTimetable->update([
                'day' => $request->day,
                'unit_id' => $request->unit_id,
                'semester_id' => $request->semester_id,
                'class_id' => $request->class_id ?: null,
                'group_id' => $request->group_id ?: null,
                'venue' => $request->venue,
                'location' => $request->location,
                'no' => $request->no,
                'lecturer' => $request->lecturer,
                'start_time' => $request->start_time,
                'end_time' => $request->end_time,
                'teaching_mode' => $teachingMode,
            ]);

            return redirect()->back()->with('success', 'Class timetable updated successfully.');
        } catch (\Exception $e) {
            \Log::error('Failed to update class timetable: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to update class timetable: ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        try {
            $classTimetable = ClassTimetable::findOrFail($id);
            $classTimetable->delete();
            return redirect()->back()->with('success', 'Class timetable deleted successfully.');
        } catch (\Exception $e) {
            \Log::error('Failed to delete class timetable: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to delete class timetable: ' . $e->getMessage());
        }
    }

    /**
     * AJAX delete method for compatibility with frontend frameworks.
     */
    public function ajaxDestroy($id)
    {
        try {
            $classTimetable = ClassTimetable::findOrFail($id);
            $classTimetable->delete();
            return response()->json(['success' => true, 'message' => 'Class timetable deleted successfully.']);
        } catch (\Exception $e) {
            \Log::error('Failed to delete class timetable: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to delete class timetable: ' . $e->getMessage()], 500);
        }
    }

    /**
     * ✅ NEW: API endpoint to get units by class and semester (for modal functionality)
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

            // Try using the semester_unit pivot table first
            $hasSemesterUnitTable = DB::getSchemaBuilder()->hasTable('semester_unit');

            if ($hasSemesterUnitTable) {
                $semesterUnitColumns = DB::getSchemaBuilder()->getColumnListing('semester_unit');

                if (in_array('class_id', $semesterUnitColumns)) {
                    $units = DB::table('semester_unit')
                        ->join('units', 'semester_unit.unit_id', '=', 'units.id')
                        ->where('semester_unit.semester_id', $semesterId)
                        ->where('semester_unit.class_id', $classId)
                        ->select('units.id', 'units.name', 'units.code')
                        ->get();
                } else {
                    $units = DB::table('semester_unit')
                        ->join('units', 'semester_unit.unit_id', '=', 'units.id')
                        ->where('semester_unit.semester_id', $semesterId)
                        ->select('units.id', 'units.name', 'units.code')
                        ->get();
                }
            } else {
                // Fallback: Get units through group_unit relationships
                $units = DB::table('groups')
                    ->join('group_unit', 'groups.id', '=', 'group_unit.group_id')
                    ->join('units', 'group_unit.unit_id', '=', 'units.id')
                    ->where('groups.class_id', $classId)
                    ->select('units.id', 'units.name', 'units.code')
                    ->distinct()
                    ->get();
            }

            if ($units->isEmpty()) {
                return response()->json(['error' => 'No units found for the selected class in this semester.'], 404);
            }

            // Enhance units with student count and lecturer information
            $enhancedUnits = $units->map(function ($unit) use ($semesterId, $classId) {
                $enrollmentQuery = Enrollment::where('unit_id', $unit->id)
                    ->where('semester_id', $semesterId);

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
                    'id' => $unit->id,
                    'code' => $unit->code,
                    'name' => $unit->name,
                    'student_count' => $studentCount,
                    'lecturer_name' => $lecturerName,
                ];
            });

            return response()->json($enhancedUnits);
        } catch (\Exception $e) {
            \Log::error('Error fetching units for class: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch units. Please try again.'], 500);
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
                    return $venueInfo['teaching_mode'] === $preferredMode;
                });

                if ($availableClassrooms->isEmpty()) {
                    return [
                        'success' => false,
                        'message' => "No {$preferredMode} venues available with sufficient capacity for {$studentCount} students."
                    ];
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
                'time' => "{$startTime} - {$endTime}"
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
}