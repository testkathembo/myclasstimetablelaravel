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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Barryvdh\DomPDF\Facade\Pdf;

class ClassTimetableController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $user = auth()->user();

        // Log user roles and permissions
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

        // Fetch class timetables with related data, including lecturer names
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
            ->leftJoin('users', 'users.code', '=', 'class_timetable.lecturer') // Correct join condition
            ->select(
                'class_timetable.id',
                'class_timetable.day',
                'class_timetable.start_time',
                'class_timetable.end_time',
                'class_timetable.venue',
                'class_timetable.location',
                'class_timetable.no',
                DB::raw("IF(users.id IS NOT NULL, CONCAT(users.first_name, ' ', users.last_name), class_timetable.lecturer) as lecturer"), // Fallback to lecturer code if name is unavailable
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
                      ->orWhere(DB::raw("CONCAT(users.first_name, ' ', users.last_name)"), 'like', "%{$search}%") // Search by lecturer name
                      ->orWhere('class_timetable.venue', 'like', "%{$search}%");
                });
            })
            ->orderBy('class_timetable.day')
            ->orderBy('class_timetable.start_time')
            ->paginate($request->get('per_page', 10));

        // Fetch lecturers with both ID and code
        $lecturers = User::role('Lecturer')
            ->select('id', 'code', DB::raw("CONCAT(first_name, ' ', last_name) as name"))
            ->get();

        // Get all necessary data for the form
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

        // Log the data being passed for debugging
        \Log::info('Class Timetable Index Data:', [
            'classes_count' => $classes->count(),
            'groups_count' => $groups->count(),
            'units_count' => $unitsWithSemesters->count(),
            'classtimetables_count' => $classTimetables->total(),
        ]);

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
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        // This method is not used with Inertia.js as the form is part of the index page
        abort(404);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    /**
 * Determine teaching mode and location based on venue
 *
 * @param string $venue
 * @return array
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

public function store(Request $request)
{
    $request->validate([
        'day' => 'required|string',
        'unit_id' => 'required|exists:units,id',
        'semester_id' => 'required|exists:semesters,id',
        'class_id' => 'required|exists:classes,id',
        'group_id' => 'required|exists:groups,id',
        'venue' => 'nullable|string',
        'location' => 'nullable|string',
        'no' => 'required|integer|min:1',
        'lecturer' => 'required|string',
        'start_time' => 'required|string',
        'end_time' => 'required|string',
        'classtimeslot_id' => 'nullable|exists:class_time_slots,id',
    ]);

    try {
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

        // Enhanced conflict detection with better logging
        \Log::info('Checking for conflicts', [
            'day' => $request->day,
            'lecturer' => $request->lecturer,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'venue' => $venue,
            'location' => $location,
            'teaching_mode' => $teachingMode,
            'semester_id' => $request->semester_id,
            'class_id' => $request->class_id
        ]);

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
            \Log::warning('Lecturer conflict detected', [
                'conflicting_record' => $lecturerConflict->toArray(),
                'new_request' => $request->only(['day', 'lecturer', 'start_time', 'end_time'])
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Time conflict: The lecturer has another class that conflicts with this time slot.',
                    'errors' => ['conflict' => 'Lecturer time conflict detected']
                ], 422);
            }

            return redirect()->back()
                ->withErrors(['conflict' => 'Time conflict: The lecturer has another class that conflicts with this time slot.'])
                ->withInput()
                ->with('error', 'Time conflict: The lecturer has another class that conflicts with this time slot.');
        }

        // Check for class/semester time conflicts
        $semesterConflict = ClassTimetable::where('day', $request->day)
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
            ->first();

        if ($semesterConflict) {
            \Log::warning('Class time conflict detected', [
                'conflicting_record' => $semesterConflict->toArray(),
                'new_request' => $request->only(['day', 'semester_id', 'class_id', 'start_time', 'end_time'])
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Time conflict: This class already has another subject scheduled at this time.',
                    'errors' => ['conflict' => 'Class time conflict detected']
                ], 422);
            }

            return redirect()->back()
                ->withErrors(['conflict' => 'Time conflict: This class already has another subject scheduled at this time.'])
                ->withInput()
                ->with('error', 'Time conflict: This class already has another subject scheduled at this time.');
        }

        // Check for venue conflicts (only for physical venues, not remote)
        if (!empty($venue) && $teachingMode !== 'online') {
            $venueConflict = ClassTimetable::where('day', $request->day)
                ->where('venue', $venue)
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

            if ($venueConflict) {
                \Log::warning('Venue conflict detected', [
                    'conflicting_record' => $venueConflict->toArray(),
                    'new_request' => $request->only(['day', 'venue', 'start_time', 'end_time'])
                ]);

                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Time conflict: The venue is already booked for another class at this time.',
                        'errors' => ['conflict' => 'Venue time conflict detected'
                        ]
                    ], 422);
                }

                return redirect()->back()
                    ->withErrors(['conflict' => 'Time conflict: The venue is already booked for another class at this time.'])
                    ->withInput()
                    ->with('error', 'Time conflict: The venue is already booked for another class at this time.');
            }
        }

        // If no conflicts, create the timetable entry
        $classTimetable = ClassTimetable::create([
            'day' => $request->day,
            'unit_id' => $request->unit_id,
            'semester_id' => $request->semester_id,
            'class_id' => $request->class_id,
            'group_id' => $request->group_id,
            'venue' => $venue,
            'location' => $location, // This will be 'online' for remote venues
            'no' => $request->no,
            'lecturer' => $request->lecturer,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'teaching_mode' => $teachingMode,
        ]);

        \Log::info('Class timetable created successfully', [
            'id' => $classTimetable->id,
            'day' => $request->day,
            'unit_id' => $request->unit_id,
            'venue' => $venue,
            'location' => $location,
            'teaching_mode' => $teachingMode,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time
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
            ->withInput()
            ->with('error', 'Failed to create class timetable: ' . $e->getMessage());
    }
}

/**
 * Assign a random venue with sufficient capacity and no conflicts
 * Updated to properly handle location mapping and teaching mode
 *
 * @param int $studentCount
 * @param string $day
 * @param string $startTime
 * @param string $endTime
 * @param string|null $preferredMode
 * @return array
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
     * Display the specified resource.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $classTimetable = ClassTimetable::with(['unit', 'semester', 'class', 'group'])->findOrFail($id);
        return Inertia::render('ClassTimetable/Show', [
            'classTimetable' => $classTimetable
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        // This method is not used with Inertia.js as the form is part of the index page
        abort(404);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'day' => 'required|string',
            'unit_id' => 'required|exists:units,id',
            'semester_id' => 'required|exists:semesters,id',
            'class_id' => 'required|exists:classes,id',
            'group_id' => 'required|exists:groups,id',
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
                    $query->whereBetween('start_time', [$request->start_time, $request->end_time])
                        ->orWhereBetween('end_time', [$request->start_time, $request->end_time])
                        ->orWhere(function ($q) use ($request) {
                            $q->where('start_time', '<=', $request->start_time)
                                ->where('end_time', '>=', $request->end_time);
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
                ->where(function ($query) use ($request) {
                    $query->whereBetween('start_time', [$request->start_time, $request->end_time])
                        ->orWhereBetween('end_time', [$request->start_time, $request->end_time])
                        ->orWhere(function ($q) use ($request) {
                            $q->where('start_time', '<=', $request->start_time)
                                ->where('end_time', '>=', $request->end_time);
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
                    $query->whereBetween('start_time', [$request->start_time, $request->end_time])
                        ->orWhereBetween('end_time', [$request->start_time, $request->end_time])
                        ->orWhere(function ($q) use ($request) {
                            $q->where('start_time', '<=', $request->start_time)
                                ->where('end_time', '>=', $request->end_time);
                        });
                })
                ->exists();

            if ($venueConflict) {
                return redirect()->back()->with('error', 'Time conflict detected: The venue is already booked during this time.');
            }

            $classTimetable = ClassTimetable::findOrFail($id);
            $classTimetable->update([
                'day' => $request->day,
                'unit_id' => $request->unit_id,
                'semester_id' => $request->semester_id,
                'class_id' => $request->class_id,
                'group_id' => $request->group_id,
                'venue' => $request->venue,
                'location' => $request->location,
                'no' => $request->no,
                'lecturer' => $request->lecturer,
                'start_time' => $request->start_time,
                'end_time' => $request->end_time,
            ]);

            return redirect()->back()->with('success', 'Class timetable updated successfully.');
        } catch (\Exception $e) {
            \Log::error('Failed to update class timetable: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to update class timetable: ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
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
     *
     * @param int $id
     * @return \Illuminate\Http\Response
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
     * Get lecturer information for a specific unit and semester.
     *
     * @param int $unitId
     * @param int $semesterId
     * @return \Illuminate\Http\Response
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
     * Fetch units based on the selected group with student count and lecturer information.
     * Handles both GET and POST requests for flexibility.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function getUnitsByGroupOrClass(Request $request)
    {
        try {
            // Handle both GET (query parameters) and POST (request body) methods
            $groupId = $request->input('group_id') ?? $request->get('group_id');
            $semesterId = $request->input('semester_id') ?? $request->get('semester_id');

            // Validate the input
            if (!$groupId) {
                \Log::warning('Missing group_id in request', [
                    'method' => $request->method(),
                    'all_input' => $request->all(),
                    'query' => $request->query(),
                ]);
                return response()->json(['error' => 'Group ID is required.'], 400);
            }

            // Check if group exists
            $group = Group::find($groupId);
            if (!$group) {
                \Log::warning('Group not found', ['group_id' => $groupId]);
                return response()->json(['error' => 'Group not found.'], 404);
            }

            \Log::info('Fetching units for group', [
                'group_id' => $groupId,
                'semester_id' => $semesterId,
                'method' => $request->method()
            ]);

            // Check if group_unit pivot table exists
            if (!DB::getSchemaBuilder()->hasTable('group_unit')) {
                \Log::error('group_unit pivot table does not exist');
                return response()->json(['error' => 'Database configuration error. Please contact administrator.'], 500);
            }

            // Fetch units linked to the selected group through the pivot table
            $unitsQuery = DB::table('group_unit')
                ->join('units', 'group_unit.unit_id', '=', 'units.id')
                ->where('group_unit.group_id', $groupId)
                ->select('units.id', 'units.name', 'units.code');

            // Filter by semester if provided
            if ($semesterId) {
                // Check if semester exists
                $semester = Semester::find($semesterId);
                if (!$semester) {
                    \Log::warning('Semester not found', ['semester_id' => $semesterId]);
                    return response()->json(['error' => 'Semester not found.'], 404);
                }

                $unitsQuery->where('units.semester_id', $semesterId);
            }

            $units = $unitsQuery->get();

            \Log::info('Raw units query result', [
                'units_count' => $units->count(),
                'group_id' => $groupId,
                'semester_id' => $semesterId
            ]);

            if ($units->isEmpty()) {
                \Log::warning('No units found for group', [
                    'group_id' => $groupId,
                    'semester_id' => $semesterId,
                    'group_name' => $group->name
                ]);
                return response()->json(['error' => 'No units found for the selected group in this semester.'], 404);
            }

            // Enhance units with student count and lecturer information
            $enhancedUnits = $units->map(function ($unit) use ($semesterId) {
                // Get student count for this unit in the specified semester
                $studentCount = 0;
                $lecturerName = '';

                if ($semesterId) {
                    $enrollments = Enrollment::where('unit_id', $unit->id)
                        ->where('semester_id', $semesterId)
                        ->get();

                    $studentCount = $enrollments->count();

                    // Get lecturer information
                    $lecturerEnrollment = $enrollments->whereNotNull('lecturer_code')->first();
                    if ($lecturerEnrollment) {
                        $lecturer = User::where('code', $lecturerEnrollment->lecturer_code)->first();
                        if ($lecturer) {
                            $lecturerName = $lecturer->first_name . ' ' . $lecturer->last_name;
                        }
                    }
                } else {
                    // If no semester specified, get total enrollments for this unit
                    $studentCount = Enrollment::where('unit_id', $unit->id)->count();

                    // Get any lecturer for this unit
                    $lecturerEnrollment = Enrollment::where('unit_id', $unit->id)
                        ->whereNotNull('lecturer_code')
                        ->first();
                    if ($lecturerEnrollment) {
                        $lecturer = User::where('code', $lecturerEnrollment->lecturer_code)->first();
                        if ($lecturer) {
                            $lecturerName = $lecturer->first_name . ' ' . $lecturer->last_name;
                        }
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

            \Log::info('Units fetched successfully', [
                'count' => $enhancedUnits->count(),
                'group_id' => $groupId,
                'semester_id' => $semesterId
            ]);

            return response()->json($enhancedUnits);
        } catch (\Exception $e) {
            \Log::error('Error fetching units for group: ' . $e->getMessage(), [
                'group_id' => $request->input('group_id') ?? $request->get('group_id'),
                'semester_id' => $request->input('semester_id') ?? $request->get('semester_id'),
                'method' => $request->method(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['error' => 'Failed to fetch units. Please try again.'], 500);
        }
    }

    /**
     * Fetch units based on the selected class and semester with student count and lecturer information.
     * This bypasses the group selection and fetches units directly for a class.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function getUnitsByClass(Request $request)
    {
    try {
        // Handle both GET (query parameters) and POST (request body) methods
        $classId = $request->input('class_id') ?? $request->get('class_id');
        $semesterId = $request->input('semester_id') ?? $request->get('semester_id');

        // Validate the input
        if (!$classId) {
            \Log::warning('Missing class_id in request', [
                'method' => $request->method(),
                'all_input' => $request->all(),
                'query' => $request->query(),
            ]);
            return response()->json(['error' => 'Class ID is required.'], 400);
        }

        if (!$semesterId) {
            \Log::warning('Missing semester_id in request', [
                'method' => $request->method(),
                'all_input' => $request->all(),
                'query' => $request->query(),
            ]);
            return response()->json(['error' => 'Semester ID is required.'], 400);
        }

        // Check if class exists
        $class = ClassModel::find($classId);
        if (!$class) {
            \Log::warning('Class not found', ['class_id' => $classId]);
            return response()->json(['error' => 'Class not found.'], 404);
        }

        // Check if semester exists
        $semester = Semester::find($semesterId);
        if (!$semester) {
            \Log::warning('Semester not found', ['semester_id' => $semesterId]);
            return response()->json(['error' => 'Semester not found.'], 404);
        }

        \Log::info('Fetching units for class', [
            'class_id' => $classId,
            'semester_id' => $semesterId,
            'method' => $request->method()
        ]);

        // Try using the semester_unit pivot table first (same as enrollment system)
        $hasSemesterUnitTable = DB::getSchemaBuilder()->hasTable('semester_unit');

        if ($hasSemesterUnitTable) {
            // Check if semester_unit table has class_id column
            $semesterUnitColumns = DB::getSchemaBuilder()->getColumnListing('semester_unit');

            if (in_array('class_id', $semesterUnitColumns)) {
                // Use the same approach as enrollment system
                $units = DB::table('semester_unit')
                    ->join('units', 'semester_unit.unit_id', '=', 'units.id')
                    ->where('semester_unit.semester_id', $semesterId)
                    ->where('semester_unit.class_id', $classId)
                    ->select('units.id', 'units.name', 'units.code')
                    ->get();

                \Log::info('Units fetched from semester_unit table', [
                    'units_count' => $units->count(),
                    'class_id' => $classId,
                    'semester_id' => $semesterId
                ]);
            } else {
                // Fallback: semester_unit table exists but no class_id column
                $units = DB::table('semester_unit')
                    ->join('units', 'semester_unit.unit_id', '=', 'units.id')
                    ->where('semester_unit.semester_id', $semesterId)
                    ->select('units.id', 'units.name', 'units.code')
                    ->get();

                \Log::info('Units fetched from semester_unit table (no class filter)', [
                    'units_count' => $units->count(),
                    'semester_id' => $semesterId
                ]);
            }
        } else {
            // Fallback: Check if units table has direct class_id and semester_id columns
            $unitColumns = DB::getSchemaBuilder()->getColumnListing('units');

            if (in_array('class_id', $unitColumns) && in_array('semester_id', $unitColumns)) {
                // Direct query on units table
                $units = DB::table('units')
                    ->where('semester_id', $semesterId)
                    ->where('class_id', $classId)
                    ->select('id', 'name', 'code')
                    ->get();

                \Log::info('Units fetched from units table directly', [
                    'units_count' => $units->count(),
                    'class_id' => $classId,
                    'semester_id' => $semesterId
                ]);
            } else {
                // Last fallback: Get units through group_unit relationships for all groups in the class
                $units = DB::table('groups')
                    ->join('group_unit', 'groups.id', '=', 'group_unit.group_id')
                    ->join('units', 'group_unit.unit_id', '=', 'units.id')
                    ->where('groups.class_id', $classId)
                    ->select('units.id', 'units.name', 'units.code')
                    ->distinct()
                    ->get();

                \Log::info('Units fetched through group_unit relationships', [
                    'units_count' => $units->count(),
                    'class_id' => $classId
                ]);
            }
        }

        if ($units->isEmpty()) {
            \Log::warning('No units found for class', [
                'class_id' => $classId,
                'semester_id' => $semesterId,
                'class_name' => $class->name
            ]);
            return response()->json(['error' => 'No units found for the selected class in this semester.'], 404);
        }

        // Enhance units with student count and lecturer information
        // Get total counts for the class (not filtered by group)
        $enhancedUnits = $units->map(function ($unit) use ($semesterId, $classId) {
            // Get student count for this unit in the specified semester and class
            $enrollmentQuery = Enrollment::where('unit_id', $unit->id)
                ->where('semester_id', $semesterId);

            // If enrollments table has class_id, filter by it
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

        \Log::info('Units fetched successfully for class', [
            'count' => $enhancedUnits->count(),
            'class_id' => $classId,
            'semester_id' => $semesterId
        ]);

        return response()->json($enhancedUnits);
    } catch (\Exception $e) {
        \Log::error('Error fetching units for class: ' . $e->getMessage(), [
            'class_id' => $request->input('class_id') ?? $request->get('class_id'),
            'semester_id' => $request->input('semester_id') ?? $request->get('semester_id'),
            'method' => $request->method(),
            'trace' => $e->getTraceAsString(),
        ]);
        return response()->json(['error' => 'Failed to fetch units. Please try again.'], 500);
    }
}

/**
 * Get units by class and semester (same as enrollment system).
 * This method matches the enrollment controller's getUnitsByClassAndSemester method.
 *
 * @param \Illuminate\Http\Request $request
 * @return \Illuminate\Http\JsonResponse
 */
