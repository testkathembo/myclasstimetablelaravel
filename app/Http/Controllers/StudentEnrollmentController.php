<?php

namespace App\Http\Controllers;

use App\Models\Enrollment;
use App\Models\Unit;
use App\Models\Semester;
use App\Services\EnrollmentService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class StudentEnrollmentController extends Controller
{
    protected $enrollmentService;

    /**
     * Create a new controller instance.
     *
     * @param  \App\Services\EnrollmentService  $enrollmentService
     * @return void
     */
    public function __construct(EnrollmentService $enrollmentService)
    {
        $this->middleware('auth');
        $this->enrollmentService = $enrollmentService;
    }

    /**
     * Show the enrollment form.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Inertia\Response
     */
    public function showEnrollmentForm(Request $request)
    {
        $user = auth()->user();
        
        // Ensure the user is a student
        if ($user->role !== 'student') {
            abort(403, 'Only students can access this page.');
        }
        
        // Get all semesters
        $semesters = Semester::where('is_active', true)
            ->orWhere('id', function ($query) {
                $query->select('semester_id')
                    ->from('units')
                    ->where('is_active', true)
                    ->groupBy('semester_id')
                    ->limit(1);
            })
            ->select('id', 'name')
            ->orderBy('name')
            ->get();
            
        // Get current enrollments for the student
        $currentEnrollments = Enrollment::where('student_code', $user->code)
            ->select('unit_id', 'semester_id', 'group')
            ->get();

        return Inertia::render('Student/EnrollmentForm', [
            'semesters' => $semesters,
            'currentEnrollments' => $currentEnrollments,
        ]);
    }

    /**
     * Process student enrollment.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function enroll(Request $request)
    {
        $user = auth()->user();
        
        // Ensure the user is a student
        if ($user->role !== 'student') {
            abort(403, 'Only students can access this feature.');
        }
        
        $validated = $request->validate([
            'semester_id' => 'required|exists:semesters,id',
            'unit_ids' => 'required|array',
            'unit_ids.*' => 'exists:units,id',
        ]);
        
        $successCount = 0;
        $errors = [];
        
        foreach ($validated['unit_ids'] as $unitId) {
            // Check if already enrolled
            $exists = Enrollment::where('student_code', $user->code)
                ->where('unit_id', $unitId)
                ->where('semester_id', $validated['semester_id'])
                ->exists();
                
            if ($exists) {
                $errors[] = "You are already enrolled in one of the selected units.";
                continue;
            }
            
            // Check if unit is assigned to the semester
            $unit = Unit::find($unitId);
            if ($unit->semester_id != $validated['semester_id']) {
                $errors[] = "One of the selected units is not available for this semester.";
                continue;
            }
            
            try {
                // Use the enrollment service to handle group assignment
                $this->enrollmentService->enrollStudent(
                    $user->code,
                    $unitId,
                    $validated['semester_id']
                );
                
                $successCount++;
            } catch (\Exception $e) {
                $errors[] = $e->getMessage();
            }
        }
        
        if ($successCount > 0) {
            return redirect()->back()
                ->with('success', "Successfully enrolled in {$successCount} unit(s).");
        }
        
        return redirect()->back()
            ->withErrors(['error' => $errors]);
    }

    /**
     * View current enrollments.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Inertia\Response
     */
    public function viewEnrollments(Request $request)
    {
        $user = auth()->user();
        
        // Ensure the user is a student
        if ($user->role !== 'student') {
            abort(403, 'Only students can access this page.');
        }
        
        // Get active semester or selected semester
        $semesterId = $request->input('semester_id');
        if (!$semesterId) {
            $activeSemester = Semester::where('is_active', true)->first();
            $semesterId = $activeSemester ? $activeSemester->id : null;
        }
        
        // Get enrollments for the student
        $enrollments = Enrollment::where('student_code', $user->code)
            ->when($semesterId, function ($query) use ($semesterId) {
                return $query->where('semester_id', $semesterId);
            })
            ->with(['unit', 'semester'])
            ->get();
            
        // Get all semesters for the filter dropdown
        $semesters = Semester::select('id', 'name')->orderBy('name')->get();
        
        return Inertia::render('Student/MyEnrollments', [
            'enrollments' => $enrollments,
            'semesters' => $semesters,
            'selectedSemester' => $semesterId,
        ]);
    }
}