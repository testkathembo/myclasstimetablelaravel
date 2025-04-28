<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\ExamTimetable;
use App\Models\Semester;
use App\Models\Enrollment;
use App\Models\TimeSlot;
use App\Models\Classroom;
use App\Models\Unit;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\TimetableExport;
use Barryvdh\DomPDF\Facade\Pdf;

class ExamTimetableController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        Log::info('Accessing Exam Timetable', [
            'user_id' => $user->id,
            'roles' => $user->getRoleNames(),
            'permissions' => $user->getAllPermissions()->pluck('name'),
        ]);

        // Check if user has permission to view timetables
        if (!auth()->user()->can('manage-timetable')) {
            abort(403, 'Unauthorized action.');
        }

        $perPage = $request->input('per_page', 10);
        $search = $request->input('search', '');

        // Fetch exam timetables with related data
        $examTimetables = ExamTimetable::query()
            ->leftJoin('units', 'exam_timetables.unit_id', '=', 'units.id')
            ->leftJoin('semesters', 'exam_timetables.semester_id', '=', 'semesters.id')
            ->select(
                'exam_timetables.id',
                'exam_timetables.date',
                'exam_timetables.day',
                'exam_timetables.start_time',
                'exam_timetables.end_time',
                'exam_timetables.venue',
                'exam_timetables.location',
                'exam_timetables.no',
                'exam_timetables.chief_invigilator',
                'units.name as unit_name',
                'units.code as unit_code', // Include unit_code
                'semesters.name as semester_name',
                'exam_timetables.semester_id'
            )
            ->when($search, function ($query, $search) {
                return $query->where('exam_timetables.day', 'like', "%{$search}%")
                             ->orWhere('exam_timetables.venue', 'like', "%{$search}%")
                             ->orWhere('units.name', 'like', "%{$search}%")
                             ->orWhere('units.code', 'like', "%{$search}%"); // Allow search by unit_code
            })
            ->paginate($perPage);

        // Get all necessary data for the form
        $semesters = Semester::all();
        $classrooms = Classroom::all();
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
        Log::info('Enrollments data for exam timetable', [
            'enrollments_count' => $enrollments->count(),
            'sample_enrollments' => $enrollments->take(5)->toArray(),
        ]);

        // Log units for debugging
        Log::info('Units data for exam timetable', [
            'units_count' => $units->count(),
            'units' => $units->toArray(),
        ]);

        return Inertia::render('ExamTimetable/index', [
            'examTimetables' => $examTimetables,
            'perPage' => $perPage,
            'search' => $search,
            'semesters' => $semesters,
            'enrollments' => $enrollments,
            'classrooms' => $classrooms,
            'timeSlots' => $timeSlots,
            'units' => $units,
            'can' => [
                'create' => auth()->user()->can('create-timetable'),
                'edit' => auth()->user()->can('edit-timetable'),
                'delete' => auth()->user()->can('delete-timetable'),
                'process' => auth()->user()->can('process-timetable'),
                'solve_conflicts' => auth()->user()->can('solve-conflicts'),
                'download' => auth()->user()->can('download-timetable'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        // Check if user has permission to create timetables
        if (!auth()->user()->can('create-timetable')) {
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
        ]);
        
        // Get the classroom capacity
        $classroom = Classroom::where('name', $validated['venue'])->first();
        
        if (!$classroom) {
            return redirect()->back()->with('error', 'Classroom not found.');
        }
        
        // Check for time conflicts in the same venue
        $conflictingExams = ExamTimetable::where('venue', $validated['venue'])
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
        $totalStudents = $conflictingExams->sum('no');
        
        // Check if adding this exam would exceed classroom capacity
        if ($totalStudents + $validated['no'] > $classroom->capacity) {
            return redirect()->back()->with('error', 
                "Cannot schedule this exam. The classroom {$validated['venue']} has a capacity of {$classroom->capacity}, " .
                "but there would be " . ($totalStudents + $validated['no']) . " students scheduled at this time " .
                "(exceeding capacity by " . (($totalStudents + $validated['no']) - $classroom->capacity) . " students)."
            )->withInput();
        }
        
        // Check for unit conflicts (same unit scheduled at the same time)
        $unitConflicts = ExamTimetable::where('unit_id', $validated['unit_id'])
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
        
        // Create the exam timetable
        ExamTimetable::create($validated);

        return redirect()->back()->with('success', 'Exam timetable created successfully.');
    }
    
    public function update(Request $request, $id)
    {
        // Check if user has permission to edit timetables
        if (!auth()->user()->can('edit-timetable')) {
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
        ]);
        
        // Get the classroom capacity
        $classroom = Classroom::where('name', $validated['venue'])->first();
        
        if (!$classroom) {
            return redirect()->back()->with('error', 'Classroom not found.');
        }
        
        // Check for time conflicts in the same venue, excluding the current exam
        $conflictingExams = ExamTimetable::where('id', '!=', $id)
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
        $totalStudents = $conflictingExams->sum('no');
        
        // Check if updating this exam would exceed classroom capacity
        if ($totalStudents + $validated['no'] > $classroom->capacity) {
            return redirect()->back()->with('error', 
                "Cannot schedule this exam. The classroom {$validated['venue']} has a capacity of {$classroom->capacity}, " .
                "but there would be " . ($totalStudents + $validated['no']) . " students scheduled at this time " .
                "(exceeding capacity by " . (($totalStudents + $validated['no']) - $classroom->capacity) . " students)."
            )->withInput();
        }
        
        // Check for unit conflicts (same unit scheduled at the same time), excluding the current exam
        $unitConflicts = ExamTimetable::where('id', '!=', $id)
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
        
        // Update the exam timetable
        $timetable = ExamTimetable::findOrFail($id);
        $timetable->update($validated);

        return redirect()->back()->with('success', 'Exam timetable updated successfully.');
    }

    public function destroy($id)
    {
        // Check if user has permission to delete timetables
        if (!auth()->user()->can('delete-timetable')) {
            abort(403, 'Unauthorized action.');
        }

        $timetable = ExamTimetable::findOrFail($id);
        $timetable->delete();

        // Redirect to the correct route after deletion
        return redirect()->route('exam-timetables.index')->with('success', 'Exam timetable deleted successfully.');
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
        $query = ExamTimetable::where('venue', $validated['venue'])
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
        
        $conflictingExams = $query->get();
        $totalStudents = $conflictingExams->sum('no');
        
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
    
    // Process timetable (for Exam Office)
    public function process(Request $request)
    {
        // Check permission
        if (!auth()->user()->can('process-timetable')) {
            abort(403, 'Unauthorized action.');
        }
        
        // Process logic here...
        // This is where you would implement your algorithm to generate timetables
        
        return redirect()->back()->with('success', 'Timetable processing completed.');
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
        $timetables = ExamTimetable::with(['unit', 'semester'])->get(); // Eager-load unit and semester relationships

        $pdf = Pdf::loadView('timetables.pdf', [
            'timetables' => $timetables->map(function ($timetable) {
                return [
                    'id' => $timetable->id,
                    'day' => $timetable->day,
                    'date' => $timetable->date,
                    'unit_code' => $timetable->unit->code ?? 'N/A', // Ensure unit_code is fetched
                    'unit_name' => $timetable->unit->name ?? 'N/A', // Ensure unit_name is fetched
                    'semester_name' => $timetable->semester->name ?? 'N/A',
                    'start_time' => $timetable->start_time,
                    'end_time' => $timetable->end_time,
                    'venue' => $timetable->venue,
                    'chief_invigilator' => $timetable->chief_invigilator,
                ];
            }),
        ]);

        return $pdf->download('exam-timetable.pdf');
    }
}
