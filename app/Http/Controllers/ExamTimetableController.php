<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\ExamTimetable;
use App\Models\Semester;
use App\Models\Enrollment;
use App\Models\TimeSlot;
use App\Models\Classroom;
use Inertia\Inertia;

class ExamTimetableController extends Controller
{
    public function index(Request $request)
{
    $perPage = $request->input('per_page', 10);
    $search = $request->input('search', '');

    // ✅ Join units and fetch unit_name alongside examTimetables
    $examTimetables = ExamTimetable::query()
        ->leftJoin('units', 'exam_timetables.unit_id', '=', 'units.id')
        ->select('exam_timetables.*', 'units.name as unit_name') // ✅ Add unit_name to the selection
        ->when($search, function ($query, $search) {
            return $query->where('exam_timetables.day', 'like', "%{$search}%")
                         ->orWhere('exam_timetables.venue', 'like', "%{$search}%");
        })
        ->paginate($perPage);

    $semesters = Semester::all();

    $enrollments = Enrollment::selectRaw("
            MIN(enrollments.id) as id,
            enrollments.unit_id,
            units.name as unit_name,
            enrollments.semester_id,
            COUNT(DISTINCT enrollments.student_id) as student_count,
            MAX(enrollments.lecturer_id) as lecturer_id,
            CONCAT(MAX(users.first_name), ' ', MAX(users.last_name)) as lecturer_name
        ")
        ->join('units', 'units.id', '=', 'enrollments.unit_id')
        ->leftJoin('users', 'users.id', '=', 'enrollments.lecturer_id')
        ->groupBy('enrollments.unit_id', 'units.name', 'enrollments.semester_id')
        ->get();

    $timeSlots = TimeSlot::select('id', 'day', 'date', 'start_time', 'end_time')->get();

    $classrooms = Classroom::all();

    return Inertia::render('ExamTimetable/index', [
        'examTimetables' => $examTimetables,
        'perPage' => $perPage,
        'search' => $search,
        'semesters' => $semesters,
        'enrollments' => $enrollments,
        'timeSlots' => $timeSlots,
        'classrooms' => $classrooms,
    ]);
}


public function store(Request $request)
{
    $validated = $request->validate([
        'enrollment_id' => 'required|exists:enrollments,id',
        'semester_id' => 'required|exists:semesters,id',
        'day' => 'required|string',
        'date' => 'required|date',
        'start_time' => 'required|date_format:H:i',
        'end_time' => 'required|date_format:H:i|after:start_time',            
        'venue' => 'required|string',
        'location' => 'nullable|string',
        'no' => 'required|integer',
        'chief_invigilator' => 'required|string',
    ]);

    // Get the unit_id from the enrollment
    $enrollment = Enrollment::find($validated['enrollment_id']);
    if (!$enrollment) {
        return redirect()->back()->withErrors(['enrollment_id' => 'Invalid enrollment selected.']);
    }

    // Make sure semester_id is explicitly set
    $semesterId = $validated['semester_id'];
    if (!$semesterId) {
        // If semester_id is not in validated data, try to get it from the enrollment
        $semesterId = $enrollment->semester_id;
    }

    if (!$semesterId) {
        return redirect()->back()->withErrors(['semester_id' => 'Semester ID is required.']);
    }

    // Create the data to be saved with explicit semester_id
    $data = [
        'semester_id' => $semesterId,
        'unit_id' => $enrollment->unit_id,
        'day' => $validated['day'],
        'date' => $validated['date'],
        'start_time' => $validated['start_time'],
        'end_time' => $validated['end_time'],         
        'venue' => $validated['venue'],
        'no' => $validated['no'],
        'chief_invigilator' => $validated['chief_invigilator'],
    ];

    // Add location if it exists in the request
    if (isset($validated['location'])) {
        $data['location'] = $validated['location'];
    }

    // For debugging, you can dump and die to see the data before creating
    // dd($data);

    $examTimetable = new ExamTimetable($data);
    $examTimetable->semester_id = $semesterId; // Set it directly on the model as well
    $examTimetable->save();

    return redirect()->back()->with('success', 'Exam timetable created successfully.');
}

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'enrollment_id' => 'required|exists:enrollments,id',
            'semester_id' => 'required|exists:semesters,id',
            'day' => 'required|string',
            'date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',           
            'venue' => 'required|string',
            'location' => 'nullable|string',
            'no' => 'required|integer',
            'chief_invigilator' => 'required|string',
        ]);

        // Get the unit_id from the enrollment
        $enrollment = Enrollment::find($validated['enrollment_id']);
        
        // Create the data to be updated
        $data = [
            'semester_id' => $validated['semester_id'],
            'unit_id' => $enrollment->unit_id,
            'day' => $validated['day'],
            'date' => $validated['date'],
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
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
        $timetable = ExamTimetable::findOrFail($id);
        $timetable->delete();

        return redirect()->route('exam-timetables.index')->with('success', 'Exam timetable deleted successfully.');
    }
}