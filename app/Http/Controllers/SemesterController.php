<?php

namespace App\Http\Controllers;

use App\Models\Semester;
use App\Models\Unit;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SemesterController extends Controller {
    public function index(): Response {
        $semesters = Semester::with('units')->get();
        return Inertia::render('Semesters/index', ['semesters' => $semesters]);
    }

    public function assignUnits(Request $request, Semester $semester) {
        // Validate input
        $request->validate([
            'units' => 'array|required',
            'units.*' => 'exists:units,id'
        ]);

        // Assign units to the semester
        $semester->units()->sync($request->input('units'));

        return redirect()->back()->with('success', 'Units assigned successfully.');
    }

    // Fetch all semesters with assigned units
    public function viewAssignedUnits(): Response
    {
        $semesters = Semester::with('units')->get();

        return Inertia::render('Semesters/ViewAssignedUnits', [
            'semesters' => $semesters
        ]);
    }
}
