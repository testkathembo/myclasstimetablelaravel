<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\User;
use App\Models\Enrollment;
use App\Models\ExamTimetable;
use App\Models\Classroom;
use App\Models\Unit;
use App\Models\Semester;
use App\Models\Faculty;

class DashboardController extends Controller
{
    /**
     * Display a unified dashboard that adapts based on user roles and permissions.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Inertia\Response
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // Get user roles and permissions
        $roles = $user->roles->pluck('name')->toArray();
        $permissions = $user->getAllPermissions()->pluck('name')->toArray();

        // Common stats for all users
        $stats = [
            'currentSemester' => Semester::where('is_active', true)->first(),
        ];

        // Role-specific stats
        if ($user->hasRole('Admin')) {
            $stats = array_merge($stats, $this->getAdminStats());
        } elseif ($user->hasRole('Exam office')) {
            $stats = array_merge($stats, $this->getExamOfficeStats());
        } elseif ($user->hasRole('Faculty Admin')) {
            $stats = array_merge($stats, $this->getFacultyAdminStats($user));
        } elseif ($user->hasRole('Lecturer')) {
            $stats = array_merge($stats, $this->getLecturerStats($user));
        } elseif ($user->hasRole('Student')) {
            $stats = array_merge($stats, $this->getStudentStats($user));
        }

        // Render the unified dashboard with role-based data
        return Inertia::render('Student/Dashboard', [
            'auth' => [
                'user' => $user,
                'roles' => $roles,
                'permissions' => $permissions,
            ],
            'stats' => $stats,
        ]);
    }

    /**
     * Get statistics for Admin users.
     *
     * @return array
     */
    private function getAdminStats()
    {
        return [
            'totalUsers' => User::count(),
            'totalFaculties' => Faculty::count(),
            'totalUnits' => Unit::count(),
            'totalClassrooms' => Classroom::count(),
            'recentUsers' => User::latest()->take(5)->get(),
        ];
    }

    /**
     * Get statistics for Exam Office users.
     *
     * @return array
     */
    private function getExamOfficeStats()
    {
        return [
            'totalTimetables' => ExamTimetable::count(),
            'totalClassrooms' => Classroom::count(),
            'upcomingExams' => ExamTimetable::where('date', '>=', now()) // Use 'date' instead of 'exam_date'
                ->orderBy('date') // Use 'date' instead of 'exam_date'
                ->take(5)
                ->get(),
            'conflicts' => ExamTimetable::where('has_conflict', true)->count(),
        ];
    }

    /**
     * Get statistics for Faculty Admin users.
     *
     * @param  \App\Models\User  $user
     * @return array
     */
    private function getFacultyAdminStats($user)
    {
        $facultyId = $user->faculty_id ?? null;

        return [
            'facultyUnits' => $facultyId ? Unit::where('faculty_id', $facultyId)->count() : 0,
            'facultyLecturers' => $facultyId ? User::role('Lecturer')->where('faculty_id', $facultyId)->count() : 0,
            'facultyStudents' => $facultyId ? User::role('Student')->where('faculty_id', $facultyId)->count() : 0,
            'facultyEnrollments' => $facultyId ? Enrollment::whereHas('unit', function ($query) use ($facultyId) {
                $query->where('faculty_id', $facultyId);
            })->count() : 0,
        ];
    }

    /**
     * Get statistics for Lecturer users.
     *
     * @param  \App\Models\User  $user
     * @return array
     */
    private function getLecturerStats($user)
    {
        return [
            'assignedUnits' => Unit::whereHas('lecturers', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })->count(),
            'upcomingExams' => ExamTimetable::whereHas('unit', function ($query) use ($user) {
                $query->whereHas('lecturers', function ($q) use ($user) {
                    $q->where('user_id', $user->id);
                });
            })
            ->where('date', '>=', now()) // Use 'date' instead of 'exam_date'
            ->orderBy('date') // Use 'date' instead of 'exam_date'
            ->take(5)
            ->get(),
        ];
    }

    /**
     * Get statistics for Student users.
     *
     * @param  \App\Models\User  $user
     * @return array
     */
    private function getStudentStats($user)
    {
        return [
            'enrolledUnits' => Enrollment::where('student_code', $user->code)->count(),
            'upcomingExams' => ExamTimetable::whereHas('unit', function ($query) use ($user) {
                $query->whereHas('enrollments', function ($q) use ($user) {
                    $q->where('student_code', $user->code);
                });
            })
            ->where('date', '>=', now()) // Use 'date' instead of 'exam_date'
            ->orderBy('date') // Use 'date' instead of 'exam_date'
            ->take(5)
            ->get(),
        ];
    }

    /**
 * Display the student dashboard
 */
public function studentDashboard(Request $request)
{
    $user = $request->user();
    
    // Get current semester
    $currentSemester = Semester::where('is_active', true)->first();
    if (!$currentSemester) {
        $currentSemester = Semester::latest()->first();
    }
    
    // Get student's enrolled units for the current semester
    $enrolledUnits = $user->enrolledUnits()
        ->where('semester_id', $currentSemester->id)
        ->with(['faculty'])
        ->get();
        
    // Get upcoming exams
    $upcomingExams = ExamTimetable::whereIn('unit_id', $enrolledUnits->pluck('id')->toArray())
        ->where('date', '>=', now()->format('Y-m-d'))
        ->with(['unit', 'semester'])
        ->orderBy('date')
        ->orderBy('start_time')
        ->take(5)
        ->get();
        
    return Inertia::render('Student/Dashboard', [
        'enrolledUnits' => $enrolledUnits,
        'upcomingExams' => $upcomingExams,
        'currentSemester' => $currentSemester,
    ]);
}
}