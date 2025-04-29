<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\ClassTimetable;
use App\Models\Semester;
use App\Models\Enrollment;
use App\Models\TimeSlot;
use App\Models\Examroom;
use App\Models\Unit;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\TimetableExport;
use Barryvdh\DomPDF\Facade\Pdf;

class ClassTimetableController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();

        // Log user roles and permissions for debugging
        \Log::info('User accessing /classtimetable', [
            'user_id' => $user->id,
            'roles' => $user->getRoleNames(),
            'permissions' => $user->getAllPermissions()->pluck('name'),
        ]);

        if (!$user->can('manage-classtimetables')) {
            abort(403, 'Unauthorized action.');
        }

        $perPage = $request->input('per_page', 10);
        $search = $request->input('search', '');

        // Fetch classtimetables with related data
        $classTimetables = ClassTimetable::query()
            ->leftJoin('units', 'class_timetables.unit_id', '=', 'units.id')
            ->leftJoin('semesters', 'class_timetables.semester_id', '=', 'semesters.id')
            ->select(
                'class_timetables.id',
                'class_timetables.date',
                'class_timetables.day',
                'class_timetables.start_time',
                'class_timetables.end_time',
                'class_timetables.venue',
                'class_timetables.location',
                'class_timetables.no',
                'class_timetables.chief_invigilator',
                'class_timetables.status', // Include status
                'units.name as unit_name',
                'units.code as unit_code', // Include unit_code
                'semesters.name as semester_name',
                'class_timetables.semester_id'
            )
            ->when($request->has('search') && $request->search !== '', function ($query) use ($request) {
                $search = $request->search;
                $query->where('day', 'like', "%{$search}%")
                      ->orWhere('date', 'like', "%{$search}%"); // Use 'date' instead of 'exam_date'
            })
            ->orderBy('date') // Use 'date' instead of 'exam_date'
            ->paginate($request->get('per_page', 10));

        // Get all necessary data for the form
        $semesters = Semester::all();
        $examrooms = Examroom::all();
        $timeSlots = TimeSlot::all();
        
        // Fetch units with semester_id
        $units = Unit::select('id', 'code', 'name', 'semester_id')->get();
        
        // Fetch enrollments with lecturer's full name
        $enrollments = Enrollment::query()
            ->leftJoin('users as lecturers', 'enrollments.lecturer_id', '=', 'lecturers.id')
            ->select(
                'enrollments.*',
                DB::raw("CONCAT(lecturers.first_name, ' ', lecturers.last_name) as lecturer_name") // Construct lecturer's full name
            )
            ->get();

        // Log enrollments for debugging
        Log::info('Enrollments data for class timetable', [
            'enrollments_count' => $enrollments->count(),
            'sample_enrollments' => $enrollments->take(5)->toArray(),
        ]);

        // Log units for debugging
        Log::info('Units data for class timetable', [
            'units_count' => $units->count(),
            'units' => $units->toArray(),
        ]);

        return Inertia::render('ClassTimetable/index', [
            'classTimetables' => $classTimetables,
            'perPage' => $perPage,
            'search' => $search,
            'semesters' => $semesters,
            'enrollments' => $enrollments,
            'examrooms' => $examrooms,
            'timeSlots' => $timeSlots,
            'units' => $units,
            'can' => [
                'create' => auth()->user()->can('create-classtimetable'),
                'edit' => auth()->user()->can('edit-classtimetable'),
                'delete' => auth()->user()->can('delete-classtimetable'),
                'process' => auth()->user()->can('process-classtimetable'),
                'solve_conflicts' => auth()->user()->can('solve-conflicts'),
                'download' => auth()->user()->can('download-classtimetable'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        // Check if user has permission to create classtimetables
        if (!auth()->user()->can('create-classtimetable')) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'semester_id' => 'required|exists:semesters,id',
            'unit_id' => 'required|exists:units,id',
            'day' => 'required|string',
            'date' => 'required|date',
            'start_time' => 'required|date_format:H:i', // Validate H:i format
            'end_time' => 'required|date_format:H:i|after:start_time', // Validate H:i format and ensure end_time is after start_time
            'venue' => 'required|string',
            'location' => 'nullable|string',
            'no' => 'required|integer',
            'chief_invigilator' => 'required|string',
            'status' => 'required|in:physical,online', // Validate status
        ]);
        
        // Get the classroom capacity
        $classroom = Classroom::where('name', $validated['venue'])->first();
        
        if (!$classroom) {
            return redirect()->back()->with('error', 'Classroom not found.');
        }
        
        // Check for time conflicts in the same venue
        $conflictingClassTimetables = ClassTimetable::where('venue', $validated['venue'])
            ->where('date', $validated['date'])
            ->where(function($query) use ($validated) {
                $query->where(function($q) use ($validated) {
                    $q->where('start_time', '<=', $validated['start_time'])
                      ->where('end_time', '>', $validated['start_time']);
                })->orWhere(function($q) use ($validated) {
                    $q->where('start_time', '<', $validated['end_time'])
                      ->where('end_time', '>=', $validated['end_time']);
                })->orWhere(function($q) use ($validated) {
                    $q->where('start_time', '>=', $validated['start_time'])
                      ->where('end_time', '<=', $validated['end_time']);
                });
            })
            ->get();
        
        // Calculate total students already scheduled
        $totalStudents = $conflictingClassTimetables->sum('no');
        
        // Check if adding this exam would exceed classroom capacity
        if ($totalStudents + $validated['no'] > $classroom->capacity) {
            return redirect()->back()->with('error', 
                "Cannot schedule this exam. The classroom {$validated['venue']} has a capacity of {$classroom->capacity}, " .
                "but there would be " . ($totalStudents + $validated['no']) . " students scheduled at this time " .
                "(exceeding capacity by " . (($totalStudents + $validated['no']) - $classroom->capacity) . " students)."
            )->withInput();
        }
        
        // Check for unit conflicts (same unit scheduled at the same time)
        $unitConflicts = ClassTimetable::where('unit_id', $validated['unit_id'])
            ->where('date', $validated['date'])
            ->where(function($query) use ($validated) {
                $query->where(function($q) use ($validated) {
                    $q->where('start_time', '<=', $validated['start_time'])
                      ->where('end_time', '>', $validated['start_time']);
                })->orWhere(function($q) use ($validated) {
                    $q->where('start_time', '<', $validated['end_time'])
                      ->where('end_time', '>=', $validated['end_time']);
                })->orWhere(function($q) use ($validated) {
                    $q->where('start_time', '>=', $validated['start_time'])
                      ->where('end_time', '<=', $validated['end_time']);
                });
            })
            ->get();
            
        if ($unitConflicts->count() > 0) {
            return redirect()->back()->with('error', 
                "Cannot schedule this exam. This unit already has an exam scheduled at this time."
            )->withInput();
        }
        
        // Create the class timetable
        ClassTimetable::create($validated);

        return redirect()->back()->with('success', 'Class timetable created successfully.');
    }
    
    public function update(Request $request, $id)
    {
        // Check if user has permission to edit classtimetables
        if (!auth()->user()->can('edit-classtimetable')) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'semester_id' => 'required|exists:semesters,id',
            'unit_id' => 'required|exists:units,id',
            'day' => 'required|string',
            'date' => 'required|date',
            'start_time' => 'required|date_format:H:i', // Validate H:i format
            'end_time' => 'required|date_format:H:i|after:start_time', // Validate H:i format and ensure end_time is after start_time
            'venue' => 'required|string',
            'location' => 'nullable|string',
            'no' => 'required|integer',
            'chief_invigilator' => 'required|string',
            'status' => 'required|in:physical,online', // Validate status
        ]);
        
        // Get the classroom capacity
        $classroom = Classroom::where('name', $validated['venue'])->first();
        
        if (!$classroom) {
            return redirect()->back()->with('error', 'Classroom not found.');
        }
        
        // Check for time conflicts in the same venue, excluding the current exam
        $conflictingClassTimetables = ClassTimetable::where('id', '!=', $id)
            ->where('venue', $validated['venue'])
            ->where('date', $validated['date'])
            ->where(function($query) use ($validated) {
                $query->where(function($q) use ($validated) {
                    $q->where('start_time', '<=', $validated['start_time'])
                      ->where('end_time', '>', $validated['start_time']);
                })->orWhere(function($q) use ($validated) {
                    $q->where('start_time', '<', $validated['end_time'])
                      ->where('end_time', '>=', $validated['end_time']);
                })->orWhere(function($q) use ($validated) {
                    $q->where('start_time', '>=', $validated['start_time'])
                      ->where('end_time', '<=', $validated['end_time']);
                });
            })
            ->get();
        
        // Calculate total students already scheduled
        $totalStudents = $conflictingClassTimetables->sum('no');
        
        // Check if updating this exam would exceed classroom capacity
        if ($totalStudents + $validated['no'] > $classroom->capacity) {
            return redirect()->back()->with('error', 
                "Cannot schedule this exam. The classroom {$validated['venue']} has a capacity of {$classroom->capacity}, " .
                "but there would be " . ($totalStudents + $validated['no']) . " students scheduled at this time " .
                "(exceeding capacity by " . (($totalStudents + $validated['no']) - $classroom->capacity) . " students)."
            )->withInput();
        }
        
        // Check for unit conflicts (same unit scheduled at the same time), excluding the current exam
        $unitConflicts = ClassTimetable::where('id', '!=', $id)
            ->where('unit_id', $validated['unit_id'])
            ->where('date', $validated['date'])
            ->where(function($query) use ($validated) {
                $query->where(function($q) use ($validated) {
                    $q->where('start_time', '<=', $validated['start_time'])
                      ->where('end_time', '>', $validated['start_time']);
                })->orWhere(function($q) use ($validated) {
                    $q->where('start_time', '<', $validated['end_time'])
                      ->where('end_time', '>=', $validated['end_time']);
                })->orWhere(function($q) use ($validated) {
                    $q->where('start_time', '>=', $validated['start_time'])
                      ->where('end_time', '<=', $validated['end_time']);
                });
            })
            ->get();
            
        if ($unitConflicts->count() > 0) {
            return redirect()->back()->with('error', 
                "Cannot schedule this exam. This unit already has an exam scheduled at this time."
            )->withInput();
        }
        
        // Update the class timetable
        $classtimetable = ClassTimetable::findOrFail($id);
        $classtimetable->update($validated);

        return redirect()->route('classtimetables.index')->with('success', 'Class timetable updated successfully.');
    }

    public function destroy($id)
    {
        // Check if user has permission to delete classtimetables
        if (!auth()->user()->can('delete-classtimetable')) {
            abort(403, 'Unauthorized action.');
        }

        $classtimetable = ClassTimetable::findOrFail($id);
        $classtimetable->delete();

        // Redirect to the correct route after deletion
        return redirect()->route('class-timetables.index')->with('success', 'Class timetable deleted successfully.');
    }
    
    // API endpoints for the frontend
    
    public function getTimeSlots()
    {
        return response()->json(TimeSlot::all());
    }
    
    public function getUnitsBySemester($semesterId)
    {
        // Log the request for debugging
        Log::info('Fetching units for semester', ['semester_id' => $semesterId]);
        
        // Get units for this semester
        $units = Unit::where('semester_id', $semesterId)->get();
        
        // Log the results
        Log::info('Units found for semester', [
            'semester_id' => $semesterId,
            'count' => $units->count(),
            'units' => $units->toArray()
        ]);
        
        return response()->json($units);
    }
    
    public function getEnrollmentCount($unitId, $semesterId)
    {
        $enrollment = Enrollment::where('unit_id', $unitId)
            ->where('semester_id', $semesterId)
            ->first();
            
        return response()->json([
            'count' => $enrollment ? $enrollment->student_count : 0
        ]);
    }
    
    public function checkVenueCapacity(Request $request)
    {
        $validated = $request->validate([
            'venue' => 'required|string',
            'date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i',
            'student_count' => 'required|integer',
            'exam_id' => 'nullable|integer' // For excluding current exam when editing
        ]);
        
        $classroom = Classroom::where('name', $validated['venue'])->first();
        
        if (!$classroom) {
            return response()->json([
                'success' => false,
                'message' => 'Classroom not found.'
            ], 404);
        }
        
        // Check for time conflicts in the same venue
        $query = ClassTimetable::where('venue', $validated['venue'])
            ->where('date', $validated['date'])
            ->where(function($q) use ($validated) {
                $q->where(function($subq) use ($validated) {
                    $subq->where('start_time', '<=', $validated['start_time'])
                        ->where('end_time', '>', $validated['start_time']);
                })->orWhere(function($subq) use ($validated) {
                    $subq->where('start_time', '<', $validated['end_time'])
                        ->where('end_time', '>=', $validated['end_time']);
                })->orWhere(function($subq) use ($validated) {
                    $subq->where('start_time', '>=', $validated['start_time'])
                        ->where('end_time', '<=', $validated['end_time']);
                });
            });
            
        // Exclude current exam if editing
        if (isset($validated['exam_id'])) {
            $query->where('id', '!=', $validated['exam_id']);
        }
        
        $conflictingClassTimetables = $query->get();
        $totalStudents = $conflictingClassTimetables->sum('no');
        
        $hasCapacity = ($totalStudents + $validated['student_count']) <= $classroom->capacity;
        
        return response()->json([
            'success' => true,
            'has_capacity' => $hasCapacity,
            'classroom_capacity' => $classroom->capacity,
            'current_students' => $totalStudents,
            'total_students' => $totalStudents + $validated['student_count'],
            'exceeding_by' => $hasCapacity ? 0 : ($totalStudents + $validated['student_count'] - $classroom->capacity)
        ]);
    }
    
    // Process classtimetable (for Exam Office)
    public function process(Request $request)
    {
        // Check permission
        if (!auth()->user()->can('process-classtimetable')) {
            abort(403, 'Unauthorized action.');
        }
        
        // Process logic here...
        // This is where you would implement your algorithm to generate classtimetables
        
        return redirect()->back()->with('success', 'Classtimetable processing completed.');
    }
    
    // Solve conflicts (for Exam Office)
    public function solveConflicts(Request $request)
    {
        // Check permission
        if (!auth()->user()->can('solve-conflicts')) {
            abort(403, 'Unauthorized action.');
        }
        
        // Conflict resolution logic here...
        // This is where you would identify and resolve scheduling conflicts
        
        return redirect()->back()->with('success', 'Conflicts resolved successfully.');
    }
    
    public function downloadTimetable(Request $request)
    {
        // Check if user has permission to download classtimetables
        if (!auth()->user()->can('download-classtimetable') && !auth()->user()->can('download-own-classtimetable')) {
            abort(403, 'Unauthorized action.');
        }

        $user = auth()->user();
        $classTimetables = collect(); // Initialize as an empty collection

        // If user is a student or lecturer with download-own-classtimetable permission
        if ($user->can('download-own-classtimetable')) {
            if ($user->hasRole('Student')) {
                // Get student's enrolled units
                $enrolledUnitIds = $user->enrolledUnits()->pluck('units.id')->toArray();
                $classTimetables = ClassTimetable::whereIn('unit_id', $enrolledUnitIds)
                    ->with(['unit', 'semester'])
                    ->get();
            } elseif ($user->hasRole('Lecturer')) {
                // Get lecturer's assigned units
                $assignedUnitIds = $user->assignedUnits()->pluck('units.id')->toArray();
                $classTimetables = ClassTimetable::whereIn('unit_id', $assignedUnitIds)
                    ->with(['unit', 'semester'])
                    ->get();
            }
        } else {
            // Admin or other role with download-classtimetable permission
            $classTimetables = ClassTimetable::with(['unit', 'semester'])->get();
        }

        // Ensure $classTimetables is not null
        $classTimetables = $classTimetables ?? collect();

        // Generate the PDF
        $pdf = Pdf::loadView('timetables.pdf', [
            'classtimetables' => $classTimetables->map(function ($classtimetable) {
                return [
                    'id' => $classtimetable->id,
                    'day' => $classtimetable->day,
                    'unit_code' => $classtimetable->unit->code ?? 'N/A',
                    'unit_name' => $classtimetable->unit->name ?? 'N/A',
                    'semester_name' => $classtimetable->semester->name ?? 'N/A',
                    'start_time' => $classtimetable->start_time,
                    'end_time' => $classtimetable->end_time,
                    'venue' => $classtimetable->venue,
                    'lecturer' => $classtimetable->lecturer,
                    'status' => $classtimetable->status, // Include status
                ];
            }),
        ]);

        // Return the PDF as a download
        return $pdf->download('class-timetable.pdf');
    }

    /**
     * View student classtimetable
     */
    public function viewStudentTimetable(Request $request)
    {
        $user = $request->user();
        
        // Log the request for debugging
        Log::info('Student viewing classtimetable', [
            'user_id' => $user->id,
            'name' => $user->name,
            'roles' => $user->getRoleNames(),
            'permissions' => $user->getAllPermissions()->pluck('name'),
        ]);
        
        // Check if user has the Student role
        if (!$user->hasRole('Student')) {
            Log::warning('Non-student attempting to access student classtimetable', [
                'user_id' => $user->id,
                'roles' => $user->getRoleNames(),
            ]);
            abort(403, 'You must be a student to access this page.');
        }
        
        // Get current semester (you might want to implement logic to determine the current semester)
        $currentSemester = Semester::where('is_active', true)->first();
        if (!$currentSemester) {
            $currentSemester = Semester::latest()->first();
        }
        
        // Get student's enrolled units
        $enrolledUnits = $user->enrolledUnits()
            ->when($currentSemester, function($query) use ($currentSemester) {
                return $query->where('semester_id', $currentSemester->id);
            })
            ->get();
            
        // Get class timetables for these units
        $unitIds = $enrolledUnits->pluck('id')->toArray();
        $classTimetables = ClassTimetable::whereIn('unit_id', $unitIds)
            ->with(['unit', 'semester'])
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();
            
        // Log the results for debugging
        Log::info('Student classtimetable data', [
            'user_id' => $user->id,
            'enrolled_units_count' => count($enrolledUnits),
            'enrolled_unit_ids' => $unitIds,
            'class_timetables_count' => count($classTimetables),
        ]);
            
        return Inertia::render('Student/Timetable', [
            'classTimetables' => $classTimetables,
            'currentSemester' => $currentSemester,
            'enrolledUnits' => $enrolledUnits,
        ]);
    }
    
    /**
     * View lecturer classtimetable
     */
    public function viewLecturerTimetable(Request $request)
    {
        $user = $request->user();
        
        // Check if user has permission to view their own classtimetable
        if (!$user->can('view-own-classtimetable') || !$user->hasRole('Lecturer')) {
            abort(403, 'Unauthorized action.');
        }
        
        Log::info('Lecturer viewing classtimetable', [
            'user_id' => $user->id,
            'name' => $user->name,
        ]);
        
        // Get current semester
        $currentSemester = Semester::where('is_active', true)->first();
        if (!$currentSemester) {
            $currentSemester = Semester::latest()->first();
        }
        
        // Get lecturer's assigned units
        $assignedUnits = $user->assignedUnits()
            ->where('semester_id', $currentSemester->id)
            ->get();
            
        // Get class timetables for these units
        $unitIds = $assignedUnits->pluck('id')->toArray();
        $classTimetables = ClassTimetable::whereIn('unit_id', $unitIds)
            ->with(['unit', 'semester'])
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();
            
        return Inertia::render('Lecturer/Timetable', [
            'classTimetables' => $classTimetables,
            'currentSemester' => $currentSemester,
            'assignedUnits' => $assignedUnits,
        ]);
    }
    
    /**
     * Download student classtimetable
     */
    public function downloadStudentTimetable(Request $request)
    {
        $user = $request->user();
        
        // Check if user has permission to download their own classtimetable
        if (!$user->can('download-own-classtimetable') || !$user->hasRole('Student')) {
            abort(403, 'Unauthorized action.');
        }
        
        // Get current semester
        $currentSemester = Semester::where('is_active', true)->first();
        if (!$currentSemester) {
            $currentSemester = Semester::latest()->first();
        }
        
        // Get student's enrolled units
        $enrolledUnitIds = $user->enrolledUnits()
            ->where('semester_id', $currentSemester->id)
            ->pluck('units.id')
            ->toArray();
            
        // Get class timetables for these units
        $classTimetables = ClassTimetable::whereIn('unit_id', $enrolledUnitIds)
            ->with(['unit', 'semester'])
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();
            
        $pdf = Pdf::loadView('timetables.student-pdf', [
            'classtimetables' => $classTimetables,
            'student' => $user,
            'semester' => $currentSemester,
        ]);

        return $pdf->download('my-class-timetable.pdf');
    }
    
    /**
     * Download lecturer classtimetable
     */
    public function downloadLecturerTimetable(Request $request)
    {
        $user = $request->user();
        
        // Check if user has permission to download their own classtimetable
        if (!$user->can('download-own-classtimetable') || !$user->hasRole('Lecturer')) {
            abort(403, 'Unauthorized action.');
        }
        
        // Get current semester
        $currentSemester = Semester::where('is_active', true)->first();
        if (!$currentSemester) {
            $currentSemester = Semester::latest()->first();
        }
        
        // Get lecturer's assigned units
        $assignedUnitIds = $user->assignedUnits()
            ->where('semester_id', $currentSemester->id)
            ->pluck('units.id')
            ->toArray();
            
        // Get class timetables for these units
        $classTimetables = ClassTimetable::whereIn('unit_id', $assignedUnitIds)
            ->with(['unit', 'semester'])
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();
            
        $pdf = Pdf::loadView('timetables.lecturer-pdf', [
            'classtimetables' => $classTimetables,
            'lecturer' => $user,
            'semester' => $currentSemester,
        ]);

        return $pdf->download('my-teaching-timetable.pdf');
    }
}
