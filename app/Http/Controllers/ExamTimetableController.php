<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Timetable;
use App\Models\Semester;
use App\Models\Enrollment;
use App\Models\TimeSlot;
use App\Models\Classroom;
use App\Models\User;

class ExamTimetableController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search', '');
        $perPage = $request->input('per_page', 10);

        $query = Timetable::query()
            ->with(['enrollment', 'classroom', 'lecturer'])
            ->when($search, function ($q) use ($search) {
                $q->whereHas('enrollment', function ($q) use ($search) {
                    $q->where('unit_name', 'like', "%$search%"); // Assuming enrollments table has a unit_name column
                });
            });

        $examTimetables = $query->paginate($perPage);

        // Fetch supporting dropdown data
        $semesters = Semester::all();
        $enrollments = Enrollment::with('semester')->get(); // Fetch enrollments with semester relationship
        $timeSlots = TimeSlot::all();
        $classrooms = Classroom::all();
        $lecturers = User::all(); // Fetch all users without filtering by roles

        return inertia('ExamTimetable/index', [
            'examTimetables' => $examTimetables,
            'perPage' => $perPage,
            'search' => $search,
            'semesters' => $semesters,
            'enrollments' => $enrollments,
            'timeSlots' => $timeSlots,
            'classrooms' => $classrooms,
            'lecturers' => $lecturers,
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
