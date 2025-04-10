<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB; // ✅ Needed for raw queries
use App\Models\ExamTimetable;
use App\Models\Semester;
use App\Models\Enrollment;
use App\Models\TimeSlot;
use App\Models\Classroom;
use Inertia\Inertia; // Import the Inertia facade

class ExamTimetableController extends Controller
{
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

        // ✅ Get all semesters
        $semesters = Semester::all();

        // ✅ Updated: Get enrollments with unit info AND student count per unit
        $enrollments = Enrollment::select(
                DB::raw('MIN(enrollments.id) as id'), // Use MIN to get any valid id for that unit in semester
                'enrollments.unit_id',
                'units.name as unit_name',
                'enrollments.semester_id',
                DB::raw('COUNT(DISTINCT enrollments.student_id) as student_count')
            )
            ->join('units', 'units.id', '=', 'enrollments.unit_id')
            ->groupBy('enrollments.unit_id', 'units.name', 'enrollments.semester_id') // Ensure all selected columns are in GROUP BY
            ->get();

        // ✅ Get all time slots
        $timeSlots = TimeSlot::select('id', 'day', 'date', 'start_time', 'end_time')->get();

        // ✅ Get all classrooms (for filtering by capacity in frontend)
        $classrooms = Classroom::all();

        return Inertia::render('ExamTimetable/index', [
            'examTimetables' => $examTimetables,
            'perPage' => $perPage,
            'search' => $search,
            'semesters' => $semesters,
            'enrollments' => $enrollments,
            'timeSlots' => $timeSlots,
            'classrooms' => $classrooms, // ✅ Pass to frontend
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'enrollment_id' => 'required|exists:enrollments,id',
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

        ExamTimetable::create($validated); // ✅ Creates the timetable

        return redirect()->route('exam-timetables.index')->with('success', 'Exam timetable created successfully.');
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'enrollment_id' => 'required|exists:enrollments,id',
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

        $timetable = ExamTimetable::findOrFail($id);
        $timetable->update($validated); // ✅ Updates the timetable

        return redirect()->route('exam-timetables.index')->with('success', 'Exam timetable updated successfully.');
    }

    public function destroy($id)
    {
        $timetable = ExamTimetable::findOrFail($id);
        $timetable->delete(); // ✅ Deletes the timetable

        return redirect()->route('exam-timetables.index')->with('success', 'Exam timetable deleted successfully.');
    }
}
