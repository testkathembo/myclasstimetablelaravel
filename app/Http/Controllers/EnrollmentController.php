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
        $search = $request->input('search');

        $enrollments = Enrollment::with(['student', 'unit', 'semester', 'lecturer'])
            ->when($search, function ($query, $search) {
                $query->whereHas('unit', function ($q) use ($search) {
                    $q->where('name', 'like', "%$search%")
                      ->orWhere('code', 'like', "%$search%"); // Search by unit name or code
                })->orWhereHas('lecturer', function ($q) use ($search) {
                    $q->where('first_name', 'like', "%$search%")
                      ->orWhere('last_name', 'like', "%$search%"); // Search by lecturer name
                });
            })
            ->paginate(10);

        $students = User::where('user_role', 'student')->get();
        $units = Unit::all();
        $semesters = Semester::all();
        $lecturers = User::where('user_role', 'lecturer')->get();

        $lecturerUnitAssignments = Enrollment::with('unit', 'lecturer')
            ->select('unit_id', 'lecturer_id')
            ->whereNotNull('lecturer_id')
            ->groupBy('unit_id', 'lecturer_id')
            ->get();

        return Inertia::render('Enrollments/Index', [
            'enrollments' => $enrollments,
            'students' => $students,
            'students' => $students,
            'units' => $units,
            'semesters' => $semesters,
            'lecturers' => $lecturers, // Pass lecturers to the view
            'lecturerUnitAssignments' => $lecturerUnitAssignments, // Pass lecturer assignments to the view
            'search' => $search,
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

    public function assignLecturers(Request $request)
    {
        $request->validate([
            'unit_id' => 'required|exists:units,id', // Validate enrollment ID
            'lecturer_id' => 'required|exists:users,id', // Validate lecturer ID
        ]);

        
        // update all enrollments with the same unit_id
        Enrollment::where('unit_id', $request->unit_id)->update([
            'lecturer_id' => $request->lecturer_id, // Assign the lecturer
        ]);        

        return redirect()->route('enrollments.index')->with('success', 'Lecturer assigned to enrollment successfully.');
    }

   
public function lecturerUnits($lecturerId)
{
    $lecturer = User::findOrFail($lecturerId);

    $assignedUnits = Enrollment::with(['unit', 'semester']) // Include semester relationship
        ->where('lecturer_id', $lecturerId)
        ->select('unit_id', 'semester_id') // Include semester_id
        ->groupBy('unit_id', 'semester_id')
        ->get()
        ->map(function ($enrollment) {
            return [
                'unit_name' => $enrollment->unit->name,
                'unit_code' => $enrollment->unit->code, // Assuming `code` is a column in the units table
                'semester_name' => $enrollment->semester->name, // Assuming `name` is a column in the semesters table
            ];
        });

    return response()->json([
        'units' => $assignedUnits,
        'lecturer' => $lecturer,
    ]);
}
    
public function unassignLecturer($unitId)
{
    Enrollment::where('unit_id', $unitId)->update(['lecturer_id' => null]);

    return redirect()->route('enrollments.index')->with('success', 'Lecturer assignment removed.');
}
}
