<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\User;
use App\Models\Enrollment;
use App\Models\Semester;
use App\Models\ExamTimetable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    /**
     * Display the student dashboard.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Inertia\Response
     */
    public function studentDashboard(Request $request)
{
    $user = $request->user();
    
    // Get current semester
    $currentSemester = Semester::where('is_active', true)->first();
    if (!$currentSemester) {
        $currentSemester = Semester::latest()->first();
    }
    
    // Find semesters where the student has enrollments
    $studentSemesterIds = Enrollment::where('student_code', $user->code)
        ->distinct()
        ->pluck('semester_id')
        ->toArray();
    
    // If student has enrollments, default to their first enrollment semester
    // Otherwise use the current semester
    $defaultSemesterId = !empty($studentSemesterIds) 
        ? $studentSemesterIds[0] 
        : $currentSemester->id;
    
    // Get enrolled units for the student in their first enrollment semester
    $enrolledUnits = Enrollment::where('student_code', $user->code)
        ->where('semester_id', $defaultSemesterId)
        ->with(['unit.faculty']) // Make sure to eager load the unit and faculty
        ->get()
        ->pluck('unit') // Extract just the unit objects
        ->unique('id'); // Ensure no duplicates
    
    // Get upcoming exams for the student
    $upcomingExams = ExamTimetable::whereHas('unit', function($query) use ($user) {
            $query->whereHas('enrollments', function($q) use ($user) {
                $q->where('student_code', $user->code);
            });
        })
        ->where('date', '>=', now()->format('Y-m-d'))
        ->orderBy('date')
        ->orderBy('start_time')
        ->limit(5)
        ->get();
    
    // Log for debugging
    Log::info('Student dashboard data', [
        'student_code' => $user->code,
        'default_semester_id' => $defaultSemesterId,
        'enrolled_units_count' => $enrolledUnits->count(),
        'upcoming_exams_count' => $upcomingExams->count()
    ]);
    
    return Inertia::render('Student/Dashboard', [
        'currentSemester' => $currentSemester,
        'enrolledUnits' => $enrolledUnits, // Send the array of units instead of just the count
        'upcomingExams' => $upcomingExams,
        'selectedSemesterId' => $defaultSemesterId,
    ]);
}
}
