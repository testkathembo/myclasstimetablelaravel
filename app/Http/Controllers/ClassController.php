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

    public function update(Request $request, ClassModel $class)
    {
        \Log::info('Updating class:', $request->all());

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'semester_id' => 'required|exists:semesters,id',
            'program_id' => 'required|exists:programs,id',
        ]);

        $class->update($validated);

        return redirect()->back()->with('success', 'Class updated successfully!');
    }

    public function destroy(ClassModel $class)
    {
        \Log::info('Deleting class:', ['id' => $class->id]);

        $class->delete();

        return redirect()->back()->with('success', 'Class deleted successfully!');
    }
}
