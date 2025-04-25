<?php

namespace App\Http\Controllers;

use App\Models\Semester;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Timetable;

class SemesterController extends Controller
{
    public function index()
    {
        $semesters = Semester::all();

        return Inertia::render('Semesters/index', [
            'semesters' => $semesters,
        ]);
    }

    public function create()
    {
        return Inertia::render('Semesters/Create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
           
        ]);

        Semester::create($request->only('name'));

        return redirect()->route('semesters.index')->with('success', 'Semester created successfully.');
    }

    public function edit(Semester $semester)
    {
        return Inertia::render('Semesters/Edit', [
            'semester' => $semester,
        ]);
    }

    public function update(Request $request, Semester $semester)
    {
        $request->validate([
            'name' => 'required|string|max:255',            
        ]);

        $semester->update($request->only('name', 'group_id'));

        return redirect()->route('semesters.index')->with('success', 'Semester updated successfully.');
    }

    public function destroy(Semester $semester)
    {
        $semester->delete();

        return redirect()->route('semesters.index')->with('success', 'Semester deleted successfully.');
    }

    public function viewTimetable(Request $request)
    {
        $semesterId = $request->input('semester_id', null);
        $search = $request->input('search', '');
        $perPage = $request->input('per_page', 10);

        $query = Timetable::query()
            ->with(['unit', 'classroom', 'lecturer'])
            ->when($semesterId, function ($q) use ($semesterId) {
                $q->where('semester_id', $semesterId);
            })
            ->when($search, function ($q) use ($search) {
                $q->whereHas('unit', function ($q) use ($search) {
                    $q->where('name', 'like', "%$search%");
                });
            });

        $timetables = $query->paginate($perPage);

        $semesters = Semester::all();

        return inertia('Timetable/View', [
            'timetables' => $timetables,
            'semesters' => $semesters,
            'selectedSemester' => $semesterId,
            'perPage' => $perPage,
            'search' => $search,
        ]);
    }
}
