<?php

namespace App\Http\Controllers;

use App\Models\ClassTimetable;
use App\Models\Unit;
use App\Models\Semester;
use App\Models\Program;
use App\Models\School;
use App\Models\User;
use App\Models\Enrollment;
use App\Models\Classroom;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Barryvdh\DomPDF\Facade\Pdf;

class ClassTimetableController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
        $this->authorizeResource(ClassTimetable::class, 'classTimetable');
    }

    /**
     * Display a listing of the class timetables.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Inertia\Response
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', ClassTimetable::class); // Ensure the user has permission to view class timetables

        $query = ClassTimetable::query()
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
                  ->orWhere('lecturer', 'like', "%{$search}%")
                  ->orWhereHas('unit', function ($unitQuery) use ($search) {
                      $unitQuery->where('code', 'like', "%{$search}%")
                                ->orWhere('name', 'like', "%{$search}%");
                  });
            });
        }

        // Sorting
        $sortField = $request->input('sort_field', 'day');
        $sortDirection = $request->input('sort_direction', 'asc');
        
        if ($sortField === 'day') {
            $query->orderByRaw("FIELD(day, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday')");
        } else {
            $query->orderBy($sortField, $sortDirection);
        }
        
        // Secondary sorting by start_time
        $query->orderBy('start_time', 'asc');

        // Pagination
        $perPage = $request->input('per_page', 10);
        $classTimetables = $query->paginate($perPage)->withQueryString();

        // Get data for filter dropdowns
        $semesters = Semester::select('id', 'name')->orderBy('name')->get();
        $programs = Program::select('id', 'code', 'name')->orderBy('name')->get();
        $schools = School::select('id', 'code', 'name')->orderBy('name')->get();
        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        
        // Get unique groups
        $groups = ClassTimetable::select('group')
            ->whereNotNull('group')
            ->distinct()
            ->pluck('group')
            ->sort()
            ->values();

        // Get active semester
        $activeSemester = Semester::where('is_active', true)->first();

        return Inertia::render('ClassTimetables/Index', [
            'classTimetables' => $classTimetables,
            'semesters' => $semesters,
            'programs' => $programs,
            'schools' => $schools,
            'days' => $days,
            'groups' => $groups,
            'activeSemester' => $activeSemester,
            'filters' => $request->only([
                'search', 'semester_id', 'program_id', 'school_id', 
                'day', 'group', 'sort_field', 'sort_direction', 'per_page'
            ]),
            'can' => [
                'create' => Gate::allows('create', ClassTimetable::class),
                'download' => Gate::allows('viewAny', ClassTimetable::class),
            ],
        ]);
    }

    /**
     * Show the form for creating a new class timetable.
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
        
        // Get lecturers
        $lecturers = User::where('role', 'lecturer')
            ->select('id', 'code', 'name')
            ->orderBy('name')
            ->get();
            
        // Get classrooms
        $classrooms = Classroom::select('id', 'name', 'capacity', 'location')
            ->orderBy('name')
            ->get();
            
        // Days of the week
        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

        return Inertia::render('ClassTimetables/Create', [
            'units' => $units,
            'semesters' => $semesters,
            'activeSemester' => $activeSemester,
            'lecturers' => $lecturers,
            'classrooms' => $classrooms,
            'days' => $days,
        ]);
    }

    /**
     * Store a newly created class timetable in storage.
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
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'venue' => 'required|string|max:255',
            'location' => 'nullable|string|max:255',
            'no' => 'nullable|integer|min:0',
            'lecturer' => 'required|string|max:255',
            'group' => 'nullable|string|size:1|regex:/^[A-Z]$/',
        ]);

        // Get unit details to set program and school
        $unit = Unit::findOrFail($validated['unit_id']);
        $validated['program_id'] = $unit->program_id;
        $validated['school_id'] = $unit->school_id;

        // Check for time conflicts
        $conflicts = ClassTimetable::where('semester_id', $validated['semester_id'])
            ->where('day', $validated['day'])
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
        
        // Check for lecturer conflicts
        $lecturerConflicts = (clone $conflicts)->where('lecturer', $validated['lecturer'])->exists();
        
        if ($venueConflicts) {
            return redirect()->back()
                ->withErrors(['venue' => 'The venue is already booked during this time slot.'])
                ->withInput();
        }
        
        if ($lecturerConflicts) {
            return redirect()->back()
                ->withErrors(['lecturer' => 'The lecturer is already assigned to another class during this time slot.'])
                ->withInput();
        }

        ClassTimetable::create($validated);

        return redirect()->route('class-timetables.index')
            ->with('success', 'Class timetable created successfully.');
    }

    /**
     * Display the specified class timetable.
     *
     * @param  \App\Models\ClassTimetable  $classTimetable
     * @return \Inertia\Response
     */
    public function show(ClassTimetable $classTimetable)
    {
        $classTimetable->load(['unit', 'semester', 'program', 'school']);
        
        // Get enrolled students if group is specified
        $enrolledStudents = collect();
        if ($classTimetable->group) {
            $enrolledStudents = Enrollment::where('unit_id', $classTimetable->unit_id)
                ->where('semester_id', $classTimetable->semester_id)
                ->where('group', $classTimetable->group)
                ->with('student:id,code,name,email')
                ->get();
        }

        return Inertia::render('ClassTimetables/Show', [
            'classTimetable' => $classTimetable,
            'enrolledStudents' => $enrolledStudents,
            'can' => [
                'update' => Gate::allows('update', $classTimetable),
                'delete' => Gate::allows('delete', $classTimetable),
            ],
        ]);
    }

    /**
     * Show the form for editing the specified class timetable.
     *
     * @param  \App\Models\ClassTimetable  $classTimetable
     * @return \Inertia\Response
     */
    public function edit(ClassTimetable $classTimetable)
    {
        $classTimetable->load(['unit', 'semester']);
        
        $units = Unit::where('is_active', true)
            ->select('id', 'code', 'name', 'program_id', 'school_id')
            ->with(['program:id,name', 'school:id,name'])
            ->orderBy('code')
            ->get();
            
        $semesters = Semester::select('id', 'name')->orderBy('name')->get();
        
        // Get lecturers
        $lecturers = User::where('role', 'lecturer')
            ->select('id', 'code', 'name')
            ->orderBy('name')
            ->get();
            
        // Get classrooms
        $classrooms = Classroom::select('id', 'name', 'capacity', 'location')
            ->orderBy('name')
            ->get();
            
        // Days of the week
        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        
        // Get groups for the program
        $groups = [];
        if ($classTimetable->program_id) {
            $groups = \App\Models\ProgramGroup::where('program_id', $classTimetable->program_id)
                ->where('is_active', true)
                ->pluck('group')
                ->toArray();
        }

        return Inertia::render('ClassTimetables/Edit', [
            'classTimetable' => $classTimetable,
            'units' => $units,
            'semesters' => $semesters,
            'lecturers' => $lecturers,
            'classrooms' => $classrooms,
            'days' => $days,
            'groups' => $groups,
        ]);
    }

    /**
     * Update the specified class timetable in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\ClassTimetable  $classTimetable
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, ClassTimetable $classTimetable)
    {
        $validated = $request->validate([
            'unit_id' => 'required|exists:units,id',
            'semester_id' => 'required|exists:semesters,id',
            'day' => 'required|string|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'venue' => 'required|string|max:255',
            'location' => 'nullable|string|max:255',
            'no' => 'nullable|integer|min:0',
            'lecturer' => 'required|string|max:255',
            'group' => 'nullable|string|size:1|regex:/^[A-Z]$/',
        ]);

        // Get unit details to set program and school
        $unit = Unit::findOrFail($validated['unit_id']);
        $validated['program_id'] = $unit->program_id;
        $validated['school_id'] = $unit->school_id;

        // Check for time conflicts (excluding this record)
        $conflicts = ClassTimetable::where('id', '!=', $classTimetable->id)
            ->where('semester_id', $validated['semester_id'])
            ->where('day', $validated['day'])
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
        
        // Check for lecturer conflicts
        $lecturerConflicts = (clone $conflicts)->where('lecturer', $validated['lecturer'])->exists();
        
        if ($venueConflicts) {
            return redirect()->back()
                ->withErrors(['venue' => 'The venue is already booked during this time slot.'])
                ->withInput();
        }
        
        if ($lecturerConflicts) {
            return redirect()->back()
                ->withErrors(['lecturer' => 'The lecturer is already assigned to another class during this time slot.'])
                ->withInput();
        }

        $classTimetable->update($validated);

        return redirect()->route('class-timetables.index')
            ->with('success', 'Class timetable updated successfully.');
    }

    /**
     * Remove the specified class timetable from storage.
     *
     * @param  \App\Models\ClassTimetable  $classTimetable
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(ClassTimetable $classTimetable)
    {
        $classTimetable->delete();

        return redirect()->route('class-timetables.index')
            ->with('success', 'Class timetable deleted successfully.');
    }

    /**
     * Download class timetable as PDF.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function downloadPdf(Request $request)
    {
        $this->authorize('viewAny', ClassTimetable::class);

        $query = ClassTimetable::with(['unit', 'semester', 'program', 'school']);

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

        // Filter by day
        if ($request->has('day') && $request->input('day')) {
            $query->where('day', $request->input('day'));
        }

        // Filter by group
        if ($request->has('group') && $request->input('group')) {
            $query->where('group', $request->input('group'));
        }

        // Order by day and time
        $query->orderByRaw("FIELD(day, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday')")
              ->orderBy('start_time');

        $classTimetables = $query->get();

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
        $pdf = PDF::loadView('pdfs.class-timetable', [
            'classTimetables' => $classTimetables,
            'semesterName' => $semesterName,
            'programName' => $programName,
            'schoolName' => $schoolName,
            'day' => $request->input('day'),
            'group' => $request->input('group'),
            'generatedAt' => now()->format('Y-m-d H:i:s'),
        ]);

        // Set paper size to landscape for better readability
        $pdf->setPaper('a4', 'landscape');

        // Generate filename
        $filename = 'class-timetable';
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
     * Display the class timetable for the current student.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Inertia\Response
     */
    public function myTimetable(Request $request)
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

        // Get class timetables for the enrolled units
        $classTimetables = ClassTimetable::whereIn('unit_id', $enrollments)
            ->where('semester_id', $semesterId)
            ->with(['unit', 'semester'])
            ->orderByRaw("FIELD(day, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday')")
            ->orderBy('start_time')
            ->get();

        // Get all semesters for the dropdown
        $semesters = Semester::select('id', 'name')->orderBy('name')->get();

        return Inertia::render('Student/Timetable', [
            'classTimetables' => $classTimetables,
            'semesters' => $semesters,
            'selectedSemester' => $semesterId,
        ]);
    }

    /**
     * Display the class timetable for the current lecturer.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Inertia\Response
     */
    public function lecturerTimetable(Request $request)
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

        // Get class timetables for the lecturer
        $classTimetables = ClassTimetable::where('lecturer', $user->code)
            ->where('semester_id', $semesterId)
            ->with(['unit', 'semester'])
            ->orderByRaw("FIELD(day, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday')")
            ->orderBy('start_time')
            ->get();

        // Get all semesters for the dropdown
        $semesters = Semester::select('id', 'name')->orderBy('name')->get();

        return Inertia::render('Lecturer/Timetable', [
            'classTimetables' => $classTimetables,
            'semesters' => $semesters,
            'selectedSemester' => $semesterId,
        ]);
    }
}