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
        $studentSemesters = Enrollment::where('student_code', $user->code)
            ->distinct('semester_id')
            ->join('semesters', 'enrollments.semester_id', '=', 'semesters.id')
            ->select('semesters.*')
            ->get();
        
        // If student has no enrollments, use the current semester
        if ($studentSemesters->isEmpty()) {
            $selectedSemester = $currentSemester;
        } else {
            // Find the active semester among student's enrolled semesters
            $activeSemester = $studentSemesters->firstWhere('is_active', true);
            
            // If no active semester found, use the most recent semester the student is enrolled in
            $selectedSemester = $activeSemester ?? $studentSemesters->sortByDesc('id')->first();
        }
        
        // Get enrolled units for the student in the selected semester
        $enrolledUnits = Enrollment::where('student_code', $user->code)
            ->where('semester_id', $selectedSemester->id)
            ->with(['unit.faculty']) // Make sure to eager load the unit and faculty
            ->get()
            ->pluck('unit') // Extract just the unit objects
            ->unique('id'); // Ensure no duplicates
        
        // Get upcoming exams for the student in the selected semester
        $upcomingExams = ExamTimetable::where('semester_id', $selectedSemester->id)
            ->whereHas('unit', function($query) use ($user) {
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
            'selected_semester_id' => $selectedSemester->id,
            'selected_semester_name' => $selectedSemester->name,
            'enrolled_units_count' => $enrolledUnits->count(),
            'upcoming_exams_count' => $upcomingExams->count()
        ]);
        
        return Inertia::render('Student/Dashboard', [
            'currentSemester' => $selectedSemester, // Use the selected semester, not just any "current" semester
            'enrolledUnits' => $enrolledUnits,
            'upcomingExams' => $upcomingExams,
            'selectedSemesterId' => $selectedSemester->id,
        ]);
    }
}
