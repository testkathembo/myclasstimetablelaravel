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
                'semesters.name as semester_name'
            )
            ->when($search, function ($query, $search) {
                return $query->where('exam_timetables.day', 'like', "%{$search}%")
                             ->orWhere('exam_timetables.venue', 'like', "%{$search}%")
                             ->orWhere('units.name', 'like', "%{$search}%")
                             ->orWhere('units.code', 'like', "%{$search}%"); // Allow search by unit_code
            })
            ->paginate($perPage);

        $semesters = Semester::all();

        return Inertia::render('ExamTimetable/index', [
            'examTimetables' => $examTimetables,
            'perPage' => $perPage,
            'search' => $search,
            'semesters' => $semesters,
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
            'enrollment_id' => 'required|exists:enrollments,id',
            'semester_id' => 'required|exists:semesters,id',
            'day' => 'required|string',
            'date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'group' => 'nullable|string',
            'venue' => 'required|string',
            'location' => 'nullable|string',
            'no' => 'required|integer',
            'chief_invigilator' => 'required|string',
        ]);

        // Rest of your store method remains the same...
        // Get the unit_id from the enrollment
        $enrollment = Enrollment::find($validated['enrollment_id']);
        
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
        
        // Add this right before the capacity check
        \Log::debug("Exam scheduling validation: Venue: {$validated['venue']}, Capacity: {$classroom->capacity}, Current students: {$totalStudents}, New students: {$validated['no']}, Total: " . ($totalStudents + $validated['no']));

        // Check if adding this exam would exceed classroom capacity
        if ($totalStudents + $validated['no'] > $classroom->capacity) {
            return redirect()->back()->with('error', 
                "Cannot schedule this exam. The classroom {$validated['venue']} has a capacity of {$classroom->capacity}, " .
                "but there would be " . ($totalStudents + $validated['no']) . " students scheduled at this time " .
                "(exceeding capacity by " . (($totalStudents + $validated['no']) - $classroom->capacity) . " students)."
            )->withInput();
        }
        
        // Create the data to be stored
        $data = [
            'semester_id' => $validated['semester_id'],
            'unit_id' => $enrollment->unit_id,
            'day' => $validated['day'],
            'date' => $validated['date'],
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
            'group' => $validated['group'] ?? '',
            'venue' => $validated['venue'],
            'no' => $validated['no'],
            'chief_invigilator' => $validated['chief_invigilator'],
        ];

        // Add location if it exists in the request
        if (isset($validated['location'])) {
            $data['location'] = $validated['location'];
        }

        ExamTimetable::create($data);

        return redirect()->back()->with('success', 'Exam timetable created successfully.');
    }
    
    public function update(Request $request, $id)
    {
        // Check if user has permission to edit timetables
        if (!auth()->user()->can('edit-timetable')) {
            abort(403, 'Unauthorized action.');
        }

        // Rest of your update method remains the same...
        $validated = $request->validate([
            'enrollment_id' => 'required|exists:enrollments,id',
            'semester_id' => 'required|exists:semesters,id',
            'day' => 'required|string',
            'date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'group' => 'nullable|string',
            'venue' => 'required|string',
            'location' => 'nullable|string',
            'no' => 'required|integer',
            'chief_invigilator' => 'required|string',
        ]);

        // Get the unit_id from the enrollment
        $enrollment = Enrollment::find($validated['enrollment_id']);
        
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
        
        // Add this right before the capacity check
        \Log::debug("Exam scheduling validation: Venue: {$validated['venue']}, Capacity: {$classroom->capacity}, Current students: {$totalStudents}, New students: {$validated['no']}, Total: " . ($totalStudents + $validated['no']));

        // Check if updating this exam would exceed classroom capacity
        if ($totalStudents + $validated['no'] > $classroom->capacity) {
            return redirect()->back()->with('error', 
                "Cannot schedule this exam. The classroom {$validated['venue']} has a capacity of {$classroom->capacity}, " .
                "but there would be " . ($totalStudents + $validated['no']) . " students scheduled at this time " .
                "(exceeding capacity by " . (($totalStudents + $validated['no']) - $classroom->capacity) . " students)."
            )->withInput();
        }
        
        // Create the data to be updated
        $data = [
            'semester_id' => $validated['semester_id'],
            'unit_id' => $enrollment->unit_id,
            'day' => $validated['day'],
            'date' => $validated['date'],
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
            'group' => $validated['group'] ?? '',
            'venue' => $validated['venue'],
            'no' => $validated['no'],
            'chief_invigilator' => $validated['chief_invigilator'],
        ];

        // Add location if it exists in the request
        if (isset($validated['location'])) {
            $data['location'] = $validated['location'];
        }

        $timetable = ExamTimetable::findOrFail($id);
        $timetable->update($data);

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

        return redirect()->route('exam-timetable.index')->with('success', 'Exam timetable deleted successfully.');
    }
    
    // Add methods for role-specific views
    
    // For all authenticated users with proper permissions
    public function view(Request $request)
    {
        // Check if user has permission to view timetables
        if (!auth()->user()->can('view-timetable') && 
            !auth()->user()->can('view-own-timetable')) {
            abort(403, 'Unauthorized action.');
        }
        
        $semester_id = $request->input('semester_id');
        $query = ExamTimetable::query()
            ->leftJoin('units', 'exam_timetables.unit_id', '=', 'units.id')
            ->select('exam_timetables.*', 'units.name as unit_name');
            
        if ($semester_id) {
            $query->where('exam_timetables.semester_id', $semester_id);
        }
        
        // Filter based on user role
        $user = auth()->user();
        if ($user->hasRole('Faculty Admin')) {
            $faculty = $user->faculty;
            $query->whereHas('unit', function($q) use ($faculty) {
                $q->where('faculty', $faculty);
            });
        } elseif ($user->hasRole('Lecturer')) {
            $lecturerId = $user->id;
            $query->whereHas('unit.enrollments', function($q) use ($lecturerId) {
                $q->where('lecturer_id', $lecturerId);
            });
        } elseif ($user->hasRole('Student')) {
            $studentId = $user->id;
            $query->whereHas('unit.enrollments', function($q) use ($studentId) {
                $q->where('student_id', $studentId);
            });
        }
        
        $timetables = $query->get();
        $semesters = Semester::all();
        
        return Inertia::render('ExamTimetable/View', [
            'timetables' => $timetables,
            'semesters' => $semesters,
            'current_semester' => $semester_id,
            'can' => [
                'download' => $user->can('download-timetable') || 
                              $user->can('download-own-timetable') || 
                              $user->can('download-faculty-timetable'),
            ]
        ]);
    }
    
    // For lecturers
    public function viewLecturerTimetable(Request $request)
    {
        // Check role and permission
        if (!auth()->user()->hasRole('Lecturer') || 
            !auth()->user()->can('view-own-timetable')) {
            abort(403, 'Unauthorized action.');
        }
        
        $lecturerId = auth()->id();
        $semester_id = $request->input('semester_id');
        
        $query = ExamTimetable::query()
            ->leftJoin('units', 'exam_timetables.unit_id', '=', 'units.id')
            ->select('exam_timetables.*', 'units.name as unit_name')
            ->whereHas('unit.enrollments', function($q) use ($lecturerId) {
                $q->where('lecturer_id', $lecturerId);
            });
            
        if ($semester_id) {
            $query->where('exam_timetables.semester_id', $semester_id);
        }
        
        $timetables = $query->get();
        $semesters = Semester::all();
        
        return Inertia::render('Lecturer/Timetable', [
            'timetables' => $timetables,
            'semesters' => $semesters,
            'current_semester' => $semester_id,
            'can' => [
                'download' => auth()->user()->can('download-own-timetable'),
            ]
        ]);
    }
    
    // For students
    public function viewStudentTimetable(Request $request)
    {
        // Check role and permission
        if (!auth()->user()->hasRole('Student') || 
            !auth()->user()->can('view-own-timetable')) {
            abort(403, 'Unauthorized action.');
        }
        
        $studentId = auth()->id();
        $semester_id = $request->input('semester_id');
        
        $query = ExamTimetable::query()
            ->leftJoin('units', 'exam_timetables.unit_id', '=', 'units.id')
            ->select('exam_timetables.*', 'units.name as unit_name')
            ->whereHas('unit.enrollments', function($q) use ($studentId) {
                $q->where('student_id', $studentId);
            });
            
        if ($semester_id) {
            $query->where('exam_timetables.semester_id', $semester_id);
        }
        
        $timetables = $query->get();
        $semesters = Semester::all();
        
        return Inertia::render('Student/Timetable', [
            'timetables' => $timetables,
            'semesters' => $semesters,
            'current_semester' => $semester_id,
            'can' => [
                'download' => auth()->user()->can('download-own-timetable'),
            ]
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
    
    // Download methods for different roles
    
    public function downloadLecturerTimetable(Request $request)
    {
        // Check role and permission
        if (!auth()->user()->hasRole('Lecturer') || 
            !auth()->user()->can('download-own-timetable')) {
            abort(403, 'Unauthorized action.');
        }
        
        // Download logic for lecturer timetable
        // TODO: Implement PDF generation or Excel export
        
        return response()->download($pathToFile, 'lecturer-timetable.pdf');
    }
    
    public function downloadStudentTimetable(Request $request)
    {
        $user = auth()->user();
        Log::info('Downloading Student Timetable', [
            'user_id' => $user->id,
            'roles' => $user->getRoleNames(),
            'permissions' => $user->getAllPermissions()->pluck('name'),
        ]);

        // Check role and permission
        if (!auth()->user()->hasRole('Student') || 
            !auth()->user()->can('download-own-timetable')) {
            abort(403, 'Unauthorized action.');
        }
        
        // Download logic for student timetable
        // TODO: Implement PDF generation or Excel export
        
        return response()->download($pathToFile, 'student-timetable.pdf');
    }
    
    public function downloadFacultyTimetable(Request $request)
    {
        // Check role and permission
        if (!auth()->user()->hasRole('Faculty Admin') || 
            !auth()->user()->can('download-faculty-timetable')) {
            abort(403, 'Unauthorized action.');
        }
        
        // Download logic for faculty timetable
        // TODO: Implement PDF generation or Excel export
        
        return response()->download($pathToFile, 'faculty-timetable.pdf');
    }
    
    public function downloadTimetable(Request $request)
    {
        $format = $request->input('format', 'pdf'); // Default to PDF
        $timetables = ExamTimetable::all(); // Fetch timetables (adjust query as needed)

        if ($format === 'pdf') {
            $pdf = Pdf::loadView('timetables.pdf', ['timetables' => $timetables]);
            return $pdf->download('timetable.pdf');
        }

        // ...existing code for other formats...
    }

    // Helper function to check for time overlap
    private function checkTimeOverlap($exam1, $exam2)
    {
        // Convert times to minutes for easier comparison
        $start1 = $this->timeToMinutes($exam1['start_time']);
        $end1 = $this->timeToMinutes($exam1['end_time']);
        $start2 = $this->timeToMinutes($exam2['start_time']);
        $end2 = $this->timeToMinutes($exam2['end_time']);
        
        // Check for overlap
        return ($start1 < $end2 && $start2 < $end1);
    }

    private function timeToMinutes($time)
    {
        list($hours, $minutes) = explode(':', $time);
        return ($hours * 60) + $minutes;
    }

    public function show($id)
    {
        $timetable = ExamTimetable::with(['unit', 'semester'])->findOrFail($id);

        return Inertia::render('ExamTimetable/View', [
            'timetable' => $timetable,
        ]);
    }
}