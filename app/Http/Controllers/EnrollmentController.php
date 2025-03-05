<?php

namespace App\Http\Controllers;

use App\Models\EnrollmentGroup;
use App\Models\Semester;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EnrollmentController extends Controller
{
    public function index()
    {
        $enrollmentGroups = EnrollmentGroup::all();
        $semesters = Semester::all();
        return Inertia::render('EnrollmentGroups/index', [
            'enrollmentGroups' => $enrollmentGroups,
            'semesters' => $semesters,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'semester_id' => 'required|exists:semesters,id',
        ]);

        EnrollmentGroup::create($request->all());

        return redirect()->route('enrollment-groups.index');
    }

    public function update(Request $request, EnrollmentGroup $enrollmentGroup)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'semester_id' => 'required|exists:semesters,id',
        ]);

        $enrollmentGroup->update($request->all());

        return redirect()->route('enrollment-groups.index');
    }

    public function destroy(EnrollmentGroup $enrollmentGroup)
    {
        $enrollmentGroup->delete();

        return redirect()->route('enrollment-groups.index');
    }
}
