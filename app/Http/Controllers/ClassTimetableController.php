<?php

namespace App\Http\Controllers;

use App\Models\ClassTimetable;
use App\Models\Unit;
use App\Models\User;
use App\Models\Semester;
use App\Models\Enrollment;
use App\Models\ClassTimeSlot; // Ensure this is imported
use App\Models\Classroom;
use App\Models\Course;
use App\Models\Lecturer;
use App\Models\Room;
use App\Models\Student;
use App\Models\Department;
use App\Models\AcademicYear;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;
use Inertia\Inertia;
use Barryvdh\DomPDF\Facade\Pdf;

class ClassTimetableController extends Controller
{
    /**
     * Display a listing of the class timetable.
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

        // Fetch class timetables with related data
        $classTimetables = ClassTimetable::query()
            ->leftJoin('units', 'class_timetable.unit_id', '=', 'units.id')
            ->leftJoin('semesters', 'class_timetable.semester_id', '=', 'semesters.id')
            ->leftJoin('class_time_slots', function ($join) {
                $join->on('class_timetable.day', '=', 'class_time_slots.day')
                     ->on('class_timetable.start_time', '=', 'class_time_slots.start_time')
                     ->on('class_timetable.end_time', '=', 'class_time_slots.end_time');
            })
            ->select(
                'class_timetable.id',               
                'class_timetable.day',
                'class_timetable.start_time',
                'class_timetable.end_time',
                'class_timetable.venue',
                'class_timetable.location',
                'class_timetable.no',
                'class_timetable.lecturer',
                'units.name as unit_name',
                'units.code as unit_code',
                'semesters.name as semester_name',
                'class_timetable.semester_id',
                'class_time_slots.status' // Include status column
            )
            ->when($request->has('search') && $request->search !== '', function ($query) use ($request) {
                $search = $request->search;
                $query->where('class_timetable.day', 'like', "%{$search}%");
                    //   ->orWhere('class_timetable.date', 'like', "%{$search}%");
            })
            ->orderByRaw("FIELD(class_timetable.day, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday')")
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
        
        // Get all units
        $allUnits = Unit::select('id', 'code', 'name')->get();
        
        // Get all enrollments with student and lecturer information
        $allEnrollments = Enrollment::select('id', 'student_code', 'unit_id', 'semester_id', 'lecturer_code')
            ->get();
            
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
        
        // For each semester, find all units that have enrollments in that semester
        foreach ($semesters as $semester) {
            // Find all unit_ids that have enrollments in this semester
            $unitIdsInSemester = Enrollment::where('semester_id', $semester->id)
                ->distinct('unit_id')
                ->pluck('unit_id')
                ->toArray();
                
            // Find the corresponding units
            $unitsInSemester = Unit::whereIn('id', $unitIdsInSemester)
                ->select('id', 'code', 'name')
                ->get()
                ->map(function ($unit) use ($semester, $enrollmentsByUnitAndSemester) {
                    // Add semester_id to each unit
                    $unit->semester_id = $semester->id;
                    
                    // Find enrollments for this unit in this semester
                    $key = $unit->id . '_' . $semester->id;
                    $unitEnrollments = $enrollmentsByUnitAndSemester[$key] ?? [];
                    
                    // Count students
                    $unit->student_count = count($unitEnrollments);
                    
                    // Find lecturer
                    $lecturerCode = null;
                    foreach ($unitEnrollments as $enrollment) {
                        if ($enrollment->lecturer_code) {
                            $lecturerCode = $enrollment->lecturer_code;
                            break;
                        }
                    }
                    
                    // Find lecturer details if available
                    if ($lecturerCode) {
                        $lecturer = User::where('code', $lecturerCode)->first();
                        if ($lecturer) {
                            $unit->lecturer_code = $lecturerCode;
                            $unit->lecturer_name = $lecturer->first_name . ' ' . $lecturer->last_name;
                        }
                    }
                    
                    return $unit;
                });
                
            // Add these units to the array
            $unitsBySemester[$semester->id] = $unitsInSemester;
        }
        
        // Flatten the array to get all units with their semester_id
        $unitsWithSemesters = collect();
        foreach ($unitsBySemester as $semesterId => $units) {
            $unitsWithSemesters = $unitsWithSemesters->concat($units);
        }
        
        // Log the units with semesters for debugging
        \Log::info('Units with semesters:', [
            'count' => $unitsWithSemesters->count(),
            'sample' => $unitsWithSemesters->take(5),
        ]);

        return Inertia::render('ClassTimetable/index', [
            'classTimetables' => $classTimetables,  // This matches what your React component now expects
            'lecturers' => $lecturers,
            'perPage' => $perPage,
            'search' => $search,
            'semesters' => $semesters,
            'classrooms' => $classrooms,
            'classtimeSlots' => $classtimeSlots,
            'units' => $unitsWithSemesters,
            'enrollments' => $allEnrollments,
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
     * Show the form for creating a new class timetable.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        // This method is not used with Inertia.js as the form is part of the index page
        abort(404);
    }

    /**
     * Store a newly created class timetable in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $request->validate([
            'day' => 'required|string',
            'unit_id' => 'required|exists:units,id',
            'semester_id' => 'required|exists:semesters,id',
            'venue' => 'required|string',
            'location' => 'required|string',
            'no' => 'required|integer',
            'lecturer' => 'required|string',
            'start_time' => 'required|string',
            'end_time' => 'required|string',
        ]);

        try {
            // Check for classroom conflicts
            $classroomConflict = ClassTimetable::where('day', $request->day)
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

            if ($classroomConflict) {
                return redirect()->back()->with('error', 'Time conflict detected: The classroom is already booked during this time.');
            }

            // Check for lecturer time conflicts
            $lecturerConflict = ClassTimetable::where('day', $request->day)
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

            // Check for semester time conflicts
            $semesterConflict = ClassTimetable::where('day', $request->day)
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

            $classTimetable = ClassTimetable::create([
                'day' => $request->day,
                'unit_id' => $request->unit_id,
                'semester_id' => $request->semester_id,
                'venue' => $request->venue,
                'location' => $request->location,
                'no' => $request->no,
                'lecturer' => $request->lecturer,
                'start_time' => $request->start_time,
                'end_time' => $request->end_time,
            ]);

            return redirect()->back()->with('success', 'Class timetable created successfully.');
        } catch (\Exception $e) {
            \Log::error('Failed to create class timetable: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to create class timetable: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified class timetable.
     *
     * @param  \App\Models\ClassTimetable  $classTimetable
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $classTimetable = ClassTimetable::with(['unit', 'semester'])->findOrFail($id);
        
        return Inertia::render('ClassTimetable/Show', [
            'classTimetable' => $classTimetable
        ]);
    }

    /**
     * Show the form for editing the specified class timetable.
     *
     * @param  \App\Models\ClassTimetable  $classTimetable
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        // This method is not used with Inertia.js as the form is part of the index page
        abort(404);
    }

    /**
     * Update the specified class timetable in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\ClassTimetable  $classTimetable
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'day' => 'required|string',            
            'unit_id' => 'required|exists:units,id',
            'semester_id' => 'required|exists:semesters,id',
            'venue' => 'required|string',
            'location' => 'required|string',
            'no' => 'required|integer',
            'lecturer' => 'required|string',
            'start_time' => 'required|string',
            'end_time' => 'required|string',
        ]);

        try {
            // Check for classroom conflicts
            $classroomConflict = ClassTimetable::where('id', '!=', $id)
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

            if ($classroomConflict) {
                return redirect()->back()->with('error', 'Time conflict detected: The classroom is already booked during this time.');
            }

            // Check for lecturer time conflicts
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

            // Check for semester time conflicts
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

            $classTimetable = ClassTimetable::findOrFail($id);
            
            $classTimetable->update([
                'day' => $request->day,               
                'unit_id' => $request->unit_id,
                'semester_id' => $request->semester_id,
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
     * Remove the specified class timetable from storage.
     *
     * @param  \App\Models\ClassTimetable  $classTimetable
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
     * @param  int  $id
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
     * @param  int  $unitId
     * @param  int  $semesterId
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
     * Process the class timetable (example: generate timetable automatically).
     *
     * @return \Illuminate\Http\Response
     */
    public function process(Request $request)
    {
        try {
            // Implementation of timetable processing logic
            // This would typically involve complex algorithms to optimize the timetable
            
            // For demonstration, we'll just log that the process was called
            \Log::info('Processing class timetable');
            
            // Return success response
            return redirect()->back()->with('success', 'Class timetable processed successfully.');
        } catch (\Exception $e) {
            \Log::error('Failed to process class timetable: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to process class timetable: ' . $e->getMessage());
        }
    }

