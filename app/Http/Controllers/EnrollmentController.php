<?php

namespace App\Http\Controllers;

use App\Models\Enrollment;
use App\Models\Unit;
use App\Models\Group;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EnrollmentController extends Controller
{
    public function index()
    {
        $enrollments = Enrollment::with('student', 'unit', 'group')->paginate(10);
        $students = User::where('user_role', 'student')->get();
        $units = Unit::all();
        $groups = Group::withCount('students')->get();

        return Inertia::render('Enrollments/Index', [
            'enrollments' => $enrollments,
            'students' => $students,
            'units' => $units,
            'groups' => $groups,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'student_id' => 'required|exists:users,id',
            'unit_id' => 'required|exists:units,id',
            'group_id' => 'required|exists:groups,id',
        ]);

        $group = Group::find($request->group_id);

        if ($group->students()->count() >= 35) {
            return back()->withErrors(['group_id' => 'This group has reached the maximum capacity of 35 students.']);
        }

        Enrollment::create($request->only('student_id', 'unit_id', 'group_id'));

        return redirect()->route('enrollments.index')->with('success', 'Student enrolled successfully.');
    }

    public function destroy(Enrollment $enrollment)
    {
        $enrollment->delete();

        return redirect()->route('enrollments.index')->with('success', 'Enrollment removed successfully.');
    }
}
