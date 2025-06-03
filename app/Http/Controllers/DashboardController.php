<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\User;
use App\Models\Enrollment;
use App\Models\Semester;
use App\Models\ExamTimetable;
use App\Models\Unit;
use App\Models\ClassTimetable; // Add this model
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    /**
     * Display the student dashboard.
     */
    public function studentDashboard(Request $request)
    {
        $user = $request->user();
        
        if (!$user || !$user->code) {
            Log::error('Student dashboard accessed with invalid user', [
                'user_id' => $user ? $user->id : 'null',
                'has_code' => $user && isset($user->code)
            ]);
            
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
            
            if ($studentSemesters->isEmpty()) {
                $selectedSemester = $currentSemester;
            } else {
                $activeSemester = $studentSemesters->firstWhere('is_active', true);
                $selectedSemester = $activeSemester ?? $studentSemesters->sortByDesc('id')->first();
            }
            
            if (!$selectedSemester) {
                throw new \Exception('No valid semester found for student');
            }
            
            // Get enrolled units for the student in the selected semester
            $enrolledUnits = Enrollment::where('student_code', $user->code)
                ->where('semester_id', $selectedSemester->id)
                ->with(['unit.school'])
                ->get()
                ->map(function ($enrollment) {
                    return $enrollment->unit;
                })
                ->filter()
                ->unique('id');
            
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
        }
        
        Log::info('Student dashboard data', [
            'student_code' => $user->code,
            'selected_semester_id' => $selectedSemester ? $selectedSemester->id : null,
            'selected_semester_name' => $selectedSemester ? $selectedSemester->name : null,
            'enrolled_units_count' => $enrolledUnits->count(),
            'upcoming_exams_count' => $upcomingExams->count()
        ]);
        
        return Inertia::render('Student/Dashboard', [
            'currentSemester' => $selectedSemester,
            'enrolledUnits' => $enrolledUnits,
            'upcomingExams' => $upcomingExams,
            'selectedSemesterId' => $selectedSemester ? $selectedSemester->id : null,
        ]);
    }

    /**
     * Display the lecturer dashboard.
     */
   public function lecturerDashboard(Request $request)
{
    $user = $request->user();
    
    if (!$user) {
        return Inertia::render('Lecturer/Dashboard', [
            'error' => 'User profile is incomplete. Please contact an administrator.',
            'currentSemester' => null,
            'lecturerSemesters' => [],
            'unitsBySemester' => [],
            'studentCounts' => [],
        ]);
    }
    
    // Get current semester
    $currentSemester = Semester::where('is_active', true)->first();
    if (!$currentSemester) {
        $currentSemester = Semester::latest()->first();
    }
    
    $lecturerSemesters = collect([]);
    $unitsBySemester = [];
    $studentCounts = [];
    
    try {
        // DIRECT APPROACH: Query the enrollments table using lecturer_code
        // This matches what we see in the Enrollments page
        $lecturerCode = $user->code;
        
        // Get all semesters where this lecturer has assignments
        $lecturerSemesters = Semester::whereHas('enrollments', function($query) use ($lecturerCode) {
            $query->where('lecturer_code', $lecturerCode);
        })->get();
        
        // For each semester, get the units assigned to this lecturer
        foreach ($lecturerSemesters as $semester) {
            // Get units for this lecturer in this semester
            $units = Unit::whereHas('enrollments', function($query) use ($lecturerCode, $semester) {
                $query->where('lecturer_code', $lecturerCode)
                      ->where('semester_id', $semester->id);
            })->with('faculty')->get();
            
            if ($units->count() > 0) {
                $unitsBySemester[$semester->id] = [
                    'semester' => $semester,
                    'units' => $units->toArray()
                ];
                
                // Count students for each unit
                $studentCounts[$semester->id] = [];
                foreach ($units as $unit) {
                    $studentCounts[$semester->id][$unit->id] = Enrollment::where('unit_id', $unit->id)
                        ->where('semester_id', $semester->id)
                        ->whereNotNull('student_code')
                        ->distinct('student_code')
                        ->count();
                }
            }
        }
        
        // If no results found, log detailed debug info
        if (empty($unitsBySemester)) {
            Log::warning('No units found for lecturer', [
                'lecturer_code' => $lecturerCode,
                'lecturer_name' => $user->name,
                'semesters_checked' => $lecturerSemesters->pluck('id')->toArray(),
                'enrollment_check' => Enrollment::where('lecturer_code', $lecturerCode)->count(),
                'all_lecturer_codes' => Enrollment::whereNotNull('lecturer_code')
                    ->distinct('lecturer_code')
                    ->pluck('lecturer_code')
                    ->toArray()
            ]);
        }
        
    } catch (\Exception $e) {
        Log::error('Error in lecturer dashboard', [
            'lecturer_code' => $user->code ?? 'No code',
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
    }
    
    // Enhanced debug logging
    Log::info('Lecturer dashboard debug', [
        'lecturer_id' => $user->id,
        'lecturer_code' => $user->code ?? 'No code',
        'current_semester_id' => $currentSemester ? $currentSemester->id : null,
        'lecturer_semesters_count' => $lecturerSemesters->count(),
        'units_by_semester_keys' => array_keys($unitsBySemester),
        'total_units_count' => array_sum(array_map(function($semester) {
            return count($semester['units']);
        }, $unitsBySemester))
    ]);
    
    return Inertia::render('Lecturer/Dashboard', [
        'currentSemester' => $currentSemester,
        'lecturerSemesters' => $lecturerSemesters,
        'unitsBySemester' => $unitsBySemester,
        'studentCounts' => $studentCounts
    ]);
}
}
