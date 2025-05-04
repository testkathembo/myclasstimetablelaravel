<?php

namespace App\Http\Controllers;

use App\Models\ClassTimetable;
use App\Models\Semester;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ClassTimetableController extends Controller
{
    public function index()
    {
        $classTimetables = ClassTimetable::with(['semester', 'unit', 'lecturer'])->paginate(10);
        return Inertia::render('ClassTimetables/Index', [
            'classTimetables' => $classTimetables,
        ]);
    }

    public function create()
    {
        $semesters = Semester::all();
        $units = Unit::all();
        $lecturers = User::role('Lecturer')->get();

        return Inertia::render('ClassTimetables/Create', [
            'semesters' => $semesters,
            'units' => $units,
            'lecturers' => $lecturers,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'semester_id' => 'required|exists:semesters,id',
            'unit_id' => 'required|exists:units,id',
            'day' => 'required|string',
            'date' => 'required|date',
            'start_time' => 'required',
            'end_time' => 'required',
            'venue' => 'required|string',
            'location' => 'nullable|string',
            'status' => 'required|string',
            'lecturer_id' => 'nullable|exists:users,id',
        ]);

        ClassTimetable::create($request->all());

        return redirect()->route('classtimetables.index')->with('success', 'Class timetable created successfully.');
    }

    public function edit(ClassTimetable $classtimetable)
    {
        $semesters = Semester::all();
        $units = Unit::all();
        $lecturers = User::role('Lecturer')->get();

        return Inertia::render('ClassTimetables/Edit', [
            'classTimetable' => $classtimetable,
            'semesters' => $semesters,
            'units' => $units,
            'lecturers' => $lecturers,
        ]);
    }

    public function update(Request $request, ClassTimetable $classtimetable)
    {
        $request->validate([
            'semester_id' => 'required|exists:semesters,id',
            'unit_id' => 'required|exists:units,id',
            'day' => 'required|string',
            'date' => 'required|date',
            'start_time' => 'required',
            'end_time' => 'required',
            'venue' => 'required|string',
            'location' => 'nullable|string',
            'status' => 'required|string',
            'lecturer_id' => 'nullable|exists:users,id',
        ]);

        $classtimetable->update($request->all());

        return redirect()->route('classtimetables.index')->with('success', 'Class timetable updated successfully.');
    }

    public function destroy(ClassTimetable $classtimetable)
    {
        $classtimetable->delete();

        return redirect()->route('classtimetables.index')->with('success', 'Class timetable deleted successfully.');
    }
}
