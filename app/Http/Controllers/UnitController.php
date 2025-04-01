<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use Illuminate\Http\Request;
use Inertia\Inertia;

class UnitController extends Controller
{
    public function index()
    {
        $units = Unit::paginate(10); // Use pagination
        return Inertia::render('Units/index', [
            'units' => $units,
            'perPage' => 10,
            'search' => request('search', ''),
        ]);
    }

    public function create()
    {
        return Inertia::render('Units/Create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'code' => 'required|unique:units',
            'name' => 'required',
        ]);

        Unit::create($request->all());

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