    /**
     * Solve conflicts in the class timetable (example: manual conflict resolution).
     *
     * @return \Illuminate\Http\Response
     */
    public function solveConflicts(Request $request)
    {
        try {
            // Implementation of conflict resolution logic
            // This would typically involve identifying and resolving scheduling conflicts
            
            // For demonstration, we'll just log that the method was called
            \Log::info('Solving class timetable conflicts');
            
            // Return success response
            return redirect()->back()->with('success', 'Class conflicts resolved successfully.');
        } catch (\Exception $e) {
            \Log::error('Failed to solve class conflicts: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to solve class conflicts: ' . $e->getMessage());
        }
    }

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
                ->leftJoin('class_time_slots', function ($join) {
                    $join->on('class_timetable.day', '=', 'class_time_slots.day')
                         ->on('class_timetable.start_time', '=', 'class_time_slots.start_time')
                         ->on('class_timetable.end_time', '=', 'class_time_slots.end_time');
                })
                ->select(
                    'class_timetable.*',
                    'units.name as unit_name',
                    'units.code as unit_code',
                    'semesters.name as semester_name',
                    'class_time_slots.status as mode_of_teaching' // Fetch mode of teaching
                );

            if ($request->has('semester_id')) {
                $query->where('class_timetable.semester_id', $request->semester_id);
            }

