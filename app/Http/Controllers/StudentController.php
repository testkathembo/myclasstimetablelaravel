<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Models\Unit;
use App\Models\Enrollment;
use App\Models\Semester;
use Inertia\Inertia;

class StudentController extends Controller
{
    /**
     * Display the student's enrollments
     */
    public function myEnrollments(Request $request)
    {
        $user = $request->user();
        
        // Check if user has the Student role
        if (!$user->hasRole('Student')) {
            abort(403, 'You must be a student to access this page.');
        }
        
        // Get current semester
        $currentSemester = Semester::where('is_active', true)->first();
        if (!$currentSemester) {
            $currentSemester = Semester::latest()->first();
        }
        
        // Get all semesters for filtering
        $semesters = Semester::orderBy('name')->get();
        
        // Get selected semester (default to current)
        $selectedSemesterId = $request->input('semester_id', $currentSemester->id);
        
        // Get student's enrolled units for the selected semester using student code
        $enrolledUnits = Enrollment::byStudentCode($user->code)
            ->where('semester_id', $selectedSemesterId)
            ->with(['unit.faculty', 'unit.lecturer'])
            ->get();
            
        return Inertia::render('Student/Enrollments', [
            'enrolledUnits' => $enrolledUnits,
            'currentSemester' => $currentSemester,
            'semesters' => $semesters,
            'selectedSemesterId' => (int)$selectedSemesterId,
        ]);
    }
    
    /**
     * Display the student's profile
     */
    public function profile(Request $request)
    {
        $user = $request->user();
        
        // Get student details with related data
        $student = User::with(['faculty', 'enrollments'])
            ->where('id', $user->id)
            ->first();
            
        return Inertia::render('Student/Profile', [
            'student' => $student,
        ]);
    }
}