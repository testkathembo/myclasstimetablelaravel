<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use App\Models\Semester;
use Illuminate\Http\Request;
use Inertia\Inertia;

class UnitController extends Controller
{
    public function index()
    {
        try {
            $units = Unit::paginate(10); // Ensure the 'units' table exists and has data
            return Inertia::render('Units/index', [
                'units' => $units,
                'perPage' => 10,
                'search' => request('search', ''),
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to fetch units: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch units'], 500);
        }
    }

    public function create()
    {
        $semesters = Semester::all(); // Fetch all semesters
        return Inertia::render('Units/Create', ['semesters' => $semesters]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'code' => 'required|unique:units',
            'name' => 'required',
            'semester_id' => 'nullable|exists:semesters,id',
        ]);

        Unit::create([
            'code' => $request->code,
            'name' => $request->name,
            'semester_id' => $request->semester_id ?? 1, // Use default value if not provided
        ]);

        return redirect()->route('units.index')
                         ->with('success', 'Unit created successfully.');
    }

    public function show(Unit $unit)
    {
        return Inertia::render('Units/Show', ['unit' => $unit]);
    }

    public function edit(Unit $unit)
    {
        return Inertia::render('Units/Edit', ['unit' => $unit]);
    }

    public function update(Request $request, Unit $unit)
    {
        $request->validate([
            'code' => 'required|unique:units,code,' . $unit->id,
            'name' => 'required',
        ]);

        $unit->update($request->all());

        return redirect()->route('units.index')
                         ->with('success', 'Unit updated successfully.');
    }

    public function destroy(Unit $unit)
    {
        $unit->delete();

        return redirect()->route('units.index')
                         ->with('success', 'Unit deleted successfully.');
    }
}
