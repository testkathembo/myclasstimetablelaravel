<?php

namespace App\Http\Controllers;

use App\Models\Enrollment;
use App\Models\Unit;
use App\Models\Semester;
use App\Models\Program;
use App\Models\School;
use App\Models\User;
use App\Models\Group;
use App\Models\ClassModel;
use App\Services\EnrollmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class EnrollmentController extends Controller
{
    protected $enrollmentService;

    /**
     * Create a new controller instance.
     *
     * @param  \App\Services\EnrollmentService  $enrollmentService
     * @return void
     */
    public function __construct(EnrollmentService $enrollmentService)
    {
        $this->middleware('auth');
        $this->authorizeResource(Enrollment::class, 'enrollment');
        $this->enrollmentService = $enrollmentService;
    }

    /**
     * Display a listing of the enrollments.
     *
     * @return \Inertia\Response
     */
    public function index()
    {
        $enrollments = Enrollment::with(['group.class', 'student'])->paginate(10);
        $groups = Group::with('class')->get(); // Fetch groups with their associated classes
        $classes = ClassModel::all(); // Fetch all classes

        // Fetch students based on Spatie roles
        $students = User::role('Student')->get();

        return inertia('Enrollments/Index', [
            'enrollments' => $enrollments,
            'groups' => $groups,
            'classes' => $classes,
            'students' => $students,
        ]);
    }

    /**
     * Store a newly created enrollment in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'group_id' => 'required|exists:groups,id',
            'student_number' => 'required|string',
        ]);

        $student = User::where('student_number', $validated['student_number'])->first();

        if (!$student) {
            return redirect()->back()->withErrors(['student_number' => 'Student not found.']);
        }

        $group = Group::findOrFail($validated['group_id']);
        $currentEnrollments = Enrollment::where('group_id', $group->id)->count();

        if ($currentEnrollments >= $group->capacity) {
            return redirect()->back()->withErrors(['group_id' => 'This group is already full.']);
        }

        Enrollment::create([
            'group_id' => $validated['group_id'],
            'student_id' => $student->id,
        ]);

        return redirect()->back()->with('success', 'Student enrolled successfully!');
    }

    /**
     * Remove the specified enrollment from storage.
     *
     * @param  \App\Models\Enrollment  $enrollment
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(Enrollment $enrollment)
    {
        $enrollment->delete();

        return redirect()->back()->with('success', 'Enrollment removed successfully!');
    }
}