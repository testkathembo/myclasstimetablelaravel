<?php

namespace App\Http\Controllers;

use App\Models\Semester;
use App\Models\Unit;
use App\Models\ClassModel;
use Illuminate\Http\Request;

class SemesterUnitController extends Controller
{
    public function index()
    {
        $semesters = Semester::with('units')->get();
        $units = Unit::all();
        $classes = ClassModel::all();

        return inertia('SemesterUnits/Index', [
            'semesters' => $semesters,
            'units' => $units,
            'classes' => $classes,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'semester_id' => 'required|exists:semesters,id',
            'class_id' => 'required|exists:classes,id',
            'unit_ids' => 'required|array',
            'unit_ids.*' => 'exists:units,id',
        ]);

        $semester = Semester::findOrFail($validated['semester_id']);
        foreach ($validated['unit_ids'] as $unitId) {
            $semester->units()->attach($unitId, ['class_id' => $validated['class_id']]);
        }

        return redirect()->back()->with('success', 'Units assigned to class in semester successfully!');
    }

    public function updateUnit(Request $request, $semesterId, $unitId)
    {
        $validated = $request->validate([
            'class_id' => 'required|exists:classes,id',
        ]);

        $semester = Semester::findOrFail($semesterId);
        $semester->units()->updateExistingPivot($unitId, ['class_id' => $validated['class_id']]);

        return redirect()->back()->with('success', 'Unit updated successfully!');
    }

    public function deleteUnit($semesterId, $unitId)
    {
        $semester = Semester::findOrFail($semesterId);
        $semester->units()->detach($unitId);

        return redirect()->back()->with('success', 'Unit removed successfully!');
    }
}