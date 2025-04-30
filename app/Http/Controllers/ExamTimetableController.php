<?php

namespace App\Http\Controllers;

use App\Models\ExamTimetable;
use App\Models\Unit;
use App\Models\User;
use App\Models\Semester;
use App\Models\Enrollment;
use App\Models\TimeSlot;
use App\Models\Examroom;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Barryvdh\DomPDF\Facade\Pdf;

class ExamTimetableController extends Controller
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
        \Log::info('Accessing /examtimetable', [
            'user_id' => $user->id,
            'roles' => $user->getRoleNames(),
            'permissions' => $user->getAllPermissions()->pluck('name'),
        ]);

        if (!$user->can('manage-examtimetables')) {
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
                'units.code as unit_code',
                'semesters.name as semester_name',
                'exam_timetables.semester_id'
            )
            ->when($request->has('search') && $request->search !== '', function ($query) use ($request) {
                $search = $request->search;
                $query->where('day', 'like', "%{$search}%")
                      ->orWhere('date', 'like', "%{$search}%");
            })
            ->orderBy('date')
            ->paginate($request->get('per_page', 10));

        // Fetch lecturers with both ID and code
        $lecturers = User::role('Lecturer')
            ->select('id', 'code', DB::raw("CONCAT(first_name, ' ', last_name) as name"))
            ->get();

        // Get all necessary data for the form
        $semesters = Semester::all();
        $examrooms = Examroom::all();
        $timeSlots = TimeSlot::all();
        $units = Unit::select('id', 'code', 'name', 'semester_id')->get();
        
        // Get enrollments with lecturer information
        $enrollments = Enrollment::query()
            ->leftJoin('users as lecturers', 'enrollments.lecturer_code', '=', 'lecturers.code')
            ->leftJoin('units', 'enrollments.unit_id', '=', 'units.id')
            ->select(
                'enrollments.*',
                'units.code as unit_code',
                'units.name as unit_name',
                DB::raw("CONCAT(lecturers.first_name, ' ', lecturers.last_name) as lecturer_name")
            )
            ->get();

        // Log enrollments for debugging
        Log::info('Enrollments data for exam timetable', [
            'enrollments_count' => $enrollments->count(),
            'sample_enrollments' => $enrollments->take(5)->toArray(),
        ]);

        // Log lecturers for debugging
        Log::info('Lecturers data for exam timetable', [
            'lecturers_count' => $lecturers->count(),
            'sample_lecturers' => $lecturers->take(5)->toArray(),
        ]);

        return Inertia::render('ExamTimetable/index', [
            'examTimetables' => $examTimetables,
            'lecturers' => $lecturers,
            'perPage' => $perPage,
            'search' => $search,
            'semesters' => $semesters,
            'enrollments' => $enrollments,
            'examrooms' => $examrooms,
            'timeSlots' => $timeSlots,
            'units' => $units,
            'can' => [
                'create' => $user->can('create-examtimetables'),
                'edit' => $user->can('update-examtimetables'),
                'delete' => $user->can('delete-examtimetables'),
                'process' => $user->can('process-examtimetables'),
                'solve_conflicts' => $user->can('solve-exam-conflicts'),
                'download' => $user->can('download-examtimetables'),
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
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        // Check if user has permission to create examtimetables
        if (!auth()->user()->can('create-examtimetables')) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'semester_id' => 'required|exists:semesters,id',
            'unit_id' => 'required|exists:units,id',
            'day' => 'required|string',
            'date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'venue' => 'required|string',
            'location' => 'nullable|string',
            'no' => 'required|integer',
            'chief_invigilator' => 'required|string',
        ]);
        
        // Get the examroom capacity
        $examroom = Examroom::where('name', $validated['venue'])->first();
        
        if (!$examroom) {
            return redirect()->back()->with('error', 'Examroom not found.');
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
        
        // Check if adding this exam would exceed examroom capacity
        if ($totalStudents + $validated['no'] > $examroom->capacity) {
            return redirect()->back()->with('error', 
                "Cannot schedule this exam. The examroom {$validated['venue']} has a capacity of {$examroom->capacity}, " .
                "but there would be " . ($totalStudents + $validated['no']) . " students scheduled at this time " .
                "(exceeding capacity by " . (($totalStudents + $validated['no']) - $examroom->capacity) . " students)."
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

        return redirect()->route('examtimetable.index', $request->only(['page', 'search', 'per_page']))
            ->with('success', 'Exam timetable created successfully.');
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\ExamTimetable  $examTimetable
     * @return \Illuminate\Http\Response
     */
    public function show(ExamTimetable $examTimetable)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\ExamTimetable  $examTimetable
     * @return \Illuminate\Http\Response
     */
    public function edit(ExamTimetable $examTimetable)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\ExamTimetable  $examTimetable
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $user = auth()->user();

        // Log user roles and permissions
        \Log::info('ExamTimetable update attempt', [
            'user_id' => $user->id,
            'roles' => $user->getRoleNames(),
            'permissions' => $user->getAllPermissions()->pluck('name'),
            'exam_id' => $id,
        ]);

        if (!$user->can('update-examtimetables')) {
            abort(403, 'Unauthorized action.');
        }

        // Find the exam timetable by ID
        $examTimetable = ExamTimetable::findOrFail($id);

        $validated = $request->validate([
            'semester_id' => 'required|exists:semesters,id',
            'unit_id' => 'required|exists:units,id',
            'day' => 'required|string',
            'date' => 'required|date',
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'venue' => 'required|string',
            'location' => 'nullable|string',
            'no' => 'required|integer',
            'chief_invigilator' => 'required|string',
        ]);
        
        // Reformat start_time and end_time if necessary
        $validated['start_time'] = date('H:i', strtotime($validated['start_time']));
        $validated['end_time'] = date('H:i', strtotime($validated['end_time']));
        
        // Get the examroom capacity
        $examroom = Examroom::where('name', $validated['venue'])->first();
        
        if (!$examroom) {
            return redirect()->back()->with('error', 'Examroom not found.');
        }
        
        // Check for time conflicts in the same venue, excluding the current exam
        $conflictingExams = ExamTimetable::where('id', '!=', $examTimetable->id)
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
        
        // Check if updating this exam would exceed examroom capacity
        if ($totalStudents + $validated['no'] > $examroom->capacity) {
            return redirect()->back()->with('error', 
                "Cannot schedule this exam. The examroom {$validated['venue']} has a capacity of {$examroom->capacity}, " .
                "but there would be " . ($totalStudents + $validated['no']) . " students scheduled at this time " .
                "(exceeding capacity by " . (($totalStudents + $validated['no']) - $examroom->capacity) . " students)."
            )->withInput();
        }
        
        // Check for unit conflicts (same unit scheduled at the same time), excluding the current exam
        $unitConflicts = ExamTimetable::where('id', '!=', $examTimetable->id)
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
        $examTimetable->update($validated);

        return redirect()->route('examtimetable.index', $request->only(['page', 'search', 'per_page']))
            ->with('success', 'Exam timetable updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\ExamTimetable  $examTimetable
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $user = auth()->user();

        // Log user roles and permissions for debugging
        \Log::info('ExamTimetable delete attempt', [
            'user_id' => $user->id,
            'roles' => $user->getRoleNames(),
            'permissions' => $user->getAllPermissions()->pluck('name'),
            'exam_id' => $id,
        ]);

        // Check if the user has the required permission
        if (!$user->can('delete-examtimetables')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $examTimetable = ExamTimetable::findOrFail($id);
            $examTimetable->delete();

            return redirect()->route('examtimetable.index')
                ->with('success', 'Exam timetable deleted successfully.');
        } catch (\Exception $e) {
            \Log::error('Error deleting exam timetable', [
                'error' => $e->getMessage(),
                'exam_timetable_id' => $id,
            ]);

            return response()->json(['error' => 'Failed to delete exam timetable.'], 500);
        }
    }
    
    /**
     * Handle AJAX delete requests
     * 
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function ajaxDestroy($id)
    {
        $user = auth()->user();

        // Check if the user has the required permission
        if (!$user->can('delete-examtimetables')) {
            return response()->json(['error' => 'Unauthorized action.'], 403);
        }

        try {
            $examTimetable = ExamTimetable::findOrFail($id);
            $examTimetable->delete();

            return response()->json(['success' => true, 'message' => 'Exam timetable deleted successfully.']);
        } catch (\Exception $e) {
            \Log::error('Error deleting exam timetable', [
                'error' => $e->getMessage(),
                'exam_timetable_id' => $id,
            ]);

            return response()->json(['error' => 'Failed to delete exam timetable.'], 500);
        }
    }
    
    /**
     * Get lecturer information for a unit
     * 
     * @param int $unitId
     * @param int $semesterId
     * @return \Illuminate\Http\Response
     */
    public function getLecturerForUnit($unitId, $semesterId)
    {
        // Find enrollments for this unit in the selected semester
        $unitEnrollments = Enrollment::where('unit_id', $unitId)
            ->where('semester_id', $semesterId)
            ->whereNotNull('lecturer_code')
            ->get();
            
        // Extract unique lecturer codes
        $lecturerCodes = $unitEnrollments->pluck('lecturer_code')->unique()->filter()->values();
        
        // Find lecturer details
        $lecturers = User::whereIn('code', $lecturerCodes)
            ->select('id', 'code', DB::raw("CONCAT(first_name, ' ', last_name) as name"))
            ->get();
            
        return response()->json([
            'success' => true,
            'lecturers' => $lecturers,
            'default_lecturer' => $lecturers->first(),
        ]);
    }
    
    /**
     * Process timetable (for Exam Office)
     * 
     * @return \Illuminate\Http\Response
     */
    public function process()
    {
        // Check permission
        if (!auth()->user()->can('process-examtimetables')) {
            abort(403, 'Unauthorized action.');
        }

        // Add your processing logic here
        return redirect()->back()->with('success', 'Timetable processing completed.');
    }
    
    /**
     * Solve conflicts (for Exam Office)
     * 
     * @return \Illuminate\Http\Response
     */
    public function solveConflicts()
    {
        // Check permission
        if (!auth()->user()->can('solve-exam-conflicts')) {
            abort(403, 'Unauthorized action.');
        }
        
        // Conflict resolution logic here...
        // This is where you would identify and resolve scheduling conflicts
        
        return redirect()->back()->with('success', 'Conflicts resolved successfully.');
    }
    
    /**
     * Download exam timetable as PDF
     * 
     * @return \Illuminate\Http\Response
     */
    public function downloadPDF()
    {
        // Check if user has permission to download examtimetables
        if (!auth()->user()->can('download-examtimetables')) {
            abort(403, 'Unauthorized action.');
        }

        // Get exam timetables with related data
        $examtimetables = ExamTimetable::with(['unit', 'semester'])
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();

        \Log::info('Generating PDF for exam timetables', ['count' => $examtimetables->count()]);

        // Generate the PDF
        $pdf = Pdf::loadView('examtimetables.pdf', compact('examtimetables'));
        
        return $pdf->download('exam_timetable.pdf');
    }
}
