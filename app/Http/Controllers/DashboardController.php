<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\User;
use App\Models\Enrollment;
use App\Models\Semester;
use App\Models\ExamTimetable;
use App\Models\Unit;
use App\Models\ClassTimetable;
use App\Models\School;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Display the admin dashboard with real statistics.
     */
    public function adminDashboard(Request $request)
    {
        try {
            // Get current semester
            $currentSemester = Semester::where('is_active', true)->first();
            if (!$currentSemester) {
                $currentSemester = Semester::latest()->first();
            }
            
            // Get previous month for comparison
            $currentMonth = now();
            $previousMonth = now()->subMonth();
            $previousWeek = now()->subWeek();
            
            // Total Users Statistics
            $totalUsers = User::count();
            $usersLastMonth = User::where('created_at', '>=', $previousMonth)
                                 ->where('created_at', '<', $currentMonth)
                                 ->count();
            $usersPreviousMonth = User::where('created_at', '>=', $previousMonth->copy()->subMonth())
                                     ->where('created_at', '<', $previousMonth)
                                     ->count();
            
            $usersGrowthRate = $usersPreviousMonth > 0 
                ? round((($usersLastMonth - $usersPreviousMonth) / $usersPreviousMonth) * 100, 1)
                : ($usersLastMonth > 0 ? 100 : 0);
            
            // Active Enrollments Statistics - FIXED to count all real enrollments
$activeEnrollments = Enrollment::whereNotNull('student_code')->count();

$enrollmentsLastWeek = Enrollment::where('created_at', '>=', $previousWeek)
                                ->where('created_at', '<', $currentMonth)
                                ->whereNotNull('student_code')
                                ->count();

$enrollmentsPreviousWeek = Enrollment::where('created_at', '>=', $previousWeek->copy()->subWeek())
                                   ->where('created_at', '<', $previousWeek)
                                   ->whereNotNull('student_code')
                                   ->count();
            if ($currentSemester) {
                $activeEnrollments = Enrollment::where('semester_id', $currentSemester->id)
                                              ->whereNotNull('student_code')
                                              ->count();
                
                $enrollmentsLastWeek = Enrollment::where('semester_id', $currentSemester->id)
                                                ->where('created_at', '>=', $previousWeek)
                                                ->whereNotNull('student_code')
                                                ->count();
                
                $enrollmentsPreviousWeek = Enrollment::where('semester_id', $currentSemester->id)
                                                   ->where('created_at', '>=', $previousWeek->copy()->subWeek())
                                                   ->where('created_at', '<', $previousWeek)
                                                   ->whereNotNull('student_code')
                                                   ->count();
            }
            
            $enrollmentsGrowthRate = $enrollmentsPreviousWeek > 0 
                ? round((($enrollmentsLastWeek - $enrollmentsPreviousWeek) / $enrollmentsPreviousWeek) * 100, 1)
                : ($enrollmentsLastWeek > 0 ? 100 : 0);
            
            // Active Classes Statistics
            $activeClasses = 0;
            $classesLastMonth = 0;
            $classesPreviousMonth = 0;
            
            if ($currentSemester) {
                // Count distinct units that have enrollments in current semester
                $activeClasses = Unit::whereHas('enrollments', function($query) use ($currentSemester) {
                    $query->where('semester_id', $currentSemester->id);
                })->count();
                
                // Classes added last month
                $classesLastMonth = Unit::where('created_at', '>=', $previousMonth)
                                       ->where('created_at', '<', $currentMonth)
                                       ->count();
                
                $classesPreviousMonth = Unit::where('created_at', '>=', $previousMonth->copy()->subMonth())
                                           ->where('created_at', '<', $previousMonth)
                                           ->count();
            }
            
            $classesGrowthRate = $classesPreviousMonth > 0 
                ? round((($classesLastMonth - $classesPreviousMonth) / $classesPreviousMonth) * 100, 1)
                : ($classesLastMonth > 0 ? 100 : 0);
            
            // Exam Sessions Statistics
            $examSessions = 0;
            $examsLastWeek = 0;
            $examsPreviousWeek = 0;
            
            if ($currentSemester) {
                $examSessions = ExamTimetable::where('semester_id', $currentSemester->id)
                                           ->where('date', '>=', now()->format('Y-m-d'))
                                           ->count();
                
                $examsLastWeek = ExamTimetable::where('semester_id', $currentSemester->id)
                                            ->where('created_at', '>=', $previousWeek)
                                            ->count();
                
                $examsPreviousWeek = ExamTimetable::where('semester_id', $currentSemester->id)
                                                ->where('created_at', '>=', $previousWeek->copy()->subWeek())
                                                ->where('created_at', '<', $previousWeek)
                                                ->count();
            }
            
            $examsGrowthRate = $examsPreviousWeek > 0 
                ? round((($examsLastWeek - $examsPreviousWeek) / $examsPreviousWeek) * 100, 1)
                : 0; // For exams, we might expect no growth or even decline
            
            // Recent Activities (optional - for future enhancement)
            $recentEnrollments = Enrollment::with(['unit', 'student'])
                                          ->whereNotNull('student_code')
                                          ->latest()
                                          ->limit(5)
                                          ->get();
            
            // System Statistics
            $totalSchools = School::count();
            $totalSemesters = Semester::count();
            
            $dashboardData = [
                'statistics' => [
                    'totalUsers' => [
                        'count' => $totalUsers,
                        'growthRate' => $usersGrowthRate,
                        'period' => 'from last month'
                    ],
                    'activeEnrollments' => [
                        'count' => $activeEnrollments,
                        'growthRate' => $enrollmentsGrowthRate,
                        'period' => 'from last week'
                    ],
                    'activeClasses' => [
                        'count' => $activeClasses,
                        'growthRate' => $classesGrowthRate,
                        'period' => 'from last month'
                    ],
                    'examSessions' => [
                        'count' => $examSessions,
                        'growthRate' => $examsGrowthRate,
                        'period' => 'from last week'
                    ]
                ],
                'currentSemester' => $currentSemester,
                'systemInfo' => [
                    'totalSchools' => $totalSchools,
                    'totalSemesters' => $totalSemesters,
                ],
                'recentEnrollments' => $recentEnrollments
            ];
            
            Log::info('Admin dashboard data generated', [
                'total_users' => $totalUsers,
                'active_enrollments' => $activeEnrollments,
                'active_classes' => $activeClasses,
                'exam_sessions' => $examSessions,
                'current_semester' => $currentSemester ? $currentSemester->name : 'None'
            ]);
            
            return Inertia::render('Admin/Dashboard', $dashboardData);
            
        } catch (\Exception $e) {
            Log::error('Error in admin dashboard', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Return safe defaults in case of error
            return Inertia::render('Admin/Dashboard', [
                'statistics' => [
                    'totalUsers' => ['count' => 0, 'growthRate' => 0, 'period' => 'from last month'],
                    'activeEnrollments' => ['count' => 0, 'growthRate' => 0, 'period' => 'from last week'],
                    'activeClasses' => ['count' => 0, 'growthRate' => 0, 'period' => 'from last month'],
                    'examSessions' => ['count' => 0, 'growthRate' => 0, 'period' => 'from last week']
                ],
                'currentSemester' => null,
                'systemInfo' => ['totalSchools' => 0, 'totalSemesters' => 0],
                'recentEnrollments' => [],
                'error' => 'Unable to load dashboard data'
            ]);
        }
    }

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