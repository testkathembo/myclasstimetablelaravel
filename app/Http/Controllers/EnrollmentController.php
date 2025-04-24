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
                $query->whereHas('unit', function ($q) use ($search) {
                    $q->where('name', 'like', "%$search%");
                });
            })
            ->paginate(10);

        return Inertia::render('Enrollments/Index', [
            'enrollments' => $enrollments,
        ]);
    }

    public function create()
    {
        $students = User::whereHas('roles', function ($query) {
            $query->where('name', 'Student');
        })->get();

        $units = Unit::all();
        $semesters = Semester::all();

        return Inertia::render('Enrollments/Create', [
            'students' => $students,
            'units' => $units,
            'semesters' => $semesters,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'student_id' => 'required|exists:users,id',
            'unit_id' => 'required|exists:units,id',
            'semester_id' => 'required|exists:semesters,id',
        ]);

        Enrollment::create($request->only('student_id', 'unit_id', 'semester_id'));

        return redirect()->route('enrollments.index')->with('success', 'Enrollment created successfully.');
    }

    public function edit(Enrollment $enrollment)
    {
        $students = User::whereHas('roles', function ($query) {
            $query->where('name', 'Student');
        })->get();

        $units = Unit::all();
        $semesters = Semester::all();

        return Inertia::render('Enrollments/Edit', [
            'enrollment' => $enrollment,
            'students' => $students,
            'units' => $units,
            'semesters' => $semesters,
        ]);
    }

    public function update(Request $request, Enrollment $enrollment)
    {
        $request->validate([
            'student_id' => 'required|exists:users,id',
            'unit_id' => 'required|exists:units,id',
            'semester_id' => 'required|exists:semesters,id',
        ]);

        $enrollment->update($request->only('student_id', 'unit_id', 'semester_id'));

        return redirect()->route('enrollments.index')->with('success', 'Enrollment updated successfully.');
    }

    public function destroy(Enrollment $enrollment)
    {
        $enrollment->delete();

        return redirect()->route('enrollments.index')->with('success', 'Enrollment deleted successfully.');
    }
}
