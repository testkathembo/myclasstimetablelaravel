<?php
namespace App\Http\Controllers;

use App\Models\ClassModel;
use App\Models\Semester;
use App\Models\Program;
use Illuminate\Http\Request;

class ClassController extends Controller
{
    public function index()
    {
        $classes = ClassModel::with(['semester', 'program'])->paginate(10);
        $semesters = Semester::all();
        $programs = Program::all();

        return inertia('Classes/Index', [
            'classes' => $classes,
            'semesters' => $semesters,
            'programs' => $programs,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'semester_id' => 'required|exists:semesters,id',
            'program_id' => 'required|exists:programs,id',
        ]);

        ClassModel::create($validated);

        return redirect()->back()->with('success', 'Class created successfully!');
    }
}
