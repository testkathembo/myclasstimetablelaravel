<?php

namespace App\Http\Controllers;

use App\Models\Enrollment;
use App\Models\Unit;
use App\Models\User;
use App\Models\Semester;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class EnrollmentController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');

        $enrollments = Enrollment::with(['student', 'unit', 'semester'])
            ->when($search, function ($query, $search) {
                $query->whereHas('unit', function ($q) use ($search) {
                    $q->where('name', 'like', "%$search%");
                });
            })
            ->paginate(10);

        $semesters = Semester::all();
        // Get all students
        $students = User::whereHas('roles', function ($query) {
            $query->where('name', 'Student');
        })->get();
        // Get all lecturers
        $lecturers = User::whereHas('roles', function ($query) {
            $query->where('name', 'Lecturer');
        })->get();
        // Get all units
        $units = Unit::all();
        
        // Get lecturer unit assignments
        // This query gets distinct unit_id and lecturer_id combinations
        $lecturerUnitAssignments = DB::table('enrollments')
            ->select('unit_id', 'lecturer_id')
            ->whereNotNull('lecturer_id')
            ->distinct()
            ->get()
            ->map(function ($assignment) {
                $unit = Unit::find($assignment->unit_id);
                $lecturer = User::find($assignment->lecturer_id);
                
                return [
                    'unit_id' => $assignment->unit_id,
                    'lecturer_id' => $assignment->lecturer_id,
                    'unit' => $unit,
                    'lecturer' => $lecturer
                ];
            });

        return Inertia::render('Enrollments/Index', [
            'enrollments' => $enrollments,
            'semesters' => $semesters,
            'students' => $students,
            'units' => $units,
            'lecturers' => $lecturers,
            'lecturerUnitAssignments' => $lecturerUnitAssignments,
        ]);
    }

    public function create()
    {
        $students = User::whereHas('roles', function ($query) {
            $query->where('name', 'Student');
        })->get();

        $units = Unit::all();
        $semesters = Semester::all();

        return Inertia::render('Enrollments/Create', [
            'students' => $students,
            'units' => $units,
            'semesters' => $semesters,
        ]);
    }

    public function store(Request $request)
    {
        // Log the incoming request data for debugging
        Log::info('Enrollment request data:', $request->all());
        
        $request->validate([
            'student_id' => 'required|exists:users,id',
            'semester_id' => 'required|exists:semesters,id',
            'unit_ids' => 'required|array',
            'unit_ids.*' => 'exists:units,id',
        ]);

        $successCount = 0;
        
        // Create an enrollment for each selected unit
        foreach ($request->unit_ids as $unitId) {
            // Check if enrollment already exists to avoid duplicates
            $existingEnrollment = Enrollment::where([
                'student_id' => $request->student_id,
                'unit_id' => $unitId,
                'semester_id' => $request->semester_id,
            ])->first();
            
            if (!$existingEnrollment) {
                // Get the lecturer assigned to this unit (if any)
                $lecturerId = DB::table('enrollments')
                    ->where('unit_id', $unitId)
                    ->whereNotNull('lecturer_id')
                    ->value('lecturer_id');
                
                Enrollment::create([
                    'student_id' => $request->student_id,
                    'unit_id' => $unitId,
                    'semester_id' => $request->semester_id,
                    'lecturer_id' => $lecturerId // Assign the lecturer if one is assigned to this unit
                ]);
                $successCount++;
            }
        }

        return redirect()->route('enrollments.index')
            ->with('success', $successCount > 0 
                ? "{$successCount} enrollments created successfully." 
                : "No new enrollments were created.");
    }

    public function edit(Enrollment $enrollment)
    {
        $students = User::whereHas('roles', function ($query) {
            $query->where('name', 'Student');
        })->get();

        $units = Unit::all();
        $semesters = Semester::all();

        return Inertia::render('Enrollments/Edit', [
            'enrollment' => $enrollment,
            'students' => $students,
            'units' => $units,
            'semesters' => $semesters,
        ]);
    }

    public function update(Request $request, Enrollment $enrollment)
    {
        $request->validate([
            'student_id' => 'required|exists:users,id',
            'unit_id' => 'required|exists:units,id',
            'semester_id' => 'required|exists:semesters,id',
            'lecturer_id' => 'nullable|exists:users,id',
        ]);

        $enrollment->update($request->only('student_id', 'unit_id', 'semester_id', 'lecturer_id'));

        return redirect()->route('enrollments.index')->with('success', 'Enrollment updated successfully.');
    }

    public function destroy(Enrollment $enrollment)
    {
        $enrollment->delete();

        return redirect()->route('enrollments.index')->with('success', 'Enrollment deleted successfully.');
    }
    
    /**
     * Assign a lecturer to a unit
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function assignLecturers(Request $request)
    {
        Log::info('Lecturer assignment request data:', $request->all());
        
        $request->validate([
            'unit_id' => 'required|exists:units,id',
            'lecturer_id' => 'required|exists:users,id',
        ]);
        
        // Verify the lecturer_id belongs to a user with the Lecturer role
        $isLecturer = User::where('id', $request->lecturer_id)
            ->whereHas('roles', function ($query) {
                $query->where('name', 'Lecturer');
            })->exists();
            
        if (!$isLecturer) {
            return redirect()->route('enrollments.index')
                ->with('error', 'Selected user is not a lecturer.');
        }
        
        // Update all enrollments for this unit to have the same lecturer
        Enrollment::where('unit_id', $request->unit_id)
            ->update(['lecturer_id' => $request->lecturer_id]);
        
        return redirect()->route('enrollments.index')
            ->with('success', 'Lecturer assigned to unit successfully.');
    }
    
    /**
     * Remove a lecturer assignment
     *
     * @param  int  $unitId
     * @return \Illuminate\Http\Response
     */
    public function destroyLecturerAssignment($unitId)
    {
        // Remove lecturer assignment from all enrollments for this unit
        Enrollment::where('unit_id', $unitId)
            ->update(['lecturer_id' => null]);
        
        return redirect()->route('enrollments.index')
            ->with('success', 'Lecturer assignment removed successfully.');
    }
    
    /**
     * Get units assigned to a lecturer
     *
     * @param  int  $lecturerId
     * @return \Illuminate\Http\Response
     */
    public function getLecturerUnits($lecturerId)
    {
        $lecturer = User::findOrFail($lecturerId);
        
        // Verify this is a lecturer
        $isLecturer = $lecturer->roles()->where('name', 'Lecturer')->exists();
        if (!$isLecturer) {
            return response()->json(['error' => 'User is not a lecturer'], 400);
        }
        
        // Get distinct units assigned to this lecturer
        $units = DB::table('enrollments')
            ->select('enrollments.unit_id', 'units.name as unit_name', 'units.code as unit_code', 'semesters.name as semester_name')
            ->join('units', 'enrollments.unit_id', '=', 'units.id')
            ->leftJoin('semesters', 'enrollments.semester_id', '=', 'semesters.id')
            ->where('enrollments.lecturer_id', $lecturerId)
            ->distinct()
            ->get();
        
        return response()->json([
            'lecturer' => [
                'id' => $lecturer->id,
                'first_name' => $lecturer->first_name,
                'last_name' => $lecturer->last_name,
            ],
            'units' => $units
        ]);
    }
}