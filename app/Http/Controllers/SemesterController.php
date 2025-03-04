<?php

namespace App\Http\Controllers;

use App\Models\Semester;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SemesterController extends Controller
{
    public function index()
    {
        $semesters = Semester::all();
        return Inertia::render('Semesters/index', ['semesters' => $semesters]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date',
        ]);

        Semester::create($request->all());

        return redirect()->route('semesters.index');
    }

    public function update(Request $request, Semester $semester)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date',
        ]);

        $semester->update($request->all());

        return redirect()->route('semesters.index');
    }

    public function destroy(Semester $semester)
    {
        $semester->delete();

        return redirect()->route('semesters.index');
    }
}
