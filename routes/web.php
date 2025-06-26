<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\AutoGenerateTimetableController;
use App\Http\Controllers\AdvancedCSPSolverController;

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EnrollmentController;
use App\Http\Controllers\FacultyController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LecturerAssignmentController;
use App\Http\Controllers\LecturerController;
use App\Http\Controllers\MailController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\ProgramController;
use App\Http\Controllers\ProgramGroupController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SemesterController;
use App\Http\Controllers\SemesterUnitController;
use App\Http\Controllers\Settings;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\StudentDashboardController;
use App\Http\Controllers\StudentEnrollmentController;
use App\Http\Controllers\TimeSlotController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserGuideController;
use App\Http\Controllers\UserSignatureController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\ClassController;
use App\Http\Controllers\ClassroomController;
use App\Http\Controllers\ClassTimetableController;
use App\Http\Controllers\ClassTimeSlotController;
use App\Http\Controllers\ExamOfficeController;
use App\Http\Controllers\ExamroomController;
use App\Http\Controllers\ExamTimetableController;
use App\Http\Controllers\StaffDashboardController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\SchoolController;
use Carbon\Carbon;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\PortalPreviewController;
use Illuminate\Http\Request;

// Load module routes
$moduleRoutes = glob(base_path('Modules/*/routes/web.php'));
foreach ($moduleRoutes as $routeFile) {
    if (file_exists($routeFile)) {
        require $routeFile;
    }
}

// ===================================================================
// PUBLIC ROUTES (No Authentication Required)
// ===================================================================

// Debug routes for develtment
Route::get('/test-login', function () {
    return Inertia::render('Auth/Login', [
        'canLogin' => true,
        'canResetPassword' => true,
        'status' => 'Testing new login component',
    ]);
});

Route::any('/debug', function (Request $request) {
    \Log::info('Request received', [
        'method' => $request->method(),
        'uri' => $request->path(),
    ]);
    return response()->json(['message' => 'Debug route']);
});

// Authentication routes
require __DIR__.'/auth.php';

// ===================================================================
// AUTHENTICATED ROUTES
// ===================================================================

