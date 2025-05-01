<?php

namespace App\Http\Controllers;

use App\Models\Semester;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Timetable;
use Illuminate\Support\Facades\DB;

class SemesterController extends Controller
{
    /**
     * Display a listing of the semesters.
     *
     * @return \Inertia\Response
     */
    public function index()
    {
        $semesters = Semester::orderBy('name')->get();
        $user = auth()->user();

        return Inertia::render('Semesters/index', [
            'semesters' => $semesters,
            'can' => [
                'create' => $user->can('create-semesters'),
                'edit' => $user->can('edit-semesters'),
                'delete' => $user->can('delete-semesters'),
            ],
        ]);
    }

    /**
     * Show the form for creating a new semester.
     *
     * @return \Inertia\Response
     */
    public function create()
    {
        return Inertia::render('Semesters/Create');
    }

    /**
     * Store a newly created semester in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'is_active' => 'boolean',
        ]);

        DB::beginTransaction();
        
        try {
            // If setting this new semester as active, deactivate all others
            if ($request->has('is_active') && $request->is_active) {
                Semester::where('is_active', true)->update(['is_active' => false]);
            }
            
            Semester::create([
                'name' => $request->name,
                'is_active' => $request->has('is_active') ? $request->is_active : false,
            ]);
            
            DB::commit();
            
            return redirect()->route('semesters.index')->with('success', 'Semester created successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            
            return redirect()->route('semesters.index')->with('error', 'Failed to create semester: ' . $e->getMessage());
        }
    }

    /**
     * Show the form for editing the specified semester.
     *
     * @param  \App\Models\Semester  $semester
     * @return \Inertia\Response
     */
    public function edit(Semester $semester)
    {
        return Inertia::render('Semesters/Edit', [
            'semester' => $semester,
        ]);
    }

    /**
     * Update the specified semester in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Semester  $semester
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, Semester $semester)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'is_active' => 'boolean',
        ]);

        // Begin a database transaction
        DB::beginTransaction();
        
        try {
            // If setting this semester as active, deactivate all others
            if ($request->has('is_active') && $request->is_active && !$semester->is_active) {
                Semester::where('is_active', true)->update(['is_active' => false]);
            }
            
            // Update the semester
            $semester->update([
                'name' => $request->name,
                'is_active' => $request->has('is_active') ? $request->is_active : false,
            ]);
            
            // Commit the transaction
            DB::commit();
            
            return redirect()->route('semesters.index')->with('success', 'Semester updated successfully.');
        } catch (\Exception $e) {
            // Something went wrong, rollback the transaction
            DB::rollBack();
            
            return redirect()->route('semesters.index')->with('error', 'Failed to update semester: ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified semester from storage.
     *
     * @param  \App\Models\Semester  $semester
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(Semester $semester)
    {
        $semester->delete();

        return redirect()->route('semesters.index')->with('success', 'Semester deleted successfully.');
    }

    /**
     * Toggle the active status of a semester.
     * When a semester is activated, all other semesters are deactivated.
     *
     * @param  \App\Models\Semester  $semester
     * @return \Illuminate\Http\RedirectResponse
     */
    public function toggleActive(Semester $semester)
    {
        // Begin a database transaction
        DB::beginTransaction();
        
        try {
            // If we're activating this semester, deactivate all others
            if (!$semester->is_active) {
                // Deactivate all semesters
                Semester::where('is_active', true)->update(['is_active' => false]);
                
                // Activate the selected semester
                $semester->is_active = true;
                $semester->save();
                
                $message = "Semester '{$semester->name}' has been set as the active semester.";
            } else {
                // If we're deactivating the current active semester
                $semester->is_active = false;
                $semester->save();
                
                $message = "Semester '{$semester->name}' has been deactivated. No semester is currently active.";
            }
            
            // Commit the transaction
            DB::commit();
            
            return redirect()->route('semesters.index')->with('success', $message);
        } catch (\Exception $e) {
            // Something went wrong, rollback the transaction
            DB::rollBack();
            
            return redirect()->route('semesters.index')->with('error', 'Failed to update semester status: ' . $e->getMessage());
        }
    }

    /**
     * View timetable for a specific semester.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Inertia\Response
     */
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
