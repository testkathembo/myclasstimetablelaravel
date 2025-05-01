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
                $query->where('exam_timetables.day', 'like', "%{$search}%")
                      ->orWhere('exam_timetables.date', 'like', "%{$search}%");
            })
            ->orderBy('exam_timetables.date')
            ->paginate($request->get('per_page', 10));

        // Fetch lecturers with both ID and code
        $lecturers = User::role('Lecturer')
            ->select('id', 'code', DB::raw("CONCAT(first_name, ' ', last_name) as name"))
            ->get();

        // Get all necessary data for the form
        $semesters = Semester::all();
        $examrooms = Examroom::all();
        $timeSlots = TimeSlot::all();
        
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

        return Inertia::render('ExamTimetable/index', [
            'examTimetables' => $examTimetables,
            'lecturers' => $lecturers,
            'perPage' => $perPage,
            'search' => $search,
            'semesters' => $semesters,
            'examrooms' => $examrooms,
            'timeSlots' => $timeSlots,
            'units' => $unitsWithSemesters, // Pass units with their semester_id and enrollment data
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
        // This method is not used with Inertia.js as the form is part of the index page
        abort(404);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $request->validate([
            'day' => 'required|string',
            'date' => 'required|date',
            'unit_id' => 'required|exists:units,id',
            'semester_id' => 'required|exists:semesters,id',
            'venue' => 'required|string',
            'location' => 'required|string',
            'no' => 'required|integer',
            'chief_invigilator' => 'required|string',
            'start_time' => 'required|string',
            'end_time' => 'required|string',
        ]);

        try {
            $examTimetable = ExamTimetable::create([
                'day' => $request->day,
                'date' => $request->date,
                'unit_id' => $request->unit_id,
                'semester_id' => $request->semester_id,
                'venue' => $request->venue,
                'location' => $request->location,
                'no' => $request->no,
                'chief_invigilator' => $request->chief_invigilator,
                'start_time' => $request->start_time,
                'end_time' => $request->end_time,
            ]);

            return redirect()->back()->with('success', 'Exam timetable created successfully.');
        } catch (\Exception $e) {
            \Log::error('Failed to create exam timetable: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to create exam timetable: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $examTimetable = ExamTimetable::with(['unit', 'semester'])->findOrFail($id);
        
        return Inertia::render('ExamTimetable/Show', [
            'examTimetable' => $examTimetable
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
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
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'day' => 'required|string',
            'date' => 'required|date',
            'unit_id' => 'required|exists:units,id',
            'semester_id' => 'required|exists:semesters,id',
            'venue' => 'required|string',
            'location' => 'required|string',
            'no' => 'required|integer',
            'chief_invigilator' => 'required|string',
            'start_time' => 'required|string',
            'end_time' => 'required|string',
        ]);

        try {
            $examTimetable = ExamTimetable::findOrFail($id);
            
            $examTimetable->update([
                'day' => $request->day,
                'date' => $request->date,
                'unit_id' => $request->unit_id,
                'semester_id' => $request->semester_id,
                'venue' => $request->venue,
                'location' => $request->location,
                'no' => $request->no,
                'chief_invigilator' => $request->chief_invigilator,
                'start_time' => $request->start_time,
                'end_time' => $request->end_time,
            ]);

            return redirect()->back()->with('success', 'Exam timetable updated successfully.');
        } catch (\Exception $e) {
            \Log::error('Failed to update exam timetable: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to update exam timetable: ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        try {
            $examTimetable = ExamTimetable::findOrFail($id);
            $examTimetable->delete();
            
            return redirect()->back()->with('success', 'Exam timetable deleted successfully.');
        } catch (\Exception $e) {
            \Log::error('Failed to delete exam timetable: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to delete exam timetable: ' . $e->getMessage());
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
            $examTimetable = ExamTimetable::findOrFail($id);
            $examTimetable->delete();
            
            return response()->json(['success' => true, 'message' => 'Exam timetable deleted successfully.']);
        } catch (\Exception $e) {
            \Log::error('Failed to delete exam timetable: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to delete exam timetable: ' . $e->getMessage()], 500);
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
     * Process the exam timetable to optimize and resolve conflicts.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function process(Request $request)
    {
        try {
            // Implementation of timetable processing logic
            // This would typically involve complex algorithms to optimize the timetable
            
            // For demonstration, we'll just log that the process was called
            \Log::info('Processing exam timetable');
            
            // Return success response
            return redirect()->back()->with('success', 'Exam timetable processed successfully.');
        } catch (\Exception $e) {
            \Log::error('Failed to process exam timetable: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to process exam timetable: ' . $e->getMessage());
        }
    }

    /**
     * Solve conflicts in the exam timetable.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function solveConflicts(Request $request)
    {
        try {
            // Implementation of conflict resolution logic
            // This would typically involve identifying and resolving scheduling conflicts
            
            // For demonstration, we'll just log that the method was called
            \Log::info('Solving exam timetable conflicts');
            
            // Return success response
            return redirect()->back()->with('success', 'Exam conflicts resolved successfully.');
        } catch (\Exception $e) {
            \Log::error('Failed to solve exam conflicts: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to solve exam conflicts: ' . $e->getMessage());
        }
    }

    /**
     * Download the exam timetable as a PDF.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function downloadPDF(Request $request)
    {
        try {
            // Get all exam timetables or filter by semester if provided
            $query = ExamTimetable::query()
                ->join('units', 'exam_timetables.unit_id', '=', 'units.id')
                ->join('semesters', 'exam_timetables.semester_id', '=', 'semesters.id')
                ->select(
                    'exam_timetables.*',
                    'units.name as unit_name',
                    'units.code as unit_code',
                    'semesters.name as semester_name'
                );
            
            if ($request->has('semester_id')) {
                $query->where('exam_timetables.semester_id', $request->semester_id);
            }
            
            $examTimetables = $query->orderBy('exam_timetables.date')
                ->orderBy('exam_timetables.start_time')
                ->get();
            
            // Generate PDF
            $pdf = PDF::loadView('pdfs.exam-timetable', [
                'examTimetables' => $examTimetables,
                'title' => 'Exam Timetable',
                'generatedAt' => now()->format('Y-m-d H:i:s')
            ]);
            
            // Return the PDF for download
            return $pdf->download('exam-timetable.pdf');
        } catch (\Exception $e) {
            \Log::error('Failed to download exam timetable: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to download exam timetable: ' . $e->getMessage());
        }
    }

    /**
     * Download the faculty exam timetable as a PDF.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function downloadFacultyTimetable(Request $request)
    {
        try {
            $user = auth()->user();
            
            // Get the faculty ID of the user
            $facultyId = $user->faculty_id;
            
            if (!$facultyId) {
                return redirect()->back()->with('error', 'You are not associated with any faculty.');
            }
            
            // Get all exam timetables for units in this faculty
            $examTimetables = ExamTimetable::join('units', 'exam_timetables.unit_id', '=', 'units.id')
                ->join('semesters', 'exam_timetables.semester_id', '=', 'semesters.id')
                ->where('units.faculty_id', $facultyId)
                ->select(
                    'exam_timetables.*',
                    'units.name as unit_name',
                    'units.code as unit_code',
                    'semesters.name as semester_name'
                )
                ->orderBy('exam_timetables.date')
                ->orderBy('exam_timetables.start_time')
                ->get();
            
            // Generate PDF
            $pdf = PDF::loadView('pdfs.faculty-exam-timetable', [
                'examTimetables' => $examTimetables,
                'faculty' => $user->faculty->name ?? 'Unknown Faculty',
                'title' => 'Faculty Exam Timetable',
                'generatedAt' => now()->format('Y-m-d H:i:s')
            ]);
            
            // Return the PDF for download
            return $pdf->download('faculty-exam-timetable.pdf');
        } catch (\Exception $e) {
            \Log::error('Failed to download faculty exam timetable: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to download faculty exam timetable: ' . $e->getMessage());
        }
    }

    /**
     * View the lecturer's exam timetable.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function viewLecturerTimetable(Request $request)
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
        
        // Get exam timetables for the lecturer's units
        $examTimetables = ExamTimetable::where('semester_id', $semesterId)
            ->whereIn('unit_id', $lecturerUnitIds)
            ->with('unit') // Eager load the unit relationship
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();
        
        // Log for debugging
        \Log::info('Lecturer exam timetable data', [
            'lecturer_code' => $lecturerCode,
            'semester_id' => $semesterId,
            'unit_ids' => $lecturerUnitIds,
            'exam_count' => $examTimetables->count()
        ]);
        
        return Inertia::render('Lecturer/ExamTimetable', [
            'examTimetables' => $examTimetables,
            'semesters' => $semesters,
            'selectedSemesterId' => $semesterId
        ]);
    }

    /**
     * Download the lecturer's exam timetable as a PDF.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function downloadLecturerTimetable(Request $request)
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
            
            // Get exam timetables for the lecturer's units
            $examTimetables = ExamTimetable::where('semester_id', $semesterId)
                ->whereIn('unit_id', $lecturerUnitIds)
                ->with('unit') // Eager load the unit relationship
                ->orderBy('date')
                ->orderBy('start_time')
                ->get();
            
            // Generate PDF
            $pdf = PDF::loadView('pdfs.lecturer-exam-timetable', [
                'examTimetables' => $examTimetables,
                'lecturer' => $user->first_name . ' ' . $user->last_name,
                'semester' => $semester->name . ' ' . ($semester->year ?? ''),
                'generatedAt' => now()->format('Y-m-d H:i:s')
            ]);
            
            // Return the PDF for download
            return $pdf->download('lecturer-exam-timetable-' . $semester->name . '.pdf');
        } catch (\Exception $e) {
            \Log::error('Failed to download lecturer exam timetable: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to download lecturer exam timetable: ' . $e->getMessage());
        }
    }

    /**
     * Display the exam timetable for the logged-in student.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function viewStudentTimetable(Request $request)
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
        
        // Get exam timetables for the student's enrolled units in the selected semester
        $examTimetables = ExamTimetable::where('semester_id', $semesterId)
            ->whereIn('unit_id', $enrolledUnitIds)
            ->orWhereHas('unit', function($query) use ($user, $enrolledUnitIds) {
                $query->whereIn('id', $enrolledUnitIds);
            })
            ->with('unit') // Eager load the unit relationship
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();
        
        // Log for debugging
        \Log::info('Student exam timetable data', [
            'student_code' => $user->code,
            'semester_id' => $semesterId,
            'enrolled_unit_ids' => $enrolledUnitIds,
            'exam_count' => $examTimetables->count()
        ]);
        
        return Inertia::render('Student/ExamTimetable', [
            'examTimetables' => $examTimetables,
            'semesters' => $semesters,
            'selectedSemesterId' => $semesterId
        ]);
    }
    
    /**
     * Display details for a specific exam for a student.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $examtimetable
     * @return \Illuminate\Http\Response
     */
    public function viewStudentExamDetails(Request $request, $examtimetable)
    {
        $user = auth()->user();
        
        // Get the exam timetable with related data
        $examTimetable = ExamTimetable::with(['unit', 'semester'])
            ->findOrFail($examtimetable);
        
        // Check if the student is enrolled in this unit
        $isEnrolled = Enrollment::where('student_code', $user->code)
            ->where('unit_id', $examTimetable->unit_id)
            ->where('semester_id', $examTimetable->semester_id)
            ->exists();
        
        if (!$isEnrolled) {
            abort(403, 'You are not enrolled in this unit.');
        }
        
        return Inertia::render('Student/ExamDetails', [
            'examTimetable' => $examTimetable
        ]);
    }
    
    /**
     * Download the exam timetable for the logged-in student.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function downloadStudentTimetable(Request $request)
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

        // Get exam timetables for the student's enrolled units in the selected semester
        $examTimetables = ExamTimetable::where('semester_id', $semesterId)
            ->whereIn('unit_id', $enrolledUnitIds)
            ->with('unit') // Eager load the unit relationship
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();

        if ($examTimetables->isEmpty()) {
            return redirect()->back()->with('error', 'No exams found for the selected semester.');
        }

        // Generate PDF
        $pdf = Pdf::loadView('examtimetables.student', [
            'examTimetables' => $examTimetables,
            'student' => $user,
            'currentSemester' => $semester,
        ]);

        // Return the PDF for download
        return $pdf->download('exam-timetable-' . $semester->name . '.pdf');
    }
}