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

        $enrollments = Enrollment::with(['student', 'unit', 'semester'])
            ->when($search, function ($query, $search) {
                $query->whereHas('student', function ($q) use ($search) {
                    $q->where('first_name', 'like', "%$search%")
                      ->orWhere('last_name', 'like', "%$search%");
                })->orWhereHas('unit', function ($q) use ($search) {
                    $q->where('name', 'like', "%$search%");
                })->orWhereHas('semester', function ($q) use ($search) {
                    $q->where('name', 'like', "%$search%");
                });
            })
            ->paginate(10);

        $students = User::where('user_role', 'student')->get();
        $units = Unit::all();
        $semesters = Semester::all();

        return Inertia::render('Enrollments/Index', [
            'enrollments' => $enrollments,
            'students' => $students,
            'units' => $units,
            'semesters' => $semesters,
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
