<?php

namespace App\Http\Controllers;

use App\Models\Enrollment;
use App\Models\Unit;
use App\Models\User;
use App\Models\Semester;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EnrollmentController extends Controller
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

    // ✅ Updated: Get enrollments with student count AND include unique enrollment.id
    $enrollments = Enrollment::select(
            DB::raw('MIN(enrollments.id) as id'), // Use MIN to get any valid id for that unit in semester
            'enrollments.unit_id',
            'units.name as unit_name',
            'enrollments.semester_id',
            DB::raw('COUNT(DISTINCT enrollments.student_id) as student_count')
        )
        ->join('units', 'units.id', '=', 'enrollments.unit_id')
        ->groupBy('enrollments.unit_id', 'units.name', 'enrollments.semester_id')
        ->get();

    // ✅ Get time slots
    $timeSlots = TimeSlot::select('id', 'day', 'date', 'start_time', 'end_time')->get();

    // ✅ Get classrooms
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
        $request->validate([
            'student_id' => 'required|exists:users,id',
            'semester_id' => 'required|exists:semesters,id',
            'unit_ids' => 'required|array', // Validate multiple units
            'unit_ids.*' => 'exists:units,id', // Ensure each unit_id exists
        ]);

        foreach ($request->unit_ids as $unit_id) {
            Enrollment::create([
                'student_id' => $request->student_id,
                'unit_id' => $unit_id,
                'semester_id' => $request->semester_id, // Save semester_id
            ]);
        }

        return redirect()->route('enrollments.index')->with('success', 'Student enrolled in selected units successfully.');
    }

    public function destroy(Enrollment $enrollment)
    {
        $enrollment->delete();

        return redirect()->route('enrollments.index')->with('success', 'Enrollment removed successfully.');
    }
}