Route::middleware(['auth'])->group(function () {

    // ===============================================================
    // CORE AUTHENTICATION & USER ROUTES
    // ===============================================================
    
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::get('/user/roles-permissions', [UserController::class, 'getUserRolesAndPermissions'])->name('user.roles-permissions');

    // ===============================================================
    // DASHBOARD ROUTES
    // ===============================================================
    
    Route::get('/dashboard', function () {
        $user = auth()->user();
        
        if ($user->hasRole('Admin')) {
            return redirect()->route('admin.dashboard');
        } elseif ($user->hasRole('Exam office')) {
            return redirect()->route('exam-office.dashboard');
        } elseif ($user->hasRole('Faculty Admin')) {
            return redirect()->route('faculty-admin.dashboard');
        } elseif ($user->hasRole('Lecturer')) {
            return redirect()->route('lecturer.dashboard');
        } elseif ($user->hasRole('Student')) {
            return redirect()->route('student.dashboard');
        }
        
        return Inertia::render('Dashboard');
    })->name('dashboard');

    // ===============================================================
    // API ROUTES FOR FRONTEND FUNCTIONALITY
    // ===============================================================
    
    // ✅ CRITICAL: Units by class and semester (Fixed route for enrollment)
    Route::get('/units/by-class-and-semester', [EnrollmentController::class, 'getUnitsByClassAndSemester'])
        ->name('units.by-class-and-semester');
    Route::post('/units/by-class-and-semester', [EnrollmentController::class, 'getUnitsByClassAndSemester'])
        ->name('units.by-class-and-semester.post');

    // API routes for cascading dropdowns
    Route::prefix('api')->group(function () {
        Route::get('/semesters/{semesterId}/classes', [ClassTimetableController::class, 'getClassesBySemester'])
            ->name('api.classes.by-semester');
        Route::get('/semesters/{semesterId}/classes/{classId}/units', [ClassTimetableController::class, 'getUnitsByClassAndSemester'])
            ->name('api.units.by-class-and-semester');
        Route::match(['GET', 'POST'], '/units/by-group-or-class', [ClassTimetableController::class, 'getUnitsByGroupOrClass'])
            ->name('api.units.by-group-or-class');
        Route::get('/classes/{classId}/groups', [ClassTimetableController::class, 'getGroupsByClass'])
            ->name('api.groups.by-class');
        Route::match(['GET', 'POST'], '/units/by-class', [ClassTimetableController::class, 'getUnitsByClass'])
            ->name('api.units.by-class');
        
        // Auto-generate timetable API routes
        Route::get('/auto-generate/classes', [AutoGenerateTimetableController::class, 'getClassesByProgramAndSemester'])
            ->name('api.auto-generate.classes');
        Route::get('/auto-generate/groups', [AutoGenerateTimetableController::class, 'getGroupsByClass'])
            ->name('api.auto-generate.groups');
        Route::get('/auto-generate/timetable-data', [AutoGenerateTimetableController::class, 'getTimetableData'])
            ->name('api.auto-generate.timetable-data');
        
        // Exam timetable API routes
        Route::get('/examtimetable/semester/{semester}/classes', [ExamTimetableController::class, 'getClassesBySemester'])
            ->name('api.examtimetable.classes');
        Route::post('/examtimetable/units', [ExamTimetableController::class, 'getUnitsByClassAndSemesterForExam'])
            ->name('api.examtimetable.units');
        Route::get('/lecturer-for-unit/{unitId}/{semesterId}', [ExamTimetableController::class, 'getLecturerForUnit'])
            ->name('api.lecturer-for-unit');
            
        // Enrollment API routes
        Route::get('/enrollments/student/{studentCode}', [EnrollmentController::class, 'getEnrollmentsByStudent']);
        Route::get('/enrollments/unit/{unitId}/students', [EnrollmentController::class, 'getStudentsByUnit']);
        Route::get('/units/class-semester', [EnrollmentController::class, 'getUnitsByClassAndSemester']);
    });

    // ===============================================================
    // PROFILE ROUTES
    // ===============================================================
    
    Route::prefix('profile')->group(function () {
        Route::get('/', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/', [ProfileController::class, 'update'])->name('profile.update');
        Route::delete('/', [ProfileController::class, 'destroy'])->name('profile.destroy');
    });

    // ===============================================================
    // ADMIN MANAGEMENT ROUTES
    // ===============================================================
    
    // Users management
    Route::middleware(['permission:manage-users'])->group(function () {
        Route::resource('users', UserController::class);
        Route::get('/users/{user}/edit-role', [UserController::class, 'editRole'])->name('users.editRole');
        Route::put('/users/{user}/role', [UserController::class, 'updateRole'])->name('users.updateRole');
    });

    // Schools management
    Route::middleware(['permission:manage-schools'])->group(function () {
        Route::resource('schools', SchoolController::class);
        Route::get('/schools/{school}/dashboard', [SchoolController::class, 'dashboard'])->name('schools.dashboard');
    });

    // Programs management
    Route::middleware(['permission:manage-programs'])->group(function () {
        Route::resource('programs', ProgramController::class);
        Route::get('/schools/sces/bbit/programs', [ProgramController::class, 'index'])->name('programs.index.alt');
    });

    // Units management
    Route::middleware(['permission:manage-units'])->group(function () {
        Route::resource('units', UnitController::class);
        Route::get('/schools/sces/bbit/units', [UnitController::class, 'index'])->name('units.index.alt');
    });

    // Semesters management
    Route::middleware(['permission:manage-semesters'])->group(function () {
        Route::resource('semesters', SemesterController::class);
        Route::get('/schools/sces/bbit/semesters', [SemesterController::class, 'index'])->name('semesters.index.alt');
    });

    // Classes management
    Route::resource('classes', ClassController::class);
    Route::resource('/schools/sces/bbit/classes', ClassController::class, ['as' => 'schools.sces.bbit']);

    // Groups management
    Route::resource('groups', GroupController::class);
    Route::resource('schools/sces/bbit/groups', GroupController::class, ['as' => 'schools.sces.bbit']);

    // Student Routes - Remove duplicates and use only StudentEnrollmentController
Route::middleware(['role:Student'])->group(function () {
    Route::get('/student', [DashboardController::class, 'studentDashboard'])->name('student.dashboard');
    
    // Student enrollment routes
    Route::get('/enroll', [StudentEnrollmentController::class, 'showEnrollmentForm'])->name('student.enrollment-form');
    Route::post('/enroll', [StudentEnrollmentController::class, 'enroll'])->name('student.enroll');
       // CORRECT - Match the exact permission name
Route::get('/enrollments', [StudentEnrollmentController::class, 'viewEnrollments'])
    ->middleware(['auth', 'permission:view-enrollments']); // ✅ Correct permission name
    
    
    // Student timetables
    Route::get('/student/timetable', [ClassTimetableController::class, 'studentTimetable'])->name('student.timetable');
    Route::get('/my-classes', [ClassTimetableController::class, 'studentTimetable'])->name('student.classes');
    Route::get('/my-exams', [ExamTimetableController::class, 'studentExamTimetable'])->name('student.exams');
    Route::get('/my-exams/{examtimetable}', [ExamTimetableController::class, 'viewStudentExamDetails'])->name('student.exam.details');
    
    // Student downloads
    Route::get('/my-timetable/download', [ClassTimetableController::class, 'downloadStudentClassTimetable'])->name('student.classes.download');
    Route::get('/my-exams/download', [ExamTimetableController::class, 'downloadStudentTimetable'])->name('student.exams.download');
    Route::get('/student/timetable/download', [ClassTimetableController::class, 'downloadPDF'])->name('student.timetable.download');
});

// Add this route for the units API call that your frontend makes
Route::get('/units/by-class-and-semester', [EnrollmentController::class, 'getUnitsByClassAndSemester'])
    ->middleware('auth')
    ->name('units.by-class-and-semester');
    // ===============================================================
    // ENROLLMENT MANAGEMENT (Admin routes - FIXED)
    // ===============================================================
    
    Route::middleware(['permission:manage-enrollments'])->group(function () {
        // Admin enrollment management routes
        Route::get('/enrollments', [EnrollmentController::class, 'index'])->name('enrollments.index');
        Route::get('/enrollments/create', [EnrollmentController::class, 'create'])->name('enrollments.create');
        
        // CRITICAL FIX: Use adminStore for admin enrollment, not store
        Route::post('/enrollments', [EnrollmentController::class, 'adminStore'])->name('enrollments.store');
        
        Route::get('/enrollments/{enrollment}/edit', [EnrollmentController::class, 'edit'])->name('enrollments.edit');
        Route::put('/enrollments/{enrollment}', [EnrollmentController::class, 'update'])->name('enrollments.update');
        Route::delete('/enrollments/{enrollment}', [EnrollmentController::class, 'destroy'])->name('enrollments.destroy');
        
        // Alternative routes for schools path
        Route::get('/schools/sces/bbit/enrollments', [EnrollmentController::class, 'index'])->name('enrollments.index.alt');
        
        // Bulk operations
        Route::post('/enrollments/bulk', [EnrollmentController::class, 'bulkEnroll'])->name('enrollments.bulk');
        Route::post('/enrollments/bulk-delete', [EnrollmentController::class, 'bulkDelete'])->name('enrollments.bulk-delete');

        // Lecturer assignments
        Route::post('/assign-lecturers', [EnrollmentController::class, 'assignLecturers'])->name('assign.lecturers');
        Route::delete('/assign-lecturers/{unitId}', [EnrollmentController::class, 'destroyLecturerAssignment'])->name('assign-lecturers.destroy');
        Route::get('/lecturer-units/{lecturerId}', [EnrollmentController::class, 'getLecturerUnits'])->name('lecturer.units');
        
        // Unit assignments
        Route::post('/assign-unit', [EnrollmentController::class, 'assignUnitToLecturer'])->name('assign.unit');
    });

    // ===============================================================
    // SEMESTER UNIT MANAGEMENT
    // ===============================================================
    
    Route::middleware(['permission:manage-semester-units'])->group(function () {
        Route::resource('semester-units', SemesterUnitController::class, ['except' => ['show', 'edit']]);
        Route::get('/schools/sces/bbit/semester-units', [SemesterUnitController::class, 'index'])->name('semester-units.index.alt');
        Route::post('/semester-units/bulk-assign', [SemesterUnitController::class, 'bulkAssign'])->name('semester-units.bulk-assign');
        Route::put('/semester-units/{semester}/units/{unit}', [SemesterUnitController::class, 'updateUnit'])->name('semester-units.update-unit');
        Route::delete('/semester-units/{semester}/units/{unit}', [SemesterUnitController::class, 'deleteUnit'])->name('semester-units.delete-unit');
        Route::delete('/semester-units/{semesterId}/units/{unitId}', [SemesterUnitController::class, 'deleteUnit']);
    });

    // ===============================================================
    // CLASSROOM MANAGEMENT
    // ===============================================================
    
    Route::middleware(['permission:manage-classrooms'])->group(function () {
        Route::resource('classrooms', ClassroomController::class);
        Route::get('/schools/sces/bbit/classrooms', [ClassroomController::class, 'index'])->name('classrooms.index.alt');
    });

    // ===============================================================
    // TIMETABLE MANAGEMENT
    // ===============================================================
    
    // Class Timetables
    Route::middleware(['permission:manage-classtimetables'])->group(function () {
        Route::resource('classtimetables', ClassTimetableController::class, ['as' => 'classtimetable']);
        Route::get('/schools/sces/bbit/classtimetable', [ClassTimetableController::class, 'index'])->name('classtimetable.index.alt');
        Route::get('/classtimetable/download', [ClassTimetableController::class, 'downloadTimetable'])->name('classtimetable.download');
        Route::post('/api/resolve-conflicts', [ClassTimetableController::class, 'resolveConflicts']);
        Route::put('/classtimetable/{id}', [ClassTimetableController::class, 'update'])->name('classtimetable.update');

        // ✅ ADD CSP SOLVER ROUTES HERE (INSIDE THE MIDDLEWARE GROUP):
        Route::post('/optimize-schedule', [AdvancedCSPSolverController::class, 'optimize'])->name('csp.optimize-schedule');
        Route::post('/generate-optimal-schedule', [AdvancedCSPSolverController::class, 'generateOptimalSchedule'])->name('csp.generate-schedule');
        Route::post('/detect-conflicts', [ClassTimetableController::class, 'detectConflicts'])->name('csp.detect-conflicts');
        Route::post('/resolve-conflicts', [ClassTimetableController::class, 'resolveConflicts'])->name('csp.resolve-conflicts');
    });

    // Exam Timetables
    Route::middleware(['permission:manage-examtimetables'])->group(function () {
        Route::get('/schools/sces/bbit/examtimetable', [ExamTimetableController::class, 'index'])->name('examtimetable.index');
        Route::get('/examtimetable/create', [ExamTimetableController::class, 'create'])->name('examtimetable.create');
        Route::post('/examtimetable', [ExamTimetableController::class, 'store'])->name('examtimetable.store');
        Route::get('/examtimetable/{id}', [ExamTimetableController::class, 'show'])->name('examtimetable.show');
        Route::get('/examtimetable/{id}/edit', [ExamTimetableController::class, 'edit'])->name('examtimetable.edit');
        Route::put('/examtimetable/{id}', [ExamTimetableController::class, 'update'])->name('examtimetable.update');
        Route::delete('/examtimetable/{id}', [ExamTimetableController::class, 'destroy'])->name('examtimetable.destroy');
        Route::post('/examtimetable/{id}/ajax-delete', [ExamTimetableController::class, 'ajaxDestroy'])->name('examtimetable.ajax-destroy');
    });

    // Auto-Generate Timetable
    Route::middleware(['permission:manage-classtimetables'])->group(function () {
        Route::get('/auto-generate-timetable', [AutoGenerateTimetableController::class, 'index'])->name('auto-generate-timetable.index');
        Route::post('/auto-generate-timetable', [AutoGenerateTimetableController::class, 'autoGenerate'])->name('auto-generate-timetable.generate');
        Route::get('/auto-generate-timetable/download', [AutoGenerateTimetableController::class, 'downloadPDF'])->name('auto-generate-timetable.download');
    });

    // ===============================================================
    // EXAM ROOMS & TIME SLOTS
    // ===============================================================
    
    Route::middleware(['permission:manage-examrooms'])->group(function () {
        Route::resource('examrooms', ExamroomController::class);
        Route::get('/schools/sces/bbit/examrooms', [ExamroomController::class, 'index'])->name('examrooms.index.alt');
    });

    Route::middleware(['permission:manage-timeslots'])->group(function () {
        Route::get('/schools/sces/bbit/timeslots', [TimeSlotController::class, 'index'])->name('timeslots.index');
        Route::post('/timeslots', [TimeSlotController::class, 'store'])->name('timeslots.store');
        Route::put('/timeslots/{timeSlot}', [TimeSlotController::class, 'update'])->name('timeslots.update');
        Route::delete('/timeslots/{timeSlot}', [TimeSlotController::class, 'destroy'])->name('timeslots.destroy');
    });

    Route::middleware(['permission:manage-classtimeslots'])->group(function () {
        Route::get('/schools/sces/bbit/classtimeslot', [ClassTimeSlotController::class, 'index'])->name('classtimeslot.index');
        Route::post('/classtimeslot', [ClassTimeSlotController::class, 'store'])->name('classtimeslot.store');
        Route::put('/classtimeslot/{classtimeSlot}', [ClassTimeSlotController::class, 'update'])->name('classtimeslot.update');
        Route::delete('/classtimeslot/{classtimeSlot}', [ClassTimeSlotController::class, 'destroy'])->name('classtimeslot.destroy');
    });

    // ===============================================================
    // TIMETABLE PROCESSING
    // ===============================================================
    
    Route::middleware(['permission:process-examtimetables'])->group(function () {
        Route::get('/process-examtimetable', [ExamTimetableController::class, 'processForm'])->name('examtimetables.process-form');
        Route::post('/process-examtimetables', [ExamTimetableController::class, 'process'])->name('examtimetables.process');
        Route::get('/solve-exam-conflicts', [ExamTimetableController::class, 'solveConflicts'])->name('examtimetables.conflicts');
    });

    Route::middleware(['permission:process-classtimetables'])->group(function () {
        Route::post('/process-classtimetables', [ClassTimetableController::class, 'process'])->name('classtimetables.process');
        Route::get('/solve-class-conflicts', [ClassTimetableController::class, 'solveConflicts'])->name('classtimetables.conflicts');
    });

    // ===============================================================
    // DOWNLOAD ROUTES
    // ===============================================================
    
    // Admin downloads
    Route::middleware(['permission:download-examtimetables'])->group(function () {
        Route::get('/download-examtimetables', [ExamTimetableController::class, 'downloadPDF'])->name('examtimetable.download');
    });

    Route::middleware(['permission:download-classtimetables'])->group(function () {
        Route::get('/download-classtimetables', [ClassTimetableController::class, 'downloadPDF'])->name('classtimetable.download');
    });

    // Faculty downloads
    Route::middleware(['permission:download-faculty-examtimetables'])->group(function () {
        Route::get('/examtimetable/faculty/download', [ExamTimetableController::class, 'downloadFacultyTimetable'])->name('examtimetable.faculty.download');
    });

    // Lecturer downloads
    Route::middleware(['permission:download-own-examtimetables'])->group(function () {
        Route::get('/examtimetable/lecturer/download', [ExamTimetableController::class, 'downloadLecturerTimetable'])->name('examtimetable.lecturer.download');
    });

    Route::middleware(['permission:view-own-examtimetables'])->group(function () {
        Route::get('/examtimetable/lecturer', [ExamTimetableController::class, 'viewLecturerTimetable'])->name('examtimetable.lecturer');
    });

    // ===============================================================
    // LECTURER ROUTES
    // ===============================================================
    
    Route::middleware(['role:Lecturer'])->group(function () {
        Route::get('/lecturer/dashboard', [DashboardController::class, 'lecturerDashboard'])->name('lecturer.dashboard');
        Route::get('/lecturer/my-classes', [LecturerController::class, 'myClasses'])->name('lecturer.my-classes');
        Route::get('/lecturer/my-classes/{unitId}/students', [LecturerController::class, 'classStudents'])->name('lecturer.class-students');
        Route::get('/lecturer/class-timetable', [LecturerController::class, 'viewClassTimetable'])->name('lecturer.class-timetable');
        Route::get('/lecturer/exam-supervision', [LecturerController::class, 'examSupervision'])->name('lecturer.exam-supervision');
        Route::get('/lecturer/profile', [LecturerController::class, 'profile'])->name('lecturer.profile');
        Route::get('/lecturer/timetable/download', [ExamTimetableController::class, 'downloadLecturerTimetable'])->name('lecturer.timetable.download');
    });

    // ===============================================================
    // FACULTY ADMIN ROUTES
    // ===============================================================
    
    Route::middleware(['role:Faculty Admin'])->group(function () {
        Route::get('/faculty-admin', fn() => Inertia::render('FacultyAdmin/Dashboard'))->name('faculty-admin.dashboard');
        
        Route::middleware(['permission:manage-faculty-users'])->group(function () {
            Route::get('/faculty/lecturers', [LecturerController::class, 'index'])->name('faculty.lecturers');
            Route::get('/faculty/students', [StudentController::class, 'index'])->name('faculty.students');
        });
        
        Route::middleware(['permission:manage-faculty-enrollments'])->group(function () {
            Route::get('/faculty/enrollments', [EnrollmentController::class, 'facultyEnrollments'])->name('faculty.enrollments');
        });
        
        Route::middleware(['permission:download-faculty-timetable'])->group(function () {
            Route::get('/faculty/timetable/download', [ExamTimetableController::class, 'downloadFacultyTimetable'])->name('faculty.timetable.download');
        });
    });

    // ===============================================================
    // EXAM OFFICE ROUTES
    // ===============================================================
    
    Route::middleware(['role:Exam office'])->group(function () {
        Route::get('/exam-office', fn() => Inertia::render('ExamOffice/Dashboard'))->name('exam-office.dashboard');
        Route::get('/exam-office/dashboard', [ExamOfficeController::class, 'dashboard'])->name('exam-office.dashboard.alt');
    });

    // ===============================================================
    // PROGRAM GROUP MANAGEMENT
    // ===============================================================
    
    Route::middleware(['permission:manage-program-groups'])->group(function () {
        Route::resource('program-groups', ProgramGroupController::class, ['except' => ['show', 'edit']]);
    });

    // ===============================================================
    // LECTURER ASSIGNMENTS
    // ===============================================================
    
    Route::delete('/lecturer-assignments/{unitId}/{lecturerCode}', [LecturerAssignmentController::class, 'destroy'])
        ->name('lecturer-assignments.destroy');

    // ===============================================================
    // NOTIFICATIONS
    // ===============================================================
    
    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index'])->name('notifications.index');
        Route::post('/send', [NotificationController::class, 'sendReminders'])->name('notifications.send');
        Route::get('/preview/{exam}', [NotificationController::class, 'previewNotifications'])->name('notifications.preview');
        Route::get('/my-notifications', [NotificationController::class, 'userNotifications'])->name('notifications.user');
        Route::post('/{id}/mark-read', [NotificationController::class, 'markAsRead'])->name('notifications.mark-read');
        Route::post('/mark-all-read', [NotificationController::class, 'markAllAsRead'])->name('notifications.mark-all-read');
        Route::get('/logs', [NotificationController::class, 'filterLogs'])
            ->name('notifications.logs')
            ->middleware('can:view-notification-logs');
    });

    // ===============================================================
    // SETTINGS
    // ===============================================================
    
    Route::middleware(['permission:manage-settings'])->group(function () {
        Route::get('/settings', fn() => Inertia::render('Admin/Settings'))->name('settings.index');
        Route::post('/settings', [AdminController::class, 'updateSettings'])->name('settings.update');
    });

    // ===============================================================
    // MISCELLANEOUS ROUTES
    // ===============================================================
    
    // Portal Access Guide
    Route::get('/portal-access-guide', [PortalPreviewController::class, 'accessGuide'])->name('portal-access-guide');

    // Debug routes
    Route::get('/debug/units-for-class/{semester_id}/{class_id}', function ($semesterId, $classId) {
        $unitColumns = Schema::getColumnListing('units');
        $units = DB::table('units')->where('semester_id', $semesterId)->where('class_id', $classId)->get();
        $hasUnitClassesTable = Schema::hasTable('unit_classes');
        $unitClassMappings = [];
        if ($hasUnitClassesTable) {
            $unitClassMappings = DB::table('unit_classes')->where('class_id', $classId)->get();
        }
        
        return [
            'semester_id' => $semesterId,
            'class_id' => $classId,
            'unit_table_columns' => $unitColumns,
            'units_count' => count($units),
            'units' => $units,
            'has_unit_classes_table' => $hasUnitClassesTable,
            'unit_class_mappings' => $unitClassMappings
        ];
    });

    // PDF test route
    Route::get('/test-pdf-debug', function() {
        $user = auth()->user();
        
        try {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML('<h1>Test PDF</h1><p>User: ' . $user->first_name . '</p>');
            $content = $pdf->output();
            
            return response($content)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'attachment; filename="test.pdf"');
                
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()]);
        }
    });
});

