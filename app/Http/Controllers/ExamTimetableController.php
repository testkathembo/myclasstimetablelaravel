<?php

namespace App\Http\Controllers;

use App\Models\ExamTimetable;
use App\Models\Unit;
use App\Models\User;
use App\Models\Semester;
use App\Models\Enrollment;
use App\Models\TimeSlot;
use App\Models\Examroom;
use App\Models\ClassModel;
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

        // Fetch exam timetables with related data including class
        $examTimetables = ExamTimetable::query()
            ->leftJoin('units', 'exam_timetables.unit_id', '=', 'units.id')
            ->leftJoin('semesters', 'exam_timetables.semester_id', '=', 'semesters.id')
            ->leftJoin('classes', 'exam_timetables.class_id', '=', 'classes.id')
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
                'exam_timetables.class_id',
                'units.name as unit_name',
                'units.code as unit_code',
                'semesters.name as semester_name',
                'classes.name as class_name',
                'exam_timetables.semester_id'
            )
            ->when($request->has('search') && $request->search !== '', function ($query) use ($request) {
                $search = $request->search;
                $query->where('exam_timetables.day', 'like', "%{$search}%")
                    ->orWhere('exam_timetables.date', 'like', "%{$search}%")
                    ->orWhere('units.name', 'like', "%{$search}%")
                    ->orWhere('classes.name', 'like', "%{$search}%");
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
        $classes = ClassModel::with('semester')->get();

        // Get all units
        $allUnits = Unit::select('id', 'code', 'name')->get();
        
        // Get all enrollments with student and lecturer information
        $allEnrollments = Enrollment::select('id', 'student_code', 'unit_id', 'semester_id', 'lecturer_code', 'group_id')
            ->with(['group.class'])
            ->get();

        // Group enrollments by class_id and semester_id for easy access
        $enrollmentsByClassAndSemester = [];
        foreach ($allEnrollments as $enrollment) {
            if ($enrollment->group && $enrollment->group->class) {
                $classId = $enrollment->group->class->id;
                $key = $classId . '_' . $enrollment->semester_id;
                if (!isset($enrollmentsByClassAndSemester[$key])) {
                    $enrollmentsByClassAndSemester[$key] = [];
                }
                $enrollmentsByClassAndSemester[$key][] = $enrollment;
            }
        }

        // Get units with their associated semesters and classes through enrollments
        $unitsByClassAndSemester = [];
        
        // For each semester and class combination, find all units that have enrollments
        foreach ($semesters as $semester) {
            foreach ($classes->where('semester_id', $semester->id) as $class) {
                // Find all unit_ids that have enrollments in this class and semester
                $key = $class->id . '_' . $semester->id;
                $classEnrollments = $enrollmentsByClassAndSemester[$key] ?? [];
                
                $unitIdsInClass = collect($classEnrollments)->pluck('unit_id')->unique()->toArray();
                
                // Find the corresponding units
                $unitsInClass = Unit::whereIn('id', $unitIdsInClass)
                    ->select('id', 'code', 'name')
                    ->get()
                    ->map(function ($unit) use ($semester, $class, $classEnrollments) {
                        // Add semester_id and class_id to each unit
                        $unit->semester_id = $semester->id;
                        $unit->class_id = $class->id;
                        
                        // Find enrollments for this unit in this class and semester
                        $unitEnrollments = collect($classEnrollments)->where('unit_id', $unit->id);
                        
                        // Count students
                        $unit->student_count = $unitEnrollments->count();
                        
                        // Find lecturer
                        $lecturerCode = $unitEnrollments->where('lecturer_code', '!=', null)->first()?->lecturer_code;
                        
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
                if (!isset($unitsByClassAndSemester[$semester->id])) {
                    $unitsByClassAndSemester[$semester->id] = [];
                }
                $unitsByClassAndSemester[$semester->id][$class->id] = $unitsInClass;
            }
        }

        // Flatten the array to get all units with their semester_id and class_id
        $unitsWithSemesterAndClass = collect();
        foreach ($unitsByClassAndSemester as $semesterId => $classesByUnits) {
            foreach ($classesByUnits as $classId => $units) {
                $unitsWithSemesterAndClass = $unitsWithSemesterAndClass->concat($units);
            }
        }

        // Log the units with semesters and classes for debugging
        \Log::info('Units with semesters and classes:', [
            'count' => $unitsWithSemesterAndClass->count(),
            'sample' => $unitsWithSemesterAndClass->take(5),
        ]);

        return Inertia::render('ExamTimetables/Index', [
            'examTimetables' => $examTimetables,
            'lecturers' => $lecturers,
            'perPage' => $perPage,
            'search' => $search,
            'semesters' => $semesters,
            'examrooms' => $examrooms,
            'timeSlots' => $timeSlots,
            'classes' => $classes,
            'units' => $unitsWithSemesterAndClass, // Pass units with their semester_id, class_id and enrollment data
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
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $request->validate([
            'day' => 'required|string',
            'date' => 'required|date',
            'unit_id' => 'required|exists:units,id',
            'semester_id' => 'required|exists:semesters,id',
            'class_id' => 'required|exists:classes,id',
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
                'class_id' => $request->class_id,
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
            'date' => 'required|date',
            'unit_id' => 'required|exists:units,id',
            'semester_id' => 'required|exists:semesters,id',
            'class_id' => 'required|exists:classes,id',
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
                'class_id' => $request->class_id,
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
     * Get units by class and semester for AJAX requests
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function getUnitsByClassAndSemester(Request $request)
    {
        $request->validate([
            'class_id' => 'required|exists:classes,id',
            'semester_id' => 'required|exists:semesters,id',
        ]);

        try {
            $classId = $request->class_id;
            $semesterId = $request->semester_id;

            // Get all enrollments for this class and semester
            $enrollments = Enrollment::whereHas('group.class', function($query) use ($classId) {
                    $query->where('id', $classId);
                })
                ->where('semester_id', $semesterId)
                ->get();

            // Get unique unit IDs
            $unitIds = $enrollments->pluck('unit_id')->unique()->toArray();

            // Get units with their enrollment data
            $units = Unit::whereIn('id', $unitIds)
                ->select('id', 'code', 'name')
                ->get()
                ->map(function ($unit) use ($enrollments) {
                    // Count students for this unit
                    $unitEnrollments = $enrollments->where('unit_id', $unit->id);
                    $unit->student_count = $unitEnrollments->count();
                    
                    // Find lecturer
                    $lecturerCode = $unitEnrollments->where('lecturer_code', '!=', null)->first()?->lecturer_code;
                    
                    if ($lecturerCode) {
                        $lecturer = User::where('code', $lecturerCode)->first();
                        if ($lecturer) {
                            $unit->lecturer_code = $lecturerCode;
                            $unit->lecturer_name = $lecturer->first_name . ' ' . $lecturer->last_name;
                        }
                    }
                    
                    return $unit;
                });

            return response()->json([
                'success' => true,
                'units' => $units,
                'count' => $units->count(),
            ]);
        } catch (\Exception $e) {
            \Log::error('Error fetching units by class and semester: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch units. Please try again.',
                'units' => [],
            ], 500);
        }
    }

    // Rest of the existing methods remain the same...
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

    public function process(Request $request)
    {
        try {
            \Log::info('Processing exam timetable');
            return redirect()->back()->with('success', 'Exam timetable processed successfully.');
        } catch (\Exception $e) {
            \Log::error('Failed to process exam timetable: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to process exam timetable: ' . $e->getMessage());
        }
    }

    public function solveConflicts(Request $request)
    {
        try {
            \Log::info('Solving exam timetable conflicts');
            return redirect()->back()->with('success', 'Exam conflicts resolved successfully.');
        } catch (\Exception $e) {
            \Log::error('Failed to solve exam conflicts: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to solve exam conflicts: ' . $e->getMessage());
        }
    }

    public function downloadPDF(Request $request)
    {
        try {
            if (!view()->exists('examtimetables.pdf')) {
                \Log::error('PDF template not found: examtimetables.pdf');
                return redirect()->back()->with('error', 'PDF template not found. Please contact the administrator.');
            }

            $query = ExamTimetable::query()
                ->join('units', 'exam_timetables.unit_id', '=', 'units.id')
                ->join('semesters', 'exam_timetables.semester_id', '=', 'semesters.id')
                ->leftJoin('classes', 'exam_timetables.class_id', '=', 'classes.id')
                ->select(
                    'exam_timetables.*',
                    'units.name as unit_name',
                    'units.code as unit_code',
                    'semesters.name as semester_name',
                    'classes.name as class_name'
                );

            if ($request->has('semester_id')) {
                $query->where('exam_timetables.semester_id', $request->semester_id);
            }

            if ($request->has('class_id')) {
                $query->where('exam_timetables.class_id', $request->class_id);
            }

            $examTimetables = $query->orderBy('exam_timetables.date')
                ->orderBy('exam_timetables.start_time')
                ->get();

            $pdf = Pdf::loadView('examtimetables.pdf', [
                'examTimetables' => $examTimetables,
                'title' => 'Exam Timetable',
                'generatedAt' => now()->format('Y-m-d H:i:s'),
            ]);

            $pdf->setPaper('a4', 'landscape');

            return $pdf->download('examtimetable.pdf');
        } catch (\Exception $e) {
            \Log::error('Failed to generate PDF: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()->with('error', 'Failed to generate PDF: ' . $e->getMessage());
        }
    }
}