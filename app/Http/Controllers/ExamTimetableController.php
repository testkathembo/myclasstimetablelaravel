<?php

namespace App\Http\Controllers;

use App\Models\ExamTimetable;
use App\Models\Unit;
use App\Models\Semester;
use App\Models\Program;
use App\Models\School;
use App\Models\User;
use App\Models\Enrollment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Barryvdh\DomPDF\Facade\Pdf;

class ExamTimetableController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
        $this->authorizeResource(ExamTimetable::class, 'examTimetable');
    }

    /**
     * Display a listing of the exam timetables.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Inertia\Response
     */
    public function index(Request $request)
    {
        $query = ExamTimetable::query()
            ->with(['unit', 'semester', 'program', 'school']);

        // Filter by semester
        if ($request->has('semester_id') && $request->input('semester_id')) {
            $query->where('semester_id', $request->input('semester_id'));
        } else {
            // Default to active semester
            $activeSemester = Semester::where('is_active', true)->first();
            if ($activeSemester) {
                $query->where('semester_id', $activeSemester->id);
            }
        }

        // Filter by program
        if ($request->has('program_id') && $request->input('program_id')) {
            $query->where('program_id', $request->input('program_id'));
        }

        // Filter by school
        if ($request->has('school_id') && $request->input('school_id')) {
            $query->where('school_id', $request->input('school_id'));
        }

        // Filter by date
        if ($request->has('date') && $request->input('date')) {
            $query->whereDate('date', $request->input('date'));
        }

        // Filter by day
        if ($request->has('day') && $request->input('day')) {
            $query->where('day', $request->input('day'));
        }

        // Filter by group
        if ($request->has('group') && $request->input('group')) {
            $query->where('group', $request->input('group'));
        }

        // Search functionality
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('venue', 'like', "%{$search}%")
                  ->orWhere('chief_invigilator', 'like', "%{$search}%")
                  ->orWhereHas('unit', function ($unitQuery) use ($search) {
                      $unitQuery->where('code', 'like', "%{$search}%")
                                ->orWhere('name', 'like', "%{$search}%");
                  });
            });
        }

        // Sorting
        $sortField = $request->input('sort_field', 'date');
        $sortDirection = $request->input('sort_direction', 'asc');
        $query->orderBy($sortField, $sortDirection);
        
        // Secondary sorting by start_time
        $query->orderBy('start_time', 'asc');

        // Pagination
        $perPage = $request->input('per_page', 10);
        $examTimetables = $query->paginate($perPage)->withQueryString();

        // Get data for filter dropdowns
        $semesters = Semester::select('id', 'name')->orderBy('name')->get();
        $programs = Program::select('id', 'code', 'name')->orderBy('name')->get();
        $schools = School::select('id', 'code', 'name')->orderBy('name')->get();
        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        
        // Get unique groups
        $groups = ExamTimetable::select('group')
            ->whereNotNull('group')
            ->distinct()
            ->pluck('group')
            ->sort()
            ->values();

        // Get active semester
        $activeSemester = Semester::where('is_active', true)->first();

        return Inertia::render('ExamTimetables/Index', [
            'examTimetables' => $examTimetables,
            'semesters' => $semesters,
            'programs' => $programs,
            'schools' => $schools,
            'days' => $days,
            'groups' => $groups,
            'activeSemester' => $activeSemester,
            'filters' => $request->only([
                'search', 'semester_id', 'program_id', 'school_id', 
                'date', 'day', 'group', 'sort_field', 'sort_direction', 'per_page'
            ]),
            'can' => [
                'create' => Gate::allows('create', ExamTimetable::class),
                'download' => Gate::allows('viewAny', ExamTimetable::class),
            ],
        ]);
    }

    /**
     * Show the form for creating a new exam timetable.
     *
     * @return \Inertia\Response
     */
    public function create()
    {
        $units = Unit::where('is_active', true)
            ->select('id', 'code', 'name', 'program_id', 'school_id')
            ->with(['program:id,name', 'school:id,name'])
            ->orderBy('code')
            ->get();
            
        $semesters = Semester::select('id', 'name')->orderBy('name')->get();
        
        // Get active semester
        $activeSemester = Semester::where('is_active', true)->first();
        
        // Get staff for chief invigilators
        $staff = User::whereIn('role', ['lecturer', 'admin'])
            ->select('id', 'code', 'name')
            ->orderBy('name')
            ->get();
            
        // Days of the week
        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

        return Inertia::render('ExamTimetables/Create', [
            'units' => $units,
            'semesters' => $semesters,
            'activeSemester' => $activeSemester,
            'staff' => $staff,
            'days' => $days,
        ]);
    }

    /**
     * Store a newly created exam timetable in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'unit_id' => 'required|exists:units,id',
            'semester_id' => 'required|exists:semesters,id',
            'day' => 'required|string|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
            'date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'venue' => 'required|string|max:255',
            'location' => 'nullable|string|max:255',
            'no' => 'required|integer|min:1',
            'chief_invigilator' => 'required|string|max:255',
            'group' => 'nullable|string|size:1|regex:/^[A-Z]$/',
        ]);

        // Get unit details to set program and school
        $unit = Unit::findOrFail($validated['unit_id']);
        $validated['program_id'] = $unit->program_id;
        $validated['school_id'] = $unit->school_id;

        // Check for time conflicts
        $conflicts = ExamTimetable::where('semester_id', $validated['semester_id'])
            ->where('date', $validated['date'])
            ->where(function ($query) use ($validated) {
                $query->whereBetween('start_time', [$validated['start_time'], $validated['end_time']])
                    ->orWhereBetween('end_time', [$validated['start_time'], $validated['end_time']])
                    ->orWhere(function ($q) use ($validated) {
                        $q->where('start_time', '<=', $validated['start_time'])
                          ->where('end_time', '>=', $validated['end_time']);
                    });
            });
            
        // If group is specified, only check conflicts within the same group
        if (!empty($validated['group'])) {
            $conflicts->where(function ($query) use ($validated) {
                $query->where('group', $validated['group'])
                      ->orWhereNull('group');
            });
        }
        
        // Check for venue conflicts
        $venueConflicts = (clone $conflicts)->where('venue', $validated['venue'])->exists();
        
        // Check for chief invigilator conflicts
        $invigilatorConflicts = (clone $conflicts)->where('chief_invigilator', $validated['chief_invigilator'])->exists();
        
        if ($venueConflicts) {
            return redirect()->back()
                ->withErrors(['venue' => 'The venue is already booked for an exam during this time slot.'])
                ->withInput();
        }
        
        if ($invigilatorConflicts) {
            return redirect()->back()
                ->withErrors(['chief_invigilator' => 'The chief invigilator is already assigned to another exam during this time slot.'])
                ->withInput();
        }

        ExamTimetable::create($validated);

        return redirect()->route('exam-timetables.index')
            ->with('success', 'Exam timetable created successfully.');
    }

    /**
     * Display the specified exam timetable.
     *
     * @param  \App\Models\ExamTimetable  $examTimetable
     * @return \Inertia\Response
     */
    public function show(ExamTimetable $examTimetable)
    {
        $examTimetable->load(['unit', 'semester', 'program', 'school']);
        
        // Get enrolled students if group is specified
        $enrolledStudents = collect();
        if ($examTimetable->group) {
            $enrolledStudents = Enrollment::where('unit_id', $examTimetable->unit_id)
                ->where('semester_id', $examTimetable->semester_id)
                ->where('group', $examTimetable->group)
                ->with('student:id,code,name,email')
                ->get();
        } else {
            // Get all enrolled students for this unit and semester
            $enrolledStudents = Enrollment::where('unit_id', $examTimetable->unit_id)
                ->where('semester_id', $examTimetable->semester_id)
                ->with('student:id,code,name,email')
                ->get();
        }

        return Inertia::render('ExamTimetables/Show', [
            'examTimetable' => $examTimetable,
            'enrolledStudents' => $enrolledStudents,
            'can' => [
                'update' => Gate::allows('update', $examTimetable),
                'delete' => Gate::allows('delete', $examTimetable),
            ],
        ]);
    }

    /**
     * Show the form for editing the specified exam timetable.
     *
     * @param  \App\Models\ExamTimetable  $examTimetable
     * @return \Inertia\Response
     */
    public function edit(ExamTimetable $examTimetable)
    {
        $examTimetable->load(['unit', 'semester']);
        
        $units = Unit::where('is_active', true)
            ->select('id', 'code', 'name', 'program_id', 'school_id')
            ->with(['program:id,name', 'school:id,name'])
            ->orderBy('code')
            ->get();
            
        $semesters = Semester::select('id', 'name')->orderBy('name')->get();
        
        // Get staff for chief invigilators
        $staff = User::whereIn('role', ['lecturer', 'admin'])
            ->select('id', 'code', 'name')
            ->orderBy('name')
            ->get();
            
        // Days of the week
        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        
        // Get groups for the program
        $groups = [];
        if ($examTimetable->program_id) {
            $groups = \App\Models\ProgramGroup::where('program_id', $examTimetable->program_id)
                ->where('is_active', true)
                ->pluck('group')
                ->toArray();
        }

        return Inertia::render('ExamTimetables/Edit', [
            'examTimetable' => $examTimetable,
            'units' => $units,
            'semesters' => $semesters,
            'staff' => $staff,
            'days' => $days,
            'groups' => $groups,
        ]);
    }

    /**
     * Update the specified exam timetable in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\ExamTimetable  $examTimetable
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, ExamTimetable $examTimetable)
    {
        $validated = $request->validate([
            'unit_id' => 'required|exists:units,id',
            'semester_id' => 'required|exists:semesters,id',
            'day' => 'required|string|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
            'date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'venue' => 'required|string|max:255',
            'location' => 'nullable|string|max:255',
            'no' => 'required|integer|min:1',
            'chief_invigilator' => 'required|string|max:255',
            'group' => 'nullable|string|size:1|regex:/^[A-Z]$/',
        ]);

        // Get unit details to set program and school
        $unit = Unit::findOrFail($validated['unit_id']);
        $validated['program_id'] = $unit->program_id;
        $validated['school_id'] = $unit->school_id;

        // Check for time conflicts (excluding this record)
        $conflicts = ExamTimetable::where('id', '!=', $examTimetable->id)
            ->where('semester_id', $validated['semester_id'])
            ->where('date', $validated['date'])
            ->where(function ($query) use ($validated) {
                $query->whereBetween('start_time', [$validated['start_time'], $validated['end_time']])
                    ->orWhereBetween('end_time', [$validated['start_time'], $validated['end_time']])
                    ->orWhere(function ($q) use ($validated) {
                        $q->where('start_time', '<=', $validated['start_time'])
                          ->where('end_time', '>=', $validated['end_time']);
                    });
            });
            
        // If group is specified, only check conflicts within the same group
        if (!empty($validated['group'])) {
            $conflicts->where(function ($query) use ($validated) {
                $query->where('group', $validated['group'])
                      ->orWhereNull('group');
            });
        }
        
        // Check for venue conflicts
        $venueConflicts = (clone $conflicts)->where('venue', $validated['venue'])->exists();
        
        // Check for chief invigilator conflicts
        $invigilatorConflicts = (clone $conflicts)->where('chief_invigilator', $validated['chief_invigilator'])->exists();
        
        if ($venueConflicts) {
            return redirect()->back()
                ->withErrors(['venue' => 'The venue is already booked for an exam during this time slot.'])
                ->withInput();
        }
        
        if ($invigilatorConflicts) {
            return redirect()->back()
                ->withErrors(['chief_invigilator' => 'The chief invigilator is already assigned to another exam during this time slot.'])
                ->withInput();
        }

        $examTimetable->update($validated);

        return redirect()->route('exam-timetables.index')
            ->with('success', 'Exam timetable updated successfully.');
    }

    /**
     * Remove the specified exam timetable from storage.
     *
     * @param  \App\Models\ExamTimetable  $examTimetable
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(ExamTimetable $examTimetable)
    {
        $examTimetable->delete();

        return redirect()->route('exam-timetables.index')
            ->with('success', 'Exam timetable deleted successfully.');
    }

    /**
     * Download exam timetable as PDF.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function downloadPdf(Request $request)
    {
        $this->authorize('viewAny', ExamTimetable::class);

        $query = ExamTimetable::with(['unit', 'semester', 'program', 'school']);

        // Filter by semester
        if ($request->has('semester_id') && $request->input('semester_id')) {
            $query->where('semester_id', $request->input('semester_id'));
        } else {
            // Default to active semester
            $activeSemester = Semester::where('is_active', true)->first();
            if ($activeSemester) {
                $query->where('semester_id', $activeSemester->id);
            }
        }

        // Filter by program
        if ($request->has('program_id') && $request->input('program_id')) {
            $query->where('program_id', $request->input('program_id'));
        }

        // Filter by school
        if ($request->has('school_id') && $request->input('school_id')) {
            $query->where('school_id', $request->input('school_id'));
        }

        // Filter by date
        if ($request->has('date') && $request->input('date')) {
            $query->whereDate('date', $request->input('date'));
        }

        // Filter by day
        if ($request->has('day') && $request->input('day')) {
            $query->where('day', $request->input('day'));
        }

        // Filter by group
        if ($request->has('group') && $request->input('group')) {
            $query->where('group', $request->input('group'));
        }

        // Order by date and time
        $query->orderBy('date')
              ->orderBy('start_time');

        $examTimetables = $query->get();

        // Get semester name for the title
        $semesterName = 'All Semesters';
        if ($request->has('semester_id') && $request->input('semester_id')) {
            $semester = Semester::find($request->input('semester_id'));
            if ($semester) {
                $semesterName = $semester->name;
            }
        } elseif ($activeSemester) {
            $semesterName = $activeSemester->name;
        }

        // Get program name for the title
        $programName = '';
        if ($request->has('program_id') && $request->input('program_id')) {
            $program = Program::find($request->input('program_id'));
            if ($program) {
                $programName = $program->name;
            }
        }

        // Get school name for the title
        $schoolName = '';
        if ($request->has('school_id') && $request->input('school_id')) {
            $school = School::find($request->input('school_id'));
            if ($school) {
                $schoolName = $school->name;
            }
        }

        // Generate PDF
        $pdf = PDF::loadView('pdfs.exam-timetable', [
            'examTimetables' => $examTimetables,
            'semesterName' => $semesterName,
            'programName' => $programName,
            'schoolName' => $schoolName,
            'date' => $request->input('date'),
            'day' => $request->input('day'),
            'group' => $request->input('group'),
            'generatedAt' => now()->format('Y-m-d H:i:s'),
        ]);

        // Set paper size to landscape for better readability
        $pdf->setPaper('a4', 'landscape');

        // Generate filename
        $filename = 'exam-timetable';
        if ($semesterName !== 'All Semesters') {
            $filename .= '-' . str_slug($semesterName);
        }
        if ($programName) {
            $filename .= '-' . str_slug($programName);
        }
        $filename .= '.pdf';

        return $pdf->download($filename);
    }

    /**
     * Display the exam timetable for the current student.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Inertia\Response
     */
    public function myExams(Request $request)
    {
        $user = auth()->user();
        
        // Ensure the user is a student
        if ($user->role !== 'student') {
            abort(403, 'Only students can access this page.');
        }

        // Get active semester or selected semester
        $semesterId = $request->input('semester_id');
        if (!$semesterId) {
            $activeSemester = Semester::where('is_active', true)->first();
            $semesterId = $activeSemester ? $activeSemester->id : null;
        }

        // Get the student's enrollments for the semester
        $enrollments = Enrollment::where('student_code', $user->code)
            ->where('semester_id', $semesterId)
            ->pluck('unit_id');

        // Get exam timetables for the enrolled units
        $examTimetables = ExamTimetable::whereIn('unit_id', $enrollments)
            ->where('semester_id', $semesterId)
            ->with(['unit', 'semester'])
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();

        // Get all semesters for the dropdown
        $semesters = Semester::select('id', 'name')->orderBy('name')->get();

        return Inertia::render('Student/Exams', [
            'examTimetables' => $examTimetables,
            'semesters' => $semesters,
            'selectedSemester' => $semesterId,
        ]);
    }

    /**
     * Display the exam timetable for the current lecturer.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Inertia\Response
     */
    public function lecturerExams(Request $request)
    {
        $user = auth()->user();
        
        // Ensure the user is a lecturer
        if ($user->role !== 'lecturer') {
            abort(403, 'Only lecturers can access this page.');
        }

        // Get active semester or selected semester
        $semesterId = $request->input('semester_id');
        if (!$semesterId) {
            $activeSemester = Semester::where('is_active', true)->first();
            $semesterId = $activeSemester ? $activeSemester->id : null;
        }

        // Get units where the lecturer is assigned
        $units = Enrollment::where('lecturer_code', $user->code)
            ->where('semester_id', $semesterId)
            ->distinct('unit_id')
            ->pluck('unit_id');

        // Get exam timetables for the lecturer's units
        $examTimetables = ExamTimetable::whereIn('unit_id', $units)
            ->where('semester_id', $semesterId)
            ->with(['unit', 'semester'])
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();

        // Get exams where the lecturer is the chief invigilator
        $invigilatorExams = ExamTimetable::where('chief_invigilator', $user->code)
            ->where('semester_id', $semesterId)
            ->with(['unit', 'semester'])
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();

        // Get all semesters for the dropdown
        $semesters = Semester::select('id', 'name')->orderBy('name')->get();

        return Inertia::render('Lecturer/Exams', [
            'examTimetables' => $examTimetables,
            'invigilatorExams' => $invigilatorExams,
            'semesters' => $semesters,
            'selectedSemester' => $semesterId,
        ]);
    }
}