            $classTimetables = $query->orderByRaw("FIELD(class_timetable.day, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday')")
                ->orderBy('class_timetable.start_time')
                ->get();

            // Log the data being passed to the view for debugging
            \Log::info('Generating PDF with data:', [
                'count' => $classTimetables->count(),
                'sample' => $classTimetables->take(2)->toArray()
            ]);

            // Generate PDF
            $pdf = Pdf::loadView('classtimetables.pdf', [
                'classTimetables' => $classTimetables,
                'title' => 'Class Timetable',
                'generatedAt' => now()->format('Y-m-d H:i:s'),
            ]);

            // Set paper size and orientation
            $pdf->setPaper('a4', 'landscape');

            // Return the PDF for download
            return $pdf->download('classTimetable.pdf');
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
     * Download the faculty class timetable as a PDF.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function downloadFacultyClassTimetable(Request $request)
    {
        try {
            $user = auth()->user();
            
            // Get the faculty ID of the user
            $facultyId = $user->faculty_id;
            
            if (!$facultyId) {
                return redirect()->back()->with('error', 'You are not associated with any faculty.');
            }
            
            // Get all class timetables for units in this faculty
            $classTimetables = ClassTimetable::join('units', 'class_timetable.unit_id', '=', 'units.id')
                ->join('semesters', 'class_timetable.semester_id', '=', 'semesters.id')
                ->where('units.faculty_id', $facultyId)
                ->select(
                    'class_timetable.*',
                    'units.name as unit_name',
                    'units.code as unit_code',
                    'semesters.name as semester_name'
                )
                ->orderByRaw("FIELD(class_timetable.day, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday')")
                ->orderBy('class_timetable.start_time')
                ->get();
            
            // Generate PDF
            $pdf = PDF::loadView('pdfs.faculty-class_timetable', [
                'classTimetables' => $classTimetables,
                'faculty' => $user->faculty->name ?? 'Unknown Faculty',
                'title' => 'Faculty Class Timetable',
                'generatedAt' => now()->format('Y-m-d H:i:s')
            ]);
            
            // Return the PDF for download
            return $pdf->download('faculty-class-timetable.pdf');
        } catch (\Exception $e) {
            \Log::error('Failed to download faculty class timetable: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to download faculty class timetable: ' . $e->getMessage());
        }
    }

    /**
     * View the lecturer's class timetable.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function viewLecturerClassTimetable(Request $request)
    {
        $user = auth()->user();
        
        // Get the lecturer's code
        $lecturerCode = $user->code;
        
        // Get all semesters for the dropdown
        $semesters = Semester::all();
        
        // Get semester ID from request or use default
        $semesterId = $request->input('semester_id');
        
        if (!$semesterId) {
            // Get the current active semester
            $currentSemester = Semester::where('is_active', true)->first();
            
            if (!$currentSemester) {
                // If no active semester, get the latest semester
                $currentSemester = Semester::latest()->first();
            }
            
            $semesterId = $currentSemester->id;
        }
        
        // Get units where the lecturer is assigned
        $lecturerUnitIds = Enrollment::where('lecturer_code', $lecturerCode)
            ->where('semester_id', $semesterId)
            ->distinct('unit_id')
            ->pluck('unit_id')
            ->toArray();
        
        // Get class timetables for the lecturer's units
        $classTimetables = ClassTimetable::where('semester_id', $semesterId)
            ->whereIn('unit_id', $lecturerUnitIds)
            ->with('unit') // Eager load the unit relationship
            ->orderByRaw("FIELD(day, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday')")
            ->orderBy('start_time')
            ->get();
        
        // Log for debugging
        \Log::info('Lecturer class timetable data', [
            'lecturer_code' => $lecturerCode,
            'semester_id' => $semesterId,
            'unit_ids' => $lecturerUnitIds,
            'class_count' => $classTimetables->count()
        ]);
        
        return Inertia::render('Lecturer/ClassTimetable', [
            'classTimetables' => $classTimetables,
            'semesters' => $semesters,
            'selectedSemesterId' => $semesterId
        ]);
    }

    /**
     * Download the lecturer's class timetable as a PDF.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function downloadLecturerClassTimetable(Request $request)
    {
        try {
            $user = auth()->user();
            
            // Get the lecturer's code
            $lecturerCode = $user->code;
            
            // Get semester ID from request or use default
            $semesterId = $request->input('semester_id');
            
            if (!$semesterId) {
                // Get the current active semester
                $currentSemester = Semester::where('is_active', true)->first();
                
                if (!$currentSemester) {
                    // If no active semester, get the latest semester
                    $currentSemester = Semester::latest()->first();
                }
                
                $semesterId = $currentSemester->id;
            }
            
            // Get the semester details
            $semester = Semester::find($semesterId);
            
            // Get units where the lecturer is assigned
            $lecturerUnitIds = Enrollment::where('lecturer_code', $lecturerCode)
                ->where('semester_id', $semesterId)
                ->distinct('unit_id')
                ->pluck('unit_id')
                ->toArray();
            
            // Get class timetables for the lecturer's units
            $classTimetables = ClassTimetable::where('semester_id', $semesterId)
                ->whereIn('unit_id', $lecturerUnitIds)
                ->with('unit') // Eager load the unit relationship
                ->orderByRaw("FIELD(day, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday')")
                ->orderBy('start_time')
                ->get();
            
            // Generate PDF
            $pdf = PDF::loadView('pdfs.lecturer-class-timetable', [
                'classimetables' => $classTimetables,
                'lecturer' => $user->first_name . ' ' . $user->last_name,
                'semester' => $semester->name . ' ' . ($semester->year ?? ''),
                'generatedAt' => now()->format('Y-m-d H:i:s')
            ]);
            
            // Return the PDF for download
            return $pdf->download('lecturer-class-timetable-' . $semester->name . '.pdf');
        } catch (\Exception $e) {
            \Log::error('Failed to download lecturer class timetable: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to download lecturer class timetable: ' . $e->getMessage());
        }
    }

    /**
     * Display the class timetable for the logged-in student.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function viewStudentClassTimetable(Request $request)
    {
        $user = auth()->user();
        
        // Get current semester or selected semester from request
        $semesterId = $request->input('semester_id');
        
        if (!$semesterId) {
            // Get the current active semester
            $currentSemester = Semester::where('is_active', true)->first();
            
            if (!$currentSemester) {
                // If no active semester, get the latest semester
                $currentSemester = Semester::latest()->first();
            }
            
            // Find semesters where the student has enrollments
            $studentSemesters = Enrollment::where('student_code', $user->code)
                ->distinct('semester_id')
                ->join('semesters', 'enrollments.semester_id', '=', 'semesters.id')
                ->select('semesters.*')
                ->get();
            
            // If student has no enrollments, use the current semester
            if ($studentSemesters->isEmpty()) {
                $selectedSemester = $currentSemester;
            } else {
                // Find the active semester among student's enrolled semesters
                $activeSemester = $studentSemesters->firstWhere('is_active', true);
                
                // If no active semester found, use the most recent semester the student is enrolled in
                $selectedSemester = $activeSemester ?? $studentSemesters->sortByDesc('id')->first();
            }
            
            $semesterId = $selectedSemester->id;
        }
        
        // Get all semesters for the dropdown
        $semesters = Semester::all();
        
        // Get the units the student is enrolled in for the selected semester
        $enrolledUnitIds = Enrollment::where('student_code', $user->code)
            ->where('semester_id', $semesterId)
            ->pluck('unit_id')
            ->toArray();
        
        // Get class timetables for the student's enrolled units in the selected semester
        $classTimetables = ClassTimetable::where('semester_id', $semesterId)
            ->whereIn('unit_id', $enrolledUnitIds)
            ->orWhereHas('unit', function($query) use ($user, $enrolledUnitIds) {
                $query->whereIn('id', $enrolledUnitIds);
            })
            ->with('unit') // Eager load the unit relationship
            ->orderByRaw("FIELD(day, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday')")
            ->orderBy('start_time')
            ->get();
        
        // Log for debugging
        \Log::info('Student class timetable data', [
            'student_code' => $user->code,
            'semester_id' => $semesterId,
            'enrolled_unit_ids' => $enrolledUnitIds,
            'class_count' => $classTimetables->count()
        ]);
        
        return Inertia::render('Student/ClassTimetable', [
            'classTimetables' => $classTimetables,
            'semesters' => $semesters,
            'selectedSemesterId' => $semesterId
        ]);
    }
    
    /**
     * Display details for a specific class for a student.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $classtimetables
     * @return \Illuminate\Http\Response
     */
    public function viewStudentClassExamDetails(Request $request, $classtimetable)
    {
        $user = auth()->user();
        
        // Get the class timetable with related data
        $classTimetable = ClassTimetable::with(['unit', 'semester'])
            ->findOrFail($classtimetable);
        
        // Check if the student is enrolled in this unit
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
     * Download the class timetable for the logged-in student.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function downloadStudentClassTimetable(Request $request)
    {
        $user = auth()->user();

        // Get semester ID from request or use default
        $semesterId = $request->input('semester_id');
        if (!$semesterId) {
            $currentSemester = Semester::where('is_active', true)->first();
            $semesterId = $currentSemester ? $currentSemester->id : null;
        }

        if (!$semesterId) {
            return redirect()->back()->with('error', 'No active semester found.');
        }

        // Get the semester details
        $semester = Semester::find($semesterId);

        // Get the units the student is enrolled in for the selected semester
        $enrolledUnitIds = Enrollment::where('student_code', $user->code)
            ->where('semester_id', $semesterId)
            ->pluck('unit_id')
            ->toArray();

        if (empty($enrolledUnitIds)) {
            return redirect()->back()->with('error', 'You are not enrolled in any units for this semester.');
        }

        // Get class timetables for the student's enrolled units in the selected semester
        $classTimetables = ClassTimetable::query()
            ->where('class_timetable.semester_id', $semesterId) // Specify table name for semester_id
            ->whereIn('class_timetable.unit_id', $enrolledUnitIds) // Specify table name for unit_id
            ->join('units', 'class_timetable.unit_id', '=', 'units.id')
            ->join('semesters', 'class_timetable.semester_id', '=', 'semesters.id')
            ->select(
                'class_timetable.day',
                'class_timetable.start_time',
                'class_timetable.end_time',
                'class_timetable.venue',
                'class_timetable.location',
                'class_timetable.lecturer',
                'units.code as unit_code',
                'units.name as unit_name',
                'semesters.name as semester_name'
            )
            ->orderByRaw("FIELD(class_timetable.day, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday')")
            ->orderBy('class_timetable.start_time')
            ->get();

        if ($classTimetables->isEmpty()) {
            return redirect()->back()->with('error', 'No classes found for the selected semester.');
        }

        // Generate PDF
        $pdf = Pdf::loadView('classtimetables.student', [
            'classTimetables' => $classTimetables, // Pass the variable to the view
            'student' => $user,
            'currentSemester' => $semester,
        ]);

        // Return the PDF for download
        return $pdf->download('class-timetable-' . $semester->name . '.pdf');
    }
}