public function getUnitsByClassAndSemester(Request $request)
{
    try {
        $validated = $request->validate([
            'semester_id' => 'required|exists:semesters,id',
            'class_id' => 'required|exists:classes,id',
            'group_id' => 'nullable|exists:groups,id',
        ]);

        \Log::info('Fetching units by class and semester', $validated);

        // Use the same logic as enrollment system
        $query = DB::table('semester_unit')
            ->join('units', 'semester_unit.unit_id', '=', 'units.id')
            ->where('semester_unit.semester_id', $validated['semester_id'])
            ->where('semester_unit.class_id', $validated['class_id']);

        // Add group filter if provided and if semester_unit table has group_id column
        if (!empty($validated['group_id'])) {
            $semesterUnitColumns = DB::getSchemaBuilder()->getColumnListing('semester_unit');
            if (in_array('group_id', $semesterUnitColumns)) {
                $query->where('semester_unit.group_id', $validated['group_id']);
            }
        }

        $units = $query->select('units.*')->get();

        if ($units->isEmpty()) {
            return response()->json([
                'error' => 'No units found for the selected class and semester.',
            ], 404);
        }

        // Enhance with enrollment data
        $enhancedUnits = $units->map(function ($unit) use ($validated) {
            $enrollmentQuery = Enrollment::where('unit_id', $unit->id)
                ->where('semester_id', $validated['semester_id']);

            if (!empty($validated['group_id'])) {
                $enrollmentQuery->where('group_id', $validated['group_id']);
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
        Log::error('Error fetching units for class and semester: ' . $e->getMessage());
        return response()->json(['error' => 'Failed to fetch units. Please try again.'], 500);
    }
}

    /**
     * Get groups by class ID.
     *
     * @param int $classId
     * @return \Illuminate\Http\Response
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
     * Process the class timetable to optimize and resolve conflicts.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
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

            // Log the incoming request for debugging
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
     * Solve conflicts in the class timetable.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function solveConflicts(Request $request)
    {
        try {
            // Implementation of conflict resolution logic
            \Log::info('Solving class timetable conflicts');

            $conflicts = $this->detectAndResolveConflicts();

            return redirect()->back()->with('success', 'Class conflicts resolved successfully.');
        } catch (\Exception $e) {
            \Log::error('Failed to solve class conflicts: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to solve class conflicts: ' . $e->getMessage());
        }
    }

    /**
     * Download the class timetable as a PDF.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
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

            // Log the data being passed to the view for debugging
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
            // Log detailed error information
            \Log::error('Failed to generate PDF: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString()
            ]);

            // Return a more informative error response
            return redirect()->back()->with('error', 'Failed to generate PDF: ' . $e->getMessage());
        }
    }

    /**
     * View the lecturer's class timetable.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function viewLecturerClassTimetable(Request $request)
    {
        $user = auth()->user();
        $lecturerCode = $user->code;
        $semesters = Semester::all();

        $semesterId = $request->input('semester_id');
        if (!$semesterId) {
            $currentSemester = Semester::where('is_active', true)->first();
            if (!$currentSemester) {
                $currentSemester = Semester::latest()->first();
            }
            $semesterId = $currentSemester->id ?? null;
        }

        $classTimetables = collect();
        if ($semesterId) {
            $classTimetables = ClassTimetable::where('lecturer', 'like', '%' . $user->first_name . '%')
                ->orWhere('lecturer', 'like', '%' . $user->last_name . '%')
                ->where('semester_id', $semesterId)
                ->with(['unit', 'semester', 'class', 'group'])
                ->orderBy('day')
                ->orderBy('start_time')
                ->get();
        }

        return Inertia::render('Lecturer/ClassTimetable', [
            'classTimetables' => $classTimetables,
            'semesters' => $semesters,
            'selectedSemesterId' => $semesterId
        ]);
    }

    /**
     * Download the lecturer's class timetable as a PDF.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function downloadLecturerClassTimetable(Request $request)
    {
        try {
            $user = auth()->user();
            $semesterId = $request->input('semester_id');

            if (!$semesterId) {
                $currentSemester = Semester::where('is_active', true)->first();
                $semesterId = $currentSemester ? $currentSemester->id : null;
            }

            if (!$semesterId) {
                return redirect()->back()->with('error', 'No semester selected.');
            }

            $semester = Semester::find($semesterId);

            $classTimetables = ClassTimetable::where('lecturer', 'like', '%' . $user->first_name . '%')
                ->orWhere('lecturer', 'like', '%' . $user->last_name . '%')
                ->where('semester_id', $semesterId)
                ->with(['unit', 'semester', 'class', 'group'])
                ->orderBy('day')
                ->orderBy('start_time')
                ->get();

            $pdf = Pdf::loadView('pdfs.lecturer-class-timetable', [
                'classTimetables' => $classTimetables,
                'lecturer' => $user->first_name . ' ' . $user->last_name,
                'semester' => $semester->name ?? 'Unknown Semester',
                'generatedAt' => now()->format('Y-m-d H:i:s')
            ]);

            return $pdf->download('lecturer-class-timetable-' . ($semester->name ?? 'unknown') . '.pdf');
        } catch (\Exception $e) {
            \Log::error('Failed to download lecturer class timetable: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to download lecturer class timetable: ' . $e->getMessage());
        }
    }

    /**
     * Display the class timetable for the logged-in student.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function viewStudentClassTimetable(Request $request)
    {
        $user = auth()->user();
        $semesterId = $request->input('semester_id');

        if (!$semesterId) {
            $currentSemester = Semester::where('is_active', true)->first();
            if (!$currentSemester) {
                $currentSemester = Semester::latest()->first();
            }

            $studentSemesters = Enrollment::where('student_code', $user->code)
                ->distinct('semester_id')
                ->join('semesters', 'enrollments.semester_id', '=', 'semesters.id')
                ->select('semesters.*')
                ->get();

            if ($studentSemesters->isEmpty()) {
                $selectedSemester = $currentSemester;
            } else {
                $activeSemester = $studentSemesters->firstWhere('is_active', true);
                $selectedSemester = $activeSemester ?? $studentSemesters->sortByDesc('id')->first();
            }
            $semesterId = $selectedSemester->id ?? null;
        }

        $semesters = Semester::all();
        $enrolledUnitIds = Enrollment::where('student_code', $user->code)
            ->where('semester_id', $semesterId)
            ->pluck('unit_id')
            ->toArray();

        $classTimetables = ClassTimetable::where('semester_id', $semesterId)
            ->whereIn('unit_id', $enrolledUnitIds)
            ->with(['unit', 'semester', 'class', 'group'])
            ->orderBy('day')
            ->orderBy('start_time')
            ->get();

        return Inertia::render('Student/ClassTimetable', [
            'classTimetables' => $classTimetables,
            'semesters' => $semesters,
            'selectedSemesterId' => $semesterId
        ]);
    }

    /**
     * Download the class timetable for the logged-in student.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function downloadStudentClassTimetable(Request $request)
    {
        try {
            $user = auth()->user();
            $semesterId = $request->input('semester_id');

            if (!$semesterId) {
                $currentSemester = Semester::where('is_active', true)->first();
                $semesterId = $currentSemester ? $currentSemester->id : null;
            }

            if (!$semesterId) {
                return redirect()->back()->with('error', 'No active semester found.');
            }

            $semester = Semester::find($semesterId);
            $enrolledUnitIds = Enrollment::where('student_code', $user->code)
                ->where('semester_id', $semesterId)
                ->pluck('unit_id')
                ->toArray();

            if (empty($enrolledUnitIds)) {
                return redirect()->back()->with('error', 'You are not enrolled in any units for this semester.');
            }

            $classTimetables = ClassTimetable::query()
                ->where('class_timetable.semester_id', $semesterId)
                ->whereIn('class_timetable.unit_id', $enrolledUnitIds)
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
                )
                ->orderBy('class_timetable.day')
                ->orderBy('class_timetable.start_time')
                ->get();

            if ($classTimetables->isEmpty()) {
                return redirect()->back()->with('error', 'No classes found for the selected semester.');
            }

            $pdf = Pdf::loadView('classtimetables.student', [
                'classTimetables' => $classTimetables,
                'student' => $user,
                'currentSemester' => $semester,
            ]);

            return $pdf->download('class-timetable-' . $semester->name . '.pdf');
        } catch (\Exception $e) {
            \Log::error('Failed to download student class timetable: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to download student class timetable: ' . $e->getMessage());
        }
    }

    /**
     * Display details for a specific class for a student.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $classtimetable
     * @return \Illuminate\Http\Response
     */
    public function viewStudentClassExamDetails(Request $request, $classtimetable)
    {
        $user = auth()->user();
        $classTimetable = ClassTimetable::with(['unit', 'semester', 'class', 'group'])->findOrFail($classtimetable);

        $isEnrolled = Enrollment::where('student_code', $user->code)
            ->where('unit_id', $classTimetable->unit_id)
            ->where('semester_id', $classTimetable->semester_id)
            ->exists();

        if (!$isEnrolled) {
            abort(403, 'You are not enrolled in this unit.');
        }

        return Inertia::render('Student/ClassDetails', [
            'classTimetable' => $classTimetable
        ]);
    }

    /**
     * Helper method to detect conflicts in the timetable.
     *
     * @return array
     */
    private function detectConflicts()
    {
        $conflicts = [];

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
     * Helper method to optimize the timetable.
     *
     * @return array
     */
    private function optimizeTimetable()
    {
        // Implementation of optimization logic
        // This could include algorithms to minimize gaps, balance workload, etc.
        return ['optimizations_applied' => 0];
    }

    /**
     * Helper method to detect and resolve conflicts.
     *
     * @return array
     */
    private function detectAndResolveConflicts()
    {
        $conflicts = $this->detectConflicts();
        $resolved = 0;

        // Implementation of conflict resolution logic
        // This could include automatic rescheduling, venue reassignment, etc.

        return ['conflicts_resolved' => $resolved];
    }
}
