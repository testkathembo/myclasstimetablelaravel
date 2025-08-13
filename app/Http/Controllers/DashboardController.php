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
use App\Models\Student;
use App\Models\Lecturer;
use App\Models\Program;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Main dashboard entry point - redirects based on user role
     */
    public function index()
    {
        $user = auth()->user();

        // Enhanced debugging
        Log::info('Dashboard index accessed', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'user_roles' => $user->getRoleNames()->toArray(),
            'has_admin_role' => $user->hasRole('Admin'),
            'can_view_admin_dashboard' => $user->can('view admin dashboard'),
        ]);

        // Check for Faculty Admin roles first (school-specific)
        $roles = $user->getRoleNames();
        foreach ($roles as $role) {
            if (str_starts_with($role, 'Faculty Admin - ')) {
                $faculty = str_replace('Faculty Admin - ', '', $role);
                $schoolRoute = match($faculty) {
                    'SCES' => 'faculty.dashboard.sces',
                    'SBS' => 'faculty.dashboard.sbs',
                    'SLS' => 'faculty.dashboard.sls',
                    'TOURISM' => 'faculty.dashboard.tourism',
                    'SHM' => 'faculty.dashboard.shm',
                    'SHS' => 'faculty.dashboard.shs',
                    default => null
                };
                
                if ($schoolRoute) {
                    Log::info('Redirecting to school-specific dashboard', [
                        'user_id' => $user->id,
                        'faculty' => $faculty,
                        'route' => $schoolRoute
                    ]);
                    return redirect()->route($schoolRoute);
                }
            }
        }

        // Automatic role-based dashboard redirection
        if ($user->hasRole('Admin')) {
            Log::info('Redirecting to admin dashboard', ['user_id' => $user->id]);
            return $this->adminDashboard(request());
        } elseif ($user->hasRole('Student')) {
            Log::info('Redirecting to student dashboard', ['user_id' => $user->id]);
            return $this->studentDashboard(request());
        } elseif ($user->hasRole('Lecturer')) {
            Log::info('Redirecting to lecturer dashboard', ['user_id' => $user->id]);
            return $this->lecturerDashboard(request());
        } elseif ($user->hasRole('Exam Office')) {
            Log::info('Redirecting to exam office dashboard', ['user_id' => $user->id]);
            return $this->examOfficeDashboard(request());
        } elseif ($user->hasRole('Faculty Admin')) {
            Log::info('Redirecting to generic faculty admin dashboard', ['user_id' => $user->id]);
            return $this->facultyAdminDashboard(request());
        }

        Log::info('No specific role found, showing default dashboard', [
            'user_id' => $user->id,
            'roles' => $user->getRoleNames()->toArray()
        ]);

        // Default dashboard for users without specific roles
        return $this->defaultDashboard();
    }

    /**
     * Display the admin dashboard with real statistics.
     */
    public function adminDashboard(Request $request)
    {
        $user = $request->user();

        // Ensure permissions are loaded
        $user->load('roles.permissions', 'permissions');
        $allPermissions = $user->getAllPermissions()->pluck('name')->toArray();
        $roles = $user->getRoleNames()->toArray();

        Log::info('Admin dashboard permissions check', [
            'user_id' => $user->id,
            'roles' => $roles,
            'all_permissions_count' => count($allPermissions),
            'all_permissions' => $allPermissions,
            'has_admin_role' => $user->hasRole('Admin'),
            'can_view_admin_dashboard' => $user->can('view admin dashboard'),
        ]);

        // Check if user has permission to view admin dashboard
        if (!$user->can('view admin dashboard') && !$user->hasRole('Admin')) {
            Log::warning('Unauthorized access to admin dashboard', [
                'user_id' => $user->id,
                'roles' => $user->getRoleNames()->toArray(),
                'permissions' => $user->getAllPermissions()->pluck('name')->toArray()
            ]);
            abort(403, 'Unauthorized access to admin dashboard.');
        }

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

            // Active Enrollments Statistics - FIXED to use DB::table
            $activeEnrollments = DB::table('sces_enrollments')->whereNotNull('student_code')->count();
            $enrollmentsLastWeek = DB::table('sces_enrollments')
                                        ->where('created_at', '>=', $previousWeek)
                                        ->where('created_at', '<', $currentMonth)
                                        ->whereNotNull('student_code')
                                        ->count();
            $enrollmentsPreviousWeek = DB::table('sces_enrollments')
                                           ->where('created_at', '>=', $previousWeek->copy()->subWeek())
                                           ->where('created_at', '<', $previousWeek)
                                           ->whereNotNull('student_code')
                                           ->count();

            if ($currentSemester) {
                $activeEnrollments = DB::table('sces_enrollments')
                                          ->where('semester_id', $currentSemester->id)
                                          ->whereNotNull('student_code')
                                          ->count();
                $enrollmentsLastWeek = DB::table('sces_enrollments')
                                            ->where('semester_id', $currentSemester->id)
                                            ->where('created_at', '>=', $previousWeek)
                                            ->where('created_at', '<', $currentMonth)
                                            ->whereNotNull('student_code')
                                            ->count();
                $enrollmentsPreviousWeek = DB::table('sces_enrollments')
                                               ->where('semester_id', $currentSemester->id)
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
                $activeClasses = Unit::whereHas('sces_enrollments', function($query) use ($currentSemester) {
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
                        : 0;

            // Recent Activities using DB::table
            $recentEnrollments = DB::table('sces_enrollments')
                                  ->join('sces_units', 'sces_enrollments.unit_id', '=', 'sces_units.id')
                                  ->leftJoin('users', 'sces_enrollments.student_code', '=', 'users.code')
                                  ->select(
                                      'sces_enrollments.*',
                                      'sces_units.unit_name',
                                      'sces_units.unit_code',
                                      'users.name as student_name'
                                  )
                                  ->whereNotNull('sces_enrollments.student_code')
                                  ->orderBy('sces_enrollments.created_at', 'desc')
                                  ->limit(5)
                                  ->get();

            // System Statistics
            $totalSchools = School::count();
            $totalSemesters = Semester::count();

            // Role-based statistics
            $roleStats = [
                'admins' => User::role('Admin')->count(),
                'students' => User::role('Student')->count(),
                'lecturers' => User::role('Lecturer')->count(),
                'faculty_admins' => User::role('Faculty Admin')->count(),
                'exam_office' => User::role('Exam Office')->count(),
            ];

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
                'roleStats' => $roleStats,
                'recentEnrollments' => $recentEnrollments,
                'userPermissions' => $allPermissions,
                'userRoles' => $roles,
                'isAdmin' => true
            ];

            Log::info('Admin dashboard data generated successfully', [
                'total_users' => $totalUsers,
                'active_enrollments' => $activeEnrollments,
                'active_classes' => $activeClasses,
                'exam_sessions' => $examSessions,
                'current_semester' => $currentSemester ? $currentSemester->name : 'None',
                'user_id' => $user->id
            ]);

            return Inertia::render('Admin/Dashboard', $dashboardData);

        } catch (\Exception $e) {
            Log::error('Error in admin dashboard', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $user->id
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
                'roleStats' => [],
                'recentEnrollments' => [],
                'userPermissions' => [],
                'userRoles' => [],
                'isAdmin' => true,
                'error' => 'Unable to load dashboard data'
            ]);
        }
    }

    /**
     * Display the student dashboard.
     */
    public function studentDashboard(Request $request)
    {
        // Check if user has permission to view student dashboard
        if (!$request->user()->can('view student dashboard') && !$request->user()->hasRole('Student')) {
            abort(403, 'Unauthorized access to student dashboard.');
        }

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
                'userPermissions' => $user ? $user->getAllPermissions()->pluck('name') : [],
                'userRoles' => $user ? $user->getRoleNames() : []
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
            // Find semesters where the student has enrollments - FIXED to use DB::table
            $studentSemesters = DB::table('sces_enrollments')
                        ->where('student_code', $user->code)
                        ->distinct()
                        ->join('semesters', 'sces_enrollments.semester_id', '=', 'semesters.id')
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

            // Get enrolled units for the student in the selected semester - FIXED to use DB::table
            $enrolledUnits = DB::table('sces_enrollments')
                        ->where('student_code', $user->code)
                        ->where('semester_id', $selectedSemester->id)
                        ->join('sces_units', 'sces_enrollments.unit_id', '=', 'sces_units.id')
                        ->leftJoin('schools', 'sces_units.school_id', '=', 'schools.id')
                        ->select('sces_units.*', 'schools.name as school_name')
                        ->distinct('sces_units.id')
                        ->get();

            // Get upcoming exams for the student in the selected semester
            $upcomingExams = ExamTimetable::where('semester_id', $selectedSemester->id)
                        ->whereIn('unit_id', function($query) use ($user) {
                            $query->select('unit_id')
                                  ->from('sces_enrollments')
                                  ->where('student_code', $user->code);
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
            'userPermissions' => $user->getAllPermissions()->pluck('name'),
            'userRoles' => $user->getRoleNames()
        ]);
    }

    /**
     * Display the lecturer dashboard.
     */
    public function lecturerDashboard(Request $request)
    {
        // Check if user has permission to view lecturer dashboard
        if (!$request->user()->can('view lecturer dashboard') && !$request->user()->hasRole('Lecturer')) {
            abort(403, 'Unauthorized access to lecturer dashboard.');
        }

        $user = $request->user();

        if (!$user) {
            return Inertia::render('Lecturer/Dashboard', [
                'error' => 'User profile is incomplete. Please contact an administrator.',
                'currentSemester' => null,
                'lecturerSemesters' => [],
                'unitsBySemester' => [],
                'studentCounts' => [],
                'userPermissions' => [],
                'userRoles' => []
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
            // FIXED: Use DB::table for lecturer enrollments
            $lecturerCode = $user->code;

            // Get all semesters where this lecturer has assignments
            $lecturerSemesters = Semester::whereExists(function($query) use ($lecturerCode) {
                $query->select(DB::raw(1))
                      ->from('sces_enrollments')
                      ->whereColumn('sces_enrollments.semester_id', 'semesters.id')
                      ->where('sces_enrollments.lecturer_code', $lecturerCode);
            })->get();

            // For each semester, get the units assigned to this lecturer
            foreach ($lecturerSemesters as $semester) {
                // Get units for this lecturer in this semester - FIXED to use DB::table
                $units = DB::table('sces_enrollments')
                          ->where('lecturer_code', $lecturerCode)
                          ->where('semester_id', $semester->id)
                          ->join('sces_units', 'sces_enrollments.unit_id', '=', 'sces_units.id')
                          ->leftJoin('schools', 'sces_units.school_id', '=', 'schools.id')
                          ->select('sces_units.*', 'schools.name as school_name')
                          ->distinct('sces_units.id')
                          ->get();

                if ($units->count() > 0) {
                    $unitsBySemester[$semester->id] = [
                        'semester' => $semester,
                        'units' => $units->toArray()
                    ];

                    // Count students for each unit
                    $studentCounts[$semester->id] = [];
                    foreach ($units as $unit) {
                        $studentCounts[$semester->id][$unit->id] = DB::table('sces_enrollments')
                                    ->where('unit_id', $unit->id)
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
                    'enrollment_check' => DB::table('sces_enrollments')->where('lecturer_code', $lecturerCode)->count(),
                    'all_lecturer_codes' => DB::table('sces_enrollments')
                                ->whereNotNull('lecturer_code')
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
            'studentCounts' => $studentCounts,
            'userPermissions' => $user->getAllPermissions()->pluck('name'),
            'userRoles' => $user->getRoleNames()
        ]);
    }

    /**
     * Display the exam office dashboard.
     */
    public function examOfficeDashboard(Request $request)
    {
        // Check if user has permission to view exam office dashboard
        if (!$request->user()->can('view exam office dashboard') && !$request->user()->hasRole('Exam Office')) {
            abort(403, 'Unauthorized access to exam office dashboard.');
        }

        $user = $request->user();
        $currentSemester = Semester::where('is_active', true)->first();

        $examStats = [];
        if ($currentSemester) {
            $examStats = [
                'total_exams' => ExamTimetable::where('semester_id', $currentSemester->id)->count(),
                'upcoming_exams' => ExamTimetable::where('semester_id', $currentSemester->id)
                        ->where('date', '>=', now()->format('Y-m-d'))
                        ->count(),
                'completed_exams' => ExamTimetable::where('semester_id', $currentSemester->id)
                        ->where('date', '<', now()->format('Y-m-d'))
                        ->count(),
            ];
        }

        return Inertia::render('ExamOffice/Dashboard', [
            'currentSemester' => $currentSemester,
            'examStats' => $examStats,
            'userPermissions' => $user->getAllPermissions()->pluck('name'),
            'userRoles' => $user->getRoleNames()
        ]);
    }

    /**
     * Display the faculty admin dashboard.
     */
    public function facultyAdminDashboard(Request $request)
    {
        // Check if user has permission to view faculty admin dashboard
        if (!$request->user()->can('view faculty admin dashboard') && !$request->user()->hasRole('Faculty Admin')) {
            abort(403, 'Unauthorized access to faculty admin dashboard.');
        }

        $user = $request->user();
        $currentSemester = Semester::where('is_active', true)->first();

        $facultyStats = [];
        if ($currentSemester) {
            $facultyStats = [
                'total_lecturers' => User::role('Lecturer')->count(),
                'total_students' => User::role('Student')->count(),
                'active_enrollments' => DB::table('sces_enrollments')
                        ->where('semester_id', $currentSemester->id)
                        ->whereNotNull('student_code')
                        ->count(),
            ];
        }

        return Inertia::render('FacultyAdmin/sces/Dashboard', [
            'currentSemester' => $currentSemester,
            'facultyStats' => $facultyStats,
            'userPermissions' => $user->getAllPermissions()->pluck('name'),
            'userRoles' => $user->getRoleNames()
        ]);
    }

    /**
     * SCES Faculty Dashboard
     */
    public function scesDashboard()
    {
        return $this->facultyDashboard('SCES', 'School of Computing and Engineering Sciences');
    }

    /**
     * SBS Faculty Dashboard
     */
    public function sbsDashboard()
    {
        return $this->facultyDashboard('SBS', 'School of Business Studies');
    }

    /**
     * SLS Faculty Dashboard
     */
    public function slsDashboard()
    {
        return $this->facultyDashboard('SLS', 'School of Legal Studies');
    }

    /**
     * TOURISM Faculty Dashboard
     */
    public function tourismDashboard()
    {
        return $this->facultyDashboard('TOURISM', 'School of Tourism and Hospitality');
    }

    /**
     * SHM Faculty Dashboard
     */
    public function shmDashboard()
    {
        return $this->facultyDashboard('SHM', 'School of Humanities');
    }

    /**
     * SHS Faculty Dashboard
     */
    public function shsDashboard()
    {
        return $this->facultyDashboard('SHS', 'School of Health Sciences');
    }

    /**
     * Generic faculty dashboard method for all schools
     */
    private function facultyDashboard($schoolCode, $schoolName)
    {
        $user = auth()->user();
        
        // Check if user has permission for this specific school
        $requiredPermission = 'view-faculty-dashboard-' . strtolower($schoolCode);
        if (!$user->can($requiredPermission) && !$user->hasRole('Faculty Admin - ' . $schoolCode)) {
            Log::warning('Unauthorized access to faculty dashboard', [
                'user_id' => $user->id,
                'school_code' => $schoolCode,
                'required_permission' => $requiredPermission,
                'user_roles' => $user->getRoleNames()->toArray()
            ]);
            abort(403, 'Unauthorized access to ' . $schoolCode . ' faculty dashboard.');
        }

        // Get current semester
        $currentSemester = Semester::where('is_active', true)->first();
        
        // Get faculty-specific statistics - FIXED to use DB::table for sces_enrollments
        $stats = [
            'totalStudents' => DB::table('sces_enrollments')
                ->join('sces_units', 'sces_enrollments.unit_id', '=', 'sces_units.id')
                ->join('programs', 'sces_units.program_id', '=', 'programs.id')
                ->join('schools', 'programs.school_id', '=', 'schools.id')
                ->where('schools.code', $schoolCode)
                ->whereNotNull('sces_enrollments.student_code')
                ->distinct('sces_enrollments.student_code')
                ->count(),
            
            'totalLecturers' => DB::table('sces_enrollments')
                ->join('sces_units', 'sces_enrollments.unit_id', '=', 'sces_units.id')
                ->join('programs', 'sces_units.program_id', '=', 'programs.id')
                ->join('schools', 'programs.school_id', '=', 'schools.id')
                ->where('schools.code', $schoolCode)
                ->whereNotNull('sces_enrollments.lecturer_code')
                ->distinct('sces_enrollments.lecturer_code')
                ->count(),
            
            'totalUnits' => DB::table('sces_units')
                ->join('programs', 'sces_units.program_id', '=', 'programs.id')
                ->join('schools', 'programs.school_id', '=', 'schools.id')
                ->where('schools.code', $schoolCode)
                ->count(),
            
            'activeEnrollments' => DB::table('sces_enrollments')
                ->join('sces_units', 'sces_enrollments.unit_id', '=', 'sces_units.id')
                ->join('programs', 'sces_units.program_id', '=', 'programs.id')
                ->join('schools', 'programs.school_id', '=', 'schools.id')
                ->where('schools.code', $schoolCode)
                ->where('sces_enrollments.semester_id', $currentSemester?->id)
                ->whereNotNull('sces_enrollments.student_code')
                ->count(),
        ];

        // Get recent activities (you can customize this based on your needs)
        $recentActivities = [
            [
                'id' => 1,
                'type' => 'enrollment',
                'description' => 'New student enrollment approved',
                'created_at' => now()->subHours(2)->toISOString(),
            ],
            [
                'id' => 2,
                'type' => 'lecturer_assignment',
                'description' => 'Lecturer assigned to new unit',
                'created_at' => now()->subHours(5)->toISOString(),
            ],
        ];

        // Get pending approvals (customize based on your approval system)
        $pendingApprovals = [
            'sces_enrollments' => 12,
            'lecturerRequests' => 3,
            'unitChanges' => 5,
        ];

        Log::info('Faculty dashboard accessed', [
            'user_id' => $user->id,
            'school_code' => $schoolCode,
            'school_name' => $schoolName,
            'stats' => $stats
        ]);

        return Inertia::render('FacultyAdmin/sces/Dashboard', [
            'schoolCode' => $schoolCode,
            'schoolName' => $schoolName,
            'currentSemester' => $currentSemester,
            'statistics' => [
                'totalStudents' => [
                    'count' => $stats['totalStudents'],
                    'growthRate' => 8.5,
                    'period' => 'vs last semester'
                ],
                'totalLecturers' => [
                    'count' => $stats['totalLecturers'],
                    'growthRate' => 12.3,
                    'period' => 'vs last year'
                ],
                'totalUnits' => [
                    'count' => $stats['totalUnits'],
                    'growthRate' => 5.2,
                    'period' => 'vs last semester'
                ],
                'activeEnrollments' => [
                    'count' => $stats['activeEnrollments'],
                    'growthRate' => 15.7,
                    'period' => 'vs last semester'
                ],
            ],
            'facultyInfo' => [
                'name' => $schoolName,
                'code' => $schoolCode,
                'totalPrograms' => 8, // You can make this dynamic
                'totalClasses' => 45,  // You can make this dynamic
            ],
            'pendingApprovals' => $pendingApprovals,
            'recentActivities' => $recentActivities,
            'userPermissions' => $user->getAllPermissions()->pluck('name'),
            'userRoles' => $user->getRoleNames()
        ]);
    }

    /**
     * Default dashboard for users without specific roles
     */
    private function defaultDashboard()
    {
        $user = auth()->user();

        Log::info('Showing default dashboard', [
            'user_id' => $user->id,
            'roles' => $user->getRoleNames()->toArray(),
            'permissions' => $user->getAllPermissions()->pluck('name')->toArray()
        ]);

        return Inertia::render('Dashboard', [
            'message' => 'Welcome to the Timetabling System',
            'userPermissions' => $user->getAllPermissions()->pluck('name'),
            'userRoles' => $user->getRoleNames(),
            'debugInfo' => [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'roles' => $user->getRoleNames()->toArray(),
                'permissions' => $user->getAllPermissions()->pluck('name')->toArray()
            ]
        ]);
    }

    /**
     * Get user dashboard based on role (API endpoint)
     */
    public function getUserDashboard(Request $request)
    {
        $user = $request->user();
        $dashboardRoute = 'dashboard';

        // Check for Faculty Admin roles first (school-specific)
        $roles = $user->getRoleNames();
        foreach ($roles as $role) {
            if (str_starts_with($role, 'Faculty Admin - ')) {
                $faculty = str_replace('Faculty Admin - ', '', $role);
                $schoolRoute = match($faculty) {
                    'SCES' => 'faculty.dashboard.sces',
                    'SBS' => 'faculty.dashboard.sbs',
                    'SLS' => 'faculty.dashboard.sls',
                    'TOURISM' => 'faculty.dashboard.tourism',
                    'SHM' => 'faculty.dashboard.shm',
                    'SHS' => 'faculty.dashboard.shs',
                    default => null
                };
                
                if ($schoolRoute) {
                    $dashboardRoute = $schoolRoute;
                    break;
                }
            }
        }

        // Fallback to generic role checks
        if ($dashboardRoute === 'dashboard') {
            if ($user->hasRole('Admin')) {
                $dashboardRoute = 'admin.dashboard';
            } elseif ($user->hasRole('Student')) {
                $dashboardRoute = 'student.dashboard';
            } elseif ($user->hasRole('Lecturer')) {
                $dashboardRoute = 'lecturer.dashboard';
            } elseif ($user->hasRole('Exam Office')) {
                $dashboardRoute = 'exam-office.dashboard';
            } elseif ($user->hasRole('Faculty Admin')) {
                $dashboardRoute = 'faculty-admin.dashboard';
            }
        }

        return response()->json([
            'dashboard_route' => $dashboardRoute,
            'user_roles' => $user->getRoleNames(),
            'user_permissions' => $user->getAllPermissions()->pluck('name')
        ]);
    }
}