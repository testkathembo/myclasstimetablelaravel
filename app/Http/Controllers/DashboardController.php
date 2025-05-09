<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\User;
use App\Models\Enrollment;
use App\Models\Semester;
use App\Models\ExamTimetable;
use App\Models\Unit;
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
        
        if (!$user || !$user->code) {
            Log::error('Student dashboard accessed with invalid user', [
                'user_id' => $user ? $user->id : 'null',
                'has_code' => $user && isset($user->code)
            ]);
            
            // Return a basic view with an error message
            return Inertia::render('Student/Dashboard', [
                'error' => 'User profile is incomplete. Please contact an administrator.',
                'currentSemester' => null,
                'enrolledUnits' => [],
                'upcomingExams' => [],
                'selectedSemesterId' => null,
            ]);
        }
        
        // Get current semester
        $currentSemester = Semester::where('is_active', true)->first();
        if (!$currentSemester) {
            $currentSemester = Semester::latest()->first();
        }
        
        // Default values in case of errors
        $selectedSemester = $currentSemester;
        $enrolledUnits = collect([]);
        $upcomingExams = collect([]);
        
        try {
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
            
            // Safety check for selected semester
            if (!$selectedSemester) {
                throw new \Exception('No valid semester found for student');
            }
            
            // Get enrolled units for the student in the selected semester
            $enrolledUnits = Enrollment::where('student_code', $user->code)
                ->where('semester_id', $selectedSemester->id)
                ->with(['unit.faculty']) // Make sure to eager load the unit and faculty
                ->get()
                ->map(function ($enrollment) {
                    // Only include enrollments with valid units
                    return $enrollment->unit;
                })
                ->filter() // Remove null values
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
                
        } catch (\Exception $e) {
            Log::error('Error in student dashboard', [
                'student_code' => $user->code,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // If there was an error, we'll still render the dashboard with empty data
            // and log the error, but not crash the application
        }
        
        // Log for debugging
        Log::info('Student dashboard data', [
            'student_code' => $user->code,
            'selected_semester_id' => $selectedSemester ? $selectedSemester->id : null,
            'selected_semester_name' => $selectedSemester ? $selectedSemester->name : null,
            'enrolled_units_count' => $enrolledUnits->count(),
            'upcoming_exams_count' => $upcomingExams->count()
        ]);
        
        return Inertia::render('Student/Dashboard', [
            'currentSemester' => $selectedSemester, // Use the selected semester, not just any "current" semester
            'enrolledUnits' => $enrolledUnits,
            'upcomingExams' => $upcomingExams,
            'selectedSemesterId' => $selectedSemester ? $selectedSemester->id : null,
        ]);
    }

    /**
     * Display the lecturer dashboard.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Inertia\Response
     */
    public function lecturerDashboard(Request $request)
    {
        $user = $request->user();
        
        if (!$user || !$user->code) {
            Log::error('Lecturer dashboard accessed with invalid user', [
                'user_id' => $user ? $user->id : 'null',
                'has_code' => $user && isset($user->code)
            ]);
            
            // Return a basic view with an error message
            return Inertia::render('Lecturer/Dashboard', [
                'error' => 'User profile is incomplete. Please contact an administrator.',
                'currentSemester' => null,
                'lecturerSemesters' => [],
                'unitsBySemester' => [],
                'studentCounts' => [],
            ]);
        }
        
        // Get current semester for reference
        $currentSemester = Semester::where('is_active', true)->first();
        if (!$currentSemester) {
            $currentSemester = Semester::latest()->first();
        }
        
        // Default values in case of errors
        $lecturerSemesters = collect([]);
        $unitsBySemester = [];
        $studentCounts = [];
        
        try {
            // Get all semesters where the lecturer has assigned units
            $lecturerSemesters = Enrollment::where('lecturer_code', $user->code)
                ->distinct('semester_id')
                ->join('semesters', 'enrollments.semester_id', '=', 'semesters.id')
                ->select('semesters.*')
                ->orderBy('semesters.name')
                ->get();
                
            // Get all enrollments for this lecturer across all semesters
            $allEnrollments = Enrollment::where('lecturer_code', $user->code)
                ->with(['unit.faculty', 'semester'])
                ->get();
                
            // Group units by semester
            $unitsBySemester = [];
            foreach ($allEnrollments as $enrollment) {
                if (!$enrollment->semester_id || !$enrollment->unit) {
                    continue; // Skip invalid enrollments
                }
                
                if (!isset($unitsBySemester[$enrollment->semester_id])) {
                    $unitsBySemester[$enrollment->semester_id] = [
                        'semester' => $enrollment->semester,
                        'units' => []
                    ];
                }
                
                // Check if unit already exists in the array to avoid duplicates
                $unitExists = false;
                foreach ($unitsBySemester[$enrollment->semester_id]['units'] as $unit) {
                    if ($unit['id'] === $enrollment->unit->id) {
                        $unitExists = true;
                        break;
                    }
                }
                
                if (!$unitExists) {
                    $unitsBySemester[$enrollment->semester_id]['units'][] = [
                        'id' => $enrollment->unit->id,
                        'code' => $enrollment->unit->code,
                        'name' => $enrollment->unit->name,
                        'faculty' => $enrollment->unit->faculty ? [
                            'name' => $enrollment->unit->faculty->name
                        ] : null
                    ];
                }
            }
            
            // Count students per unit per semester
            $studentCounts = [];
            foreach ($allEnrollments as $enrollment) {
                if (!$enrollment->unit) {
                    continue; // Skip if unit is null
                }
                
                $unitId = $enrollment->unit_id;
                $semesterId = $enrollment->semester_id;
                
                if (!isset($studentCounts[$semesterId])) {
                    $studentCounts[$semesterId] = [];
                }
                
                if (!isset($studentCounts[$semesterId][$unitId])) {
                    $studentCounts[$semesterId][$unitId] = Enrollment::where('unit_id', $unitId)
                        ->where('semester_id', $semesterId)
                        ->where('student_code', '!=', null)
                        ->distinct('student_code')
                        ->count();
                }
            }
            
        } catch (\Exception $e) {
            Log::error('Error in lecturer dashboard', [
                'lecturer_code' => $user->code,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // If there was an error, we'll still render the dashboard with empty data
            // and log the error, but not crash the application
        }
        
        // For debugging
        Log::info('Lecturer dashboard', [
            'lecturer_id' => $user->id,
            'lecturer_code' => $user->code ?? 'No code',
            'current_semester_id' => $currentSemester ? $currentSemester->id : null,
            'lecturer_semesters_count' => $lecturerSemesters->count(),
            'units_by_semester_count' => count($unitsBySemester),
            'has_lecturer_role' => method_exists($user, 'hasRole') ? $user->hasRole('Lecturer') : 'method_not_exists'
        ]);
        
        return Inertia::render('Lecturer/Dashboard', [
            'currentSemester' => $currentSemester,
            'lecturerSemesters' => $lecturerSemesters,
            'unitsBySemester' => $unitsBySemester,
            'studentCounts' => $studentCounts
        ]);
    }
}
