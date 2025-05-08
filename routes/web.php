<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\FacultyController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\ClassroomController;
use App\Http\Controllers\SemesterController;
use App\Http\Controllers\EnrollmentController;
use App\Http\Controllers\TimeSlotController;
use App\Http\Controllers\ClassTimeSlotController;
use App\Http\Controllers\ExamTimetableController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\LecturerController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExamOfficeController;
use App\Http\Controllers\ExamroomController;
use App\Http\Controllers\ClassTimetableController;
use App\Http\Controllers\MailController;
use App\Http\Controllers\NotificationController;
// Add other controller imports as needed

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

// ✅ Public routes
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::post('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
require __DIR__.'/auth.php';

// ✅ Authenticated routes
Route::middleware(['auth'])->group(function () {

    // Logout route
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    // Roles and permissions routes
    Route::get('/user/roles-permissions', [UserController::class, 'getUserRolesAndPermissions'])->name('user.roles-permissions');
    


    // Dashboard routes based on role
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
    

    // Profile routes (accessible to all authenticated users)
    Route::prefix('profile')->group(function () {
        Route::get('/', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/', [ProfileController::class, 'update'])->name('profile.update');
        Route::delete('/', [ProfileController::class, 'destroy'])->name('profile.destroy');   
    });

    // Users management
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    Route::get('/users/{user}', [UserController::class, 'show'])->name('users.show');
    Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
    Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    Route::get('/users/{user}/edit-role', [UserController::class, 'editRole'])->name('users.editRole');
    Route::put('/users/{user}/update-role', [UserController::class, 'updateRole'])->name('users.updateRole');

    // Faculties management
    Route::get('/faculties', [FacultyController::class, 'index'])->name('faculties.index');
    Route::post('/faculties', [FacultyController::class, 'store'])->name('faculties.store');
    Route::put('/faculties/{faculty}', [FacultyController::class, 'update'])->name('faculties.update');
    Route::delete('/faculties/{faculty}', [FacultyController::class, 'destroy'])->name('faculties.destroy');

    // Units management
    Route::get('/units', [UnitController::class, 'index'])->name('units.index');
    Route::post('/units', [UnitController::class, 'store'])->name('units.store');
    Route::put('/units/{unit}', [UnitController::class, 'update'])->name('units.update');
    Route::delete('/units/{unit}', [UnitController::class, 'destroy'])->name('units.destroy');

    // Semesters management
    Route::resource('semesters', SemesterController::class);

     // Classrooms management
    Route::get('/semesters/{semester}/edit', [SemesterController::class, 'edit'])->name('semesters.edit');
    Route::match(['PUT', 'PATCH'], '/semesters/{semester}', [SemesterController::class, 'update'])->name('semesters.update');
    Route::delete('/semesters/{semester}', [SemesterController::class, 'destroy'])->name('semesters.destroy');  

     // Classrooms management
     Route::get('/classrooms', [ClassroomController::class, 'index'])->name('classrooms.index');
     Route::get('/classrooms/create', [ClassroomController::class, 'create'])->name('classrooms.create');
     Route::post('/classrooms', [ClassroomController::class, 'store'])->name('classrooms.store');
     Route::get('/classrooms/{classroom}', [ClassroomController::class, 'show'])->name('classrooms.show');
     Route::get('/classrooms/{classroom}/edit', [ClassroomController::class, 'edit'])->name('classrooms.edit');
     Route::put('/classrooms/{classroom}', [ClassroomController::class, 'update'])->name('classrooms.update');
     Route::delete('/classrooms/{classroom}', [ClassroomController::class, 'destroy'])->name('classrooms.destroy');

     // ClassTimetable routes
     Route::middleware(['permission:manage-classtimetables'])->group(function () {
        Route::get('/classtimetable', [ClassTimetableController::class, 'index'])->name('classtimetable.index');
        Route::get('/classtimetable/create', [ClassTimetableController::class, 'create'])->name('classtimetable.create');
        Route::post('/classtimetable', [ClassTimetableController::class, 'store'])->name('classtimetable.store');
        Route::get('/classtimetable/{classtimetable}', [ClassTimetableController::class, 'show'])->name('classtimetable.show');
        Route::get('/classtimetable/{classtimetable}/edit', [ClassTimetableController::class, 'edit'])->name('classtimetable.edit');
        Route::put('/classtimetable/{classtimetable}', [ClassTimetableController::class, 'update'])->name('classtimetable.update');
        Route::delete('/classtimetable/{classtimetable}', [ClassTimetableController::class, 'destroy'])->name('classtimetable.destroy');
        Route::get('/classtimetable/download', [ClassTimetableController::class, 'downloadTimetable'])->name('classtimetable.download');
    });
    
    // Exams Rooms
    Route::get('/examrooms', [ExamroomController::class, 'index'])->name('examrooms.index');
    Route::get('/examrooms/create', [ExamroomController::class, 'create'])->name('examrooms.create');
    Route::post('/examrooms', [ExamroomController::class, 'store'])->name('examrooms.store');
    Route::get('/examrooms/{examroom}', [ExamroomController::class, 'show'])->name('examrooms.show');
    Route::get('/examrooms/{examroom}/edit', [ExamroomController::class, 'edit'])->name('examrooms.edit');
    Route::put('/examrooms/{examroom}', [ExamroomController::class, 'update'])->name('examrooms.update');
    Route::delete('/examrooms/{examroom}', [ExamroomController::class, 'destroy'])->name('examrooms.destroy');
     
    Route::get('/timeslots', [TimeSlotController::class, 'index'])->name('timeslots.index');
    Route::post('/timeslots', [TimeSlotController::class, 'store'])->name('timeslots.store');
    Route::put('/timeslots/{timeSlot}', [TimeSlotController::class, 'update'])->name('timeslots.update');
    Route::delete('/timeslots/{timeSlot}', [TimeSlotController::class, 'destroy'])->name('timeslots.destroy');

    Route::get('/classtimeslot', [ClassTimeSlotController::class, 'index'])->name('classtimeslot.index');
    Route::post('/classtimeslot', [ClassTimeSlotController::class, 'store'])->name('classtimeslot.store');
    Route::put('/classtimeslot/{classtimeSlot}', [ClassTimeSlotController::class, 'update'])->name('classtimeslot.update');
    Route::delete('/classtimeslot/{classtimeSlot}', [ClassTimeSlotController::class, 'destroy'])->name('classtimeslot.destroy');
    
    // ExamTimetable routes - UPDATED
    Route::middleware(['permission:manage-examtimetables'])->group(function () {
        Route::get('/examtimetable', [ExamTimetableController::class, 'index'])->name('examtimetable.index');
        Route::get('/examtimetable/create', [ExamTimetableController::class, 'create'])->name('examtimetable.create');
        Route::post('/examtimetable', [ExamTimetableController::class, 'store'])->name('examtimetable.store');
        Route::get('/examtimetable/{id}', [ExamTimetableController::class, 'show'])->name('examtimetable.show');
        Route::get('/examtimetable/{id}/edit', [ExamTimetableController::class, 'edit'])->name('examtimetable.edit');
        Route::put('/examtimetable/{id}', [ExamTimetableController::class, 'update'])->name('examtimetable.update');
        Route::delete('/examtimetable/{id}', [ExamTimetableController::class, 'destroy'])->name('examtimetable.destroy');
        // Add AJAX delete route as fallback
        Route::post('/examtimetable/{id}/ajax-delete', [ExamTimetableController::class, 'ajaxDestroy'])->name('examtimetable.ajax-destroy');
        // Add API endpoint for lecturer information
        Route::get('/lecturer-for-unit/{unitId}/{semesterId}', [ExamTimetableController::class, 'getLecturerForUnit'])->name('api.lecturer-for-unit');
    });

    // Process Timetable Route - UPDATED
    Route::middleware(['permission:process-examtimetables'])->group(function () {
        Route::post('/process-examtimetables', [ExamTimetableController::class, 'process'])->name('examtimetables.process');
        Route::get('/solve-exam-conflicts', [ExamTimetableController::class, 'solveConflicts'])->name('examtimetables.conflicts');
    });

    // Download exam timetable route - MAIN ROUTE FOR PDF DOWNLOAD
    Route::get('/download-examtimetables', [ExamTimetableController::class, 'downloadPDF'])
        ->middleware(['permission:download-examtimetables'])
        ->name('examtimetable.download');

     // Download class timetable route - MAIN ROUTE FOR PDF DOWNLOAD
     Route::get('/download-classtimetables', [ClassTimetableController::class, 'downloadPDF'])
     ->middleware(['permission:download-classtimetables'])
     ->name('classtimetable.download');
});

// Student download route
Route::get('/my-class/download', [ClassTimetableController::class, 'downloadStudentClassTimetable'])
    ->name('student.classes.download');

// Student download route
Route::get('/my-exams/download', [ExamTimetableController::class, 'downloadStudentTimetable'])
    ->name('student.exams.download');

// Lecturer download route
Route::middleware(['auth', 'permission:download-own-timetable'])->group(function () {
    Route::get('/lecturer/timetable/download', [ExamTimetableController::class, 'downloadLecturerTimetable'])
        ->name('lecturer.timetable.download');
});

// Admin Routes - Admin role bypasses permission checks
Route::middleware(['auth', 'role:Admin'])->group(function () {
    // Keep the original dashboard
    Route::get('/admin', fn() => Inertia::render('Admin/Dashboard'))->name('admin.dashboard');

    // the unified dashboard (uncomment to use)
    //Route::get('/admin', [DashboardController::class, 'dashboard'])->name('admin.dashboard');
    
    // Roles and Permissions management
    Route::resource('roles', RoleController::class)->except(['show']);
    Route::resource('permissions', PermissionController::class)->except(['show']);

    // Semesters management
    Route::get('/semesters', [SemesterController::class, 'index'])->name('semesters.index');
    Route::get('/semesters/create', [SemesterController::class, 'create'])->name('semesters.create');
    Route::post('/semesters', [SemesterController::class, 'store'])->name('semesters.store');
    Route::get('/semesters/{semester}', [SemesterController::class, 'show'])->name('semesters.show');
    Route::get('/semesters/{semester}/edit', [SemesterController::class, 'edit'])->name('semesters.edit');
    Route::put('/semesters/{semester}', [SemesterController::class, 'update'])->name('semesters.update');
    Route::delete('/semesters/{semester}', [SemesterController::class, 'destroy'])->name('semesters.destroy');

    // Examrooms management
    Route::get('/examrooms', [ExamroomController::class, 'index'])->name('examrooms.index');
    Route::get('/examrooms/create', [ExamroomController::class, 'create'])->name('examrooms.create');
    Route::post('/examrooms', [ExamroomController::class, 'store'])->name('examrooms.store');
    Route::get('/examrooms/{examroom}', [ExamroomController::class, 'show'])->name('examrooms.show');
    Route::get('/examrooms/{examroom}/edit', [ExamroomController::class, 'edit'])->name('examrooms.edit');
    Route::put('/examrooms/{examroom}', [ExamroomController::class, 'update'])->name('examrooms.update');
    Route::delete('/examrooms/{examroom}', [ExamroomController::class, 'destroy'])->name('examrooms.destroy');
        
    // Enrollment routes
Route::middleware(['auth'])->group(function () {
    Route::get('/enrollments', [EnrollmentController::class, 'index'])->name('enrollments.index');
    Route::get('/enrollments/create', [EnrollmentController::class, 'create'])->name('enrollments.create');
    Route::post('/enrollments', [EnrollmentController::class, 'store'])->name('enrollments.store');
    Route::get('/enrollments/{enrollment}/edit', [EnrollmentController::class, 'edit'])->name('enrollments.edit');
    Route::put('/enrollments/{enrollment}', [EnrollmentController::class, 'update'])->name('enrollments.update');
    Route::delete('/enrollments/{enrollment}', [EnrollmentController::class, 'destroy'])->name('enrollments.destroy');
    
    // Lecturer assignment routes
    Route::post('/assign-lecturers', [EnrollmentController::class, 'assignLecturers'])->name('assign.lecturers');
    Route::delete('/assign-lecturers/{unitId}', [EnrollmentController::class, 'destroyLecturerAssignment'])->name('assign.lecturers.destroy');
    Route::get('/lecturer-units/{lecturerId}', [EnrollmentController::class, 'getLecturerUnits'])->name('lecturer.units');
});
    // Time Slots management
    Route::get('/timeslots', [TimeSlotController::class, 'index'])->name('timeslots.index');
    Route::post('/timeslots', [TimeSlotController::class, 'store'])->name('timeslots.store');
    Route::put('/timeslots/{timeSlot}', [TimeSlotController::class, 'update'])->name('timeslots.update');
    Route::delete('/timeslots/{timeSlot}', [TimeSlotController::class, 'destroy'])->name('timeslots.destroy');

    Route::resource('classtimetable', ClassTimetableController::class);
});

// ✅ Exam Office and Admin Routes
Route::middleware(['auth', 'role:Admin|Exam office'])->group(function () {
    // Option 1: Keep the original dashboard
    Route::get('/exam-office', fn() => Inertia::render('ExamOffice/Dashboard'))->name('exam-office.dashboard');
    
    // Option 2: Use the unified dashboard (uncomment to use)
    //Route::get('/exam-office', [DashboardController::class, 'index'])->name('exam-office.dashboard');

    Route::get('/process-examtimetable', [ExamTimetableController::class, 'processForm'])->name('examtimetables.process-form');
    Route::post('/process-examtimetables', [ExamTimetableController::class, 'process'])->name('examtimetables.process');
    Route::get('/solve-exam-conflicts', [ExamTimetableController::class, 'solveConflicts'])->name('examtimetables.conflicts');
});

// ✅ Exam Office Routes
Route::middleware(['auth', 'role:Exam office'])->group(function () {
    Route::get('/exam-office/dashboard', [ExamOfficeController::class, 'dashboard'])->name('exam-office.dashboard');
});

// ✅ Faculty Admin Routes
Route::middleware(['auth', 'role:Faculty Admin', 'permission:view-dashboard'])->group(function () {
    // Option 1: Keep the original dashboard
    Route::get('/faculty-admin', fn() => Inertia::render('FacultyAdmin/Dashboard'))->name('faculty-admin.dashboard');
    
    // Option 2: Use the unified dashboard (uncomment to use)
    // Route::get('/faculty-admin', [DashboardController::class, 'index'])->name('faculty-admin.dashboard');
    
    // Faculty user management
    Route::middleware(['permission:manage-faculty-users'])->group(function () {
        Route::get('/faculty/lecturers', [LecturerController::class, 'index'])->name('faculty.lecturers');
        Route::get('/faculty/students', [StudentController::class, 'index'])->name('faculty.students');
    });   
   
    // Faculty enrollments management
    Route::middleware(['permission:manage-faculty-enrollments'])->group(function () {
        Route::get('/faculty/enrollments', [EnrollmentController::class, 'facultyEnrollments'])->name('faculty.enrollments');
    });   
    
    // Faculty timetable download
    Route::middleware(['permission:download-faculty-timetable'])->get('/faculty/timetable/download', [ExamTimetableController::class, 'downloadFacultyTimetable'])->name('faculty.timetable.download');
});

// ✅ Lecturer Routes
Route::middleware(['auth', 'role:Lecturer', 'permission:view-dashboard'])->group(function () {
    // Option 1: Keep the original dashboard
    Route::get('/lecturer', fn() => Inertia::render('Lecturer/Dashboard'))->name('lecturer.dashboard');
    
    // Option 2: Use the unified dashboard (uncomment to use)
    // Route::get('/lecturer', [DashboardController::class, 'index'])->name('lecturer.dashboard');
    
    // View own timetable
    Route::middleware(['permission:view-own-timetable'])->get('/lecturer/timetable', [ExamTimetableController::class, 'viewLecturerTimetable'])->name('lecturer.timetable');
    
    // View own units
    Route::middleware(['permission:view-own-units'])->get('/lecturer/units', [UnitController::class, 'lecturerUnits'])->name('lecturer.units');
    
    // Download own timetable
    Route::middleware(['permission:download-own-timetable'])->get('/lecturer/timetable/download', [ExamTimetableController::class, 'downloadLecturerTimetable'])->name('lecturer.timetable.download');
});

Route::middleware(['auth', 'role:Lecturer'])->group(function () {
    Route::get('/lecturer/dashboard', fn() => Inertia::render('Lecturer/Dashboard'))->name('lecturer.dashboard');
});

// Student Routes
Route::middleware(['auth', 'role:Student'])->group(function () {
    // Student Dashboard
    Route::get('/student', [DashboardController::class, 'studentDashboard'])->name('student.dashboard');
    
    // My Enrollments
    Route::get('/my-enrollments', [StudentController::class, 'myEnrollments'])->name('student.enrollments');
    
    // My Exams
    Route::get('/my-exams', [ExamTimetableController::class, 'viewStudentTimetable'])->name('student.exams');
    
    // Download My Exam Timetable
    Route::get('/my-exams/download', [ExamTimetableController::class, 'downloadStudentTimetable'])
        ->name('student.exams.download');
        
    // View specific exam details
    Route::get('/my-exams/{examtimetable}', [ExamTimetableController::class, 'viewStudentExamDetails'])
        ->name('student.exams.show');
        
    // My Class Timetable
    Route::get('/my-classes', [ClassTimetableController::class, 'viewStudentClassTimetable'])
        ->name('student.classes');
        
    // Download My Class Timetable
    Route::get('/my-classes/download', [ClassTimetableController::class, 'downloadStudentClassTimetable'])
        ->name('student.classes.download');
    
    // Student Exam Timetable
    Route::get('/student/exam-timetable', [ExamTimetableController::class, 'viewStudentTimetable'])
        ->name('student.exam-timetable');

    // Student Timetable
    Route::get('/student/timetable', [ClassTimetableController::class, 'viewStudentClassTimetable'])
        ->name('student.timetable')
        ->middleware(['auth', 'role:Student']);
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/my-enrollments', [StudentController::class, 'myEnrollments'])->name('my-enrollments');
});

// Lecturer assignment routes
Route::post('/assign-lecturers', [App\Http\Controllers\EnrollmentController::class, 'assignLecturers'])->name('assign-lecturers');
Route::delete('/assign-lecturers/{unitId}', [App\Http\Controllers\EnrollmentController::class, 'destroyLecturerAssignment'])->name('destroy-lecturer-assignment');
Route::get('/lecturer-units/{lecturerId}', [App\Http\Controllers\EnrollmentController::class, 'getLecturerUnits'])->name('lecturer-units');

// API routes that should be accessible without API middleware
Route::get('/units/by-semester/{semester_id}', [UnitController::class, 'getBySemester'])->name('units.by-semester');

// Faculty-specific exam timetable downloads
Route::middleware(['auth', 'permission:download-faculty-examtimetables'])->group(function () {
    Route::get('/examtimetable/faculty/download', [ExamTimetableController::class, 'downloadFacultyTimetable'])->name('examtimetable.faculty.download');
});

// Lecturer-specific exam timetable routes
Route::middleware(['auth', 'permission:view-own-examtimetables'])->group(function () {
    Route::get('/examtimetable/lecturer', [ExamTimetableController::class, 'viewLecturerTimetable'])->name('examtimetable.lecturer');
});

Route::middleware(['auth', 'permission:download-own-examtimetables'])->group(function () {
    Route::get('/examtimetable/lecturer/download', [ExamTimetableController::class, 'downloadLecturerTimetable'])->name('examtimetable.lecturer.download');
});


// Settings routes - accessible to users with manage-settings permission
Route::middleware(['auth', 'permission:manage-settings'])->group(function () {
    Route::get('/settings', fn() => Inertia::render('Admin/Settings'))->name('settings.index');
    Route::post('/settings', [AdminController::class, 'updateSettings'])->name('settings.update');
});

// Notification routes (protected by auth middleware)

// // Route::get('Notifications', [MailController::class, 'index'])->name('notifications.index');
// Route::middleware(['auth'])->group(function () {
//     Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
//     Route::post('/notifications/send', [NotificationController::class, 'sendReminders'])->name('notifications.send');
//     Route::get('/notifications/preview/{examId}', [NotificationController::class, 'previewNotifications'])->name('notifications.preview');
// });

// Notification routes
Route::middleware(['auth'])->group(function () {
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/send', [NotificationController::class, 'sendReminders'])->name('notifications.send');
    Route::get('/notifications/preview/{exam}', [NotificationController::class, 'previewNotifications'])->name('notifications.preview');
    
    // Notification preferences
    Route::get('/notifications/preferences', [NotificationPreferenceController::class, 'index'])->name('notifications.preferences');
    Route::post('/notifications/preferences', [NotificationPreferenceController::class, 'update'])->name('notifications.preferences.update');
    
    // Notification statistics
    Route::get('/notifications/statistics', [NotificationStatsController::class, 'index'])
        ->name('notifications.statistics')
        ->middleware('can:view-notification-stats');

    // New routes for update notifications
    Route::get('/notifications/update-preview/{exam}', [NotificationController::class, 'previewUpdateNotification'])
        ->name('notifications.update-preview');
    Route::post('/notifications/test-update/{exam}', [NotificationController::class, 'testUpdateNotification'])
        ->name('notifications.test-update');
    
    // User notification center
    Route::get('/my-notifications', [NotificationController::class, 'userNotifications'])
        ->name('notifications.user');
    Route::post('/notifications/{id}/mark-read', [NotificationController::class, 'markAsRead'])
        ->name('notifications.mark-read');
    Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllAsRead'])
        ->name('notifications.mark-all-read');
    
    // Notification logs with filtering
    Route::get('/notifications/logs', [NotificationController::class, 'filterLogs'])
        ->name('notifications.logs')
        ->middleware('can:view-notification-logs');
});

// Admin dashboard route
Route::get('/admin/dashboard', function () {
    return Inertia::render('Admin/Dashboard'); // Ensure this matches the file path
})->name('admin.dashboard');

// Catch-all route for SPA (must be at the bottom)
Route::get('/{any}', function () {
    return Inertia::render('NotFound');
})->where('any', '.*')->name('not-found');