// ===================================================================
// ADMIN ROUTES (Admin role bypasses permission checks)
// ===================================================================

Route::middleware(['auth', 'role:Admin'])->group(function () {
    Route::get('/admin', [DashboardController::class, 'adminDashboard'])->name('admin.dashboard');
    Route::resource('roles', RoleController::class);
    Route::resource('permissions', PermissionController::class);
    
    // Admin can access all resources without permission checks
    Route::resource('schools', SchoolController::class, ['as' => 'admin']);
    Route::resource('programs', ProgramController::class, ['as' => 'admin']);
    Route::resource('units', UnitController::class, ['as' => 'admin']);
    Route::resource('semesters', SemesterController::class, ['as' => 'admin']);
    Route::resource('classrooms', ClassroomController::class, ['as' => 'admin']);
    Route::resource('classtimetables', ClassTimetableController::class, ['as' => 'admin']);
    Route::resource('examtimetables', ExamTimetableController::class, ['as' => 'admin']);
    
    // REMOVED: Duplicate enrollment routes that were causing conflicts
    // The enrollment routes are now properly defined in the permission-based section above
});

// ===================================================================
// COMBINED ROLE ROUTES
// ===================================================================

Route::middleware(['auth', 'role:Admin|Exam office'])->group(function () {
    Route::get('/process-examtimetable', [ExamTimetableController::class, 'processForm'])->name('examtimetables.process-form');
    Route::post('/process-examtimetables', [ExamTimetableController::class, 'process'])->name('examtimetables.process');
    Route::get('/solve-exam-conflicts', [ExamTimetableController::class, 'solveConflicts'])->name('examtimetables.conflicts');
});

// ===================================================================
// CATCH-ALL ROUTE (Must be last)
// ===================================================================



Route::get('/{any}', function () {
    return Inertia::render('NotFound');
})->where('any', '.*')->name('not-found');
