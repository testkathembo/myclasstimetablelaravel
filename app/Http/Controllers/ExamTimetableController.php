<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ExamTimetable;
use App\Models\Semester;
use App\Models\Enrollment;
use App\Models\TimeSlot;
use App\Models\Classroom;
use App\Models\User;
use Inertia\Inertia; // Import the Inertia facade

class ExamTimetableController extends Controller
{
    // This is a simplified example of what your controller method might look like
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 10);
        $search = $request->input('search', '');
        
        $examTimetables = ExamTimetable::query()
            ->when($search, function ($query, $search) {
                return $query->where('day', 'like', "%{$search}%")
                    ->orWhere('venue', 'like', "%{$search}%");
            })
            ->paginate($perPage);
        
        // Get all semesters
        $semesters = Semester::all();
        
        // Get enrollments with unit information
        $enrollments = Enrollment::select('enrollments.id', 'units.name as unit_name', 'enrollments.semester_id')
            ->join('units', 'units.id', '=', 'enrollments.unit_id')
            ->get();
        
        // Get time slots
        $timeSlots = TimeSlot::all();
        
        return Inertia::render('ExamTimetable/index', [
            'examTimetables' => $examTimetables,
            'perPage' => $perPage,
            'search' => $search,
            'semesters' => $semesters,
            'enrollments' => $enrollments,
            'timeSlots' => $timeSlots,
        ]);
    }


    public function store(Request $request)
    {
        $validated = $request->validate([
            'enrollment_id' => 'required|exists:enrollments,id', // Replace unit_id with enrollment_id
            'classroom_id' => 'required|exists:classrooms,id',
            'lecturer_id' => 'required|exists:users,id',
            'semester_id' => 'required|exists:semesters,id',
            'day' => 'required|string',
            'date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'group' => 'nullable|string',
            'venue' => 'nullable|string',
            'no' => 'nullable|integer',
            'chief_invigilator' => 'nullable|string',
        ]);

        Timetable::create($validated);

        return redirect()->route('exam-timetables.index')->with('success', 'Exam timetable created successfully.');
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'enrollment_id' => 'required|exists:enrollments,id', // Replace unit_id with enrollment_id
            'classroom_id' => 'required|exists:classrooms,id',
            'lecturer_id' => 'required|exists:users,id',
            'semester_id' => 'required|exists:semesters,id',
            'day' => 'required|string',
            'date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'group' => 'nullable|string',
            'venue' => 'nullable|string',
            'no' => 'nullable|integer',
            'chief_invigilator' => 'nullable|string',
        ]);

        $timetable = Timetable::findOrFail($id);
        $timetable->update($validated);

        return redirect()->route('exam-timetables.index')->with('success', 'Exam timetable updated successfully.');
    }

    public function destroy($id)
    {
        $timetable = Timetable::findOrFail($id);
        $timetable->delete();

        return redirect()->route('exam-timetables.index')->with('success', 'Exam timetable deleted successfully.');
    }
}
