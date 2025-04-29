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

// ✅ Public routes
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::post('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
require __DIR__.'/auth.php';

// ✅ Authenticated routes
Route::middleware(['auth'])->group(function () {
    // Add a unified dashboard route that will render a single dashboard with role-based content
    Route::get('/unified-dashboard', [DashboardController::class, 'index'])->name('unified-dashboard');

    // Semesters management
    Route::get('/all-semesters', [SemesterController::class, 'index'])->name('semesters.index');
    Route::get('/all-semesters/create', [SemesterController::class, 'create'])->name('semesters.create');
    Route::post('/all-semesters', [SemesterController::class, 'store'])->name('semesters.store');
    Route::get('/all-semesters/{semester}/edit', [SemesterController::class, 'edit'])->name('semesters.edit');
    Route::match(['PUT', 'PATCH'], '/all-semesters/{semester}', [SemesterController::class, 'update'])->name('semesters.update');
    Route::delete('/all-semesters/{semester}', [SemesterController::class, 'destroy'])->name('semesters.destroy');

    Route::get('/user/roles-permissions', [UserController::class, 'getUserRolesAndPermissions'])->name('user.roles-permissions');
    // Profile routes (accessible to all authenticated users)
    Route::prefix('profile')->group(function () {
        Route::get('/', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/', [ProfileController::class, 'update'])->name('profile.update');
        Route::delete('/', [ProfileController::class, 'destroy'])->name('profile.destroy');
    });

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
    
    // Timetable view - permission based
    Route::middleware(['permission:view-timetable|view-own-timetable'])
        ->get('/timetable', [SemesterController::class, 'viewTimetable'])
        ->name('timetable.view');
    
    // Logout route
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('/timeslots', [TimeSlotController::class, 'index'])->name('timeslots.index');
    Route::post('/timeslots', [TimeSlotController::class, 'store'])->name('timeslots.store');
    Route::put('/timeslots/{timeSlot}', [TimeSlotController::class, 'update'])->name('timeslots.update');
    Route::delete('/timeslots/{timeSlot}', [TimeSlotController::class, 'destroy'])->name('timeslots.destroy');

    Route::get('/examrooms', [ExamroomController::class, 'index'])->name('examrooms.index');
    Route::get('/examrooms/create', [ExamroomController::class, 'create'])->name('examrooms.create');
    Route::post('/examrooms', [ExamroomController::class, 'store'])->name('examrooms.store');
    Route::get('/examrooms/{examroom}', [ExamroomController::class, 'show'])->name('examrooms.show');
    Route::get('/examrooms/{examroom}/edit', [ExamroomController::class, 'edit'])->name('examrooms.edit');
    Route::put('/examrooms/{examroom}', [ExamroomController::class, 'update'])->name('examrooms.update');
    Route::delete('/examrooms/{examroom}', [ExamroomController::class, 'destroy'])->name('examrooms.destroy');

    Route::resource('classtimetables', ClassTimetableController::class);

    // ClassTimetable routes
    Route::middleware(['permission:manage-classtimetables'])->group(function () {
        Route::get('/classtimetables', [ClassTimetableController::class, 'index'])->name('classtimetables.index');
        Route::get('/classtimetables/create', [ClassTimetableController::class, 'create'])->name('classtimetables.create');
        Route::post('/classtimetables', [ClassTimetableController::class, 'store'])->name('classtimetables.store');
        Route::get('/classtimetables/{classtimetable}', [ClassTimetableController::class, 'show'])->name('classtimetables.show');
        Route::get('/classtimetables/{classtimetable}/edit', [ClassTimetableController::class, 'edit'])->name('classtimetables.edit');
        Route::put('/classtimetables/{classtimetable}', [ClassTimetableController::class, 'update'])->name('classtimetables.update');
        Route::delete('/classtimetables/{classtimetable}', [ClassTimetableController::class, 'destroy'])->name('classtimetables.destroy');
        Route::get('/classtimetables/download', [ClassTimetableController::class, 'downloadTimetable'])->name('classtimetables.download');
    });

    // ExamTimetable routes
    Route::middleware(['permission:manage-examtimetables'])->group(function () {
        Route::get('/examtimetable', [ExamTimetableController::class, 'index'])->name('examtimetable.index');
        Route::post('/examtimetable', [ExamTimetableController::class, 'store'])->name('examtimetable.store');
        Route::put('/examtimetable/{examtimetable}', [ExamTimetableController::class, 'update'])->name('examtimetable.update');
        Route::delete('/examtimetable/{examtimetable}', [ExamTimetableController::class, 'destroy'])->name('examtimetable.destroy');
        Route::get('/examtimetable/create', [ExamTimetableController::class, 'create'])->name('examtimetable.create');
        Route::get('/examtimetable/{examtimetable}', [ExamTimetableController::class, 'show'])->name('examtimetable.show');
        Route::get('/examtimetable/{examtimetable}/edit', [ExamTimetableController::class, 'edit'])->name('examtimetable.edit');
        Route::get('/examtimetable/{examtimetable}/download', [ExamTimetableController::class, 'download'])->name('examtimetable.download');
    });
});

// ✅ Admin Routes - Admin role bypasses permission checks
Route::middleware(['auth', 'role:Admin'])->group(function () {
    // Option 1: Keep the original dashboard
    Route::get('/admin', fn() => Inertia::render('Admin/Dashboard'))->name('admin.dashboard');
    
    // Option 2: Use the unified dashboard (uncomment to use)
    // Route::get('/admin', [DashboardController::class, 'index'])->name('admin.dashboard');

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

    // Roles and Permissions management
    Route::resource('roles', RoleController::class)->except(['show']);
    Route::resource('permissions', PermissionController::class)->except(['show']);

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
    Route::get('/semesters', [SemesterController::class, 'index'])->name('semesters.index');
    Route::get('/semesters/create', [SemesterController::class, 'create'])->name('semesters.create');
    Route::post('/semesters', [SemesterController::class, 'store'])->name('semesters.store');
    Route::get('/semesters/{semester}', [SemesterController::class, 'show'])->name('semesters.show');
    Route::get('/semesters/{semester}/edit', [SemesterController::class, 'edit'])->name('semesters.edit');
    Route::put('/semesters/{semester}', [SemesterController::class, 'update'])->name('semesters.update');
    Route::delete('/semesters/{semester}', [SemesterController::class, 'destroy'])->name('semesters.destroy');


    // Classrooms management
    Route::get('/classrooms', [ClassroomController::class, 'index'])->name('classrooms.index');
    Route::get('/classrooms/create', [ClassroomController::class, 'create'])->name('classrooms.create');
    Route::post('/classrooms', [ClassroomController::class, 'store'])->name('classrooms.store');
    Route::get('/classrooms/{classroom}', [ClassroomController::class, 'show'])->name('classrooms.show');
    Route::get('/classrooms/{classroom}/edit', [ClassroomController::class, 'edit'])->name('classrooms.edit');
    Route::put('/classrooms/{classroom}', [ClassroomController::class, 'update'])->name('classrooms.update');
    Route::delete('/classrooms/{classroom}', [ClassroomController::class, 'destroy'])->name('classrooms.destroy');

    // Examrooms management
    Route::get('/examrooms', [ExamroomController::class, 'index'])->name('examrooms.index');
    Route::get('/examrooms/create', [ExamroomController::class, 'create'])->name('examrooms.create');
    Route::post('/examrooms', [ExamroomController::class, 'store'])->name('examrooms.store');
    Route::get('/examrooms/{examroom}', [ExamroomController::class, 'show'])->name('examrooms.show');
    Route::get('/examrooms/{examroom}/edit', [ExamroomController::class, 'edit'])->name('examrooms.edit');
    Route::put('/examrooms/{examroom}', [ExamroomController::class, 'update'])->name('examrooms.update');
    Route::delete('/examrooms/{examroom}', [ExamroomController::class, 'destroy'])->name('examrooms.destroy');

    // Enrollments management
    Route::get('/enrollments', [EnrollmentController::class, 'index'])->name('enrollments.index');
    Route::post('/enrollments', [EnrollmentController::class, 'store'])->name('enrollments.store');
    Route::put('/enrollments/{enrollment}', [EnrollmentController::class, 'update'])->name('enrollments.update');
    Route::delete('/enrollments/{enrollment}', [EnrollmentController::class, 'destroy'])->name('enrollments.destroy');

    // // Timetable management
    // Route::middleware(['auth', 'permission:manage-classtimetables'])->group(function () {
    //     Route::get('/classtimetable', [ClassTimetableController::class, 'index'])->name('classtimetable.index');
    //     Route::post('/classtimetable', [ClassTimetableController::class, 'store'])->name('classtimetable.store');
    //     Route::put('/classtimetable/{classtimetables}', [ClassTimetableController::class, 'update'])->name('classtimetable.update');
    //     Route::delete('/classtimetable/{classtimetables}', [ClassTimetableController::class, 'destroy'])->name('classtimetable.destroy');
    // });
    
    
    // Time Slots management
    Route::get('/timeslots', [TimeSlotController::class, 'index'])->name('timeslots.index');
    Route::post('/timeslots', [TimeSlotController::class, 'store'])->name('timeslots.store');
    Route::put('/timeslots/{timeSlot}', [TimeSlotController::class, 'update'])->name('timeslots.update');
    Route::delete('/timeslots/{timeSlot}', [TimeSlotController::class, 'destroy'])->name('timeslots.destroy');

    // Timetable management
    Route::middleware(['auth', 'permission:manage-examtimetables'])->group(function () {
        Route::get('/examtimetable', [ExamTimetableController::class, 'index'])->name('examtimetable.index');
        Route::post('/examtimetable', [ExamTimetableController::class, 'store'])->name('examtimetable.store');
        Route::put('/examtimetable/{examtimetables}', [ExamTimetableController::class, 'update'])->name('examtimetable.update');
        Route::delete('/examtimetable/{examtimetables}', [ExamTimetableController::class, 'destroy'])->name('examtimetable.destroy');
    });

    Route::get('/examtimetable/create', [ExamTimetableController::class, 'create'])->name('examtimetable.create');
    Route::get('/examtimetable/{examtimetable}', [ExamTimetableController::class, 'show'])->name('examtimetable.show');
    Route::get('/examtimetable/{examtimetables}/edit', [ExamTimetableController::class, 'edit'])->name('examtimetable.edit');
    Route::get('/examtimetable/{examtimetables}/download', [ExamTimetableController::class, 'download'])->name('examtimetable.download');
    Route::get('/examtimetable/{examtimetables}/generate', [ExamTimetableController::class, 'generate'])->name('examtimetable.generate');
    Route::get('/download-ExamTimetable', [ExamTimetableController::class, 'downloadExamTimetable'])->name('ExamTimetable.download');
});

// ✅ Exam Office and Admin Routes
Route::middleware(['auth', 'role:Admin|Exam office'])->group(function () {
    // Option 1: Keep the original dashboard
    Route::get('/exam-office', fn() => Inertia::render('ExamOffice/Dashboard'))->name('exam-office.dashboard');
    
    // Option 2: Use the unified dashboard (uncomment to use)
    // Route::get('/exam-office', [DashboardController::class, 'index'])->name('exam-office.dashboard');

    // Timetable management
    Route::resource('exam-timetables', ExamTimetableController::class);

    Route::get('/process-examtimetable', [ExamTimetableController::class, 'processForm'])->name('examtimetables.process-form');
    Route::post('/process-examtimetable', [ExamTimetableController::class, 'process'])->name('examtimetables.process');
    Route::get('/solve-conflicts', [ExamTimetableController::class, 'solveConflicts'])->name('examtimetables.conflicts');

    // Timetable download
    Route::get('/download-ExamTimetable', [ExamTimetableController::class, 'downloadExamTimetable'])->name('examtimetable.download');
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

// ✅ Student Routes
Route::middleware(['auth', 'role:Student'])->group(function () {
    Route::get('/student', [DashboardController::class, 'index'])->name('student.dashboard');
});

// Settings routes - accessible to users with manage-settings permission
Route::middleware(['auth', 'permission:manage-settings'])->group(function () {
    Route::get('/settings', fn() => Inertia::render('Admin/Settings'))->name('settings.index');
    Route::post('/settings', [AdminController::class, 'updateSettings'])->name('settings.update');
});

// Lecturer assignment routes
Route::post('/assign-lecturers', [App\Http\Controllers\EnrollmentController::class, 'assignLecturers'])->name('assign-lecturers');
Route::delete('/assign-lecturers/{unitId}', [App\Http\Controllers\EnrollmentController::class, 'destroyLecturerAssignment'])->name('destroy-lecturer-assignment');
Route::get('/lecturer-units/{lecturerId}', [App\Http\Controllers\EnrollmentController::class, 'getLecturerUnits'])->name('lecturer-units');

// API routes that should be accessible without API middleware
Route::get('/units/by-semester/{semester_id}', [UnitController::class, 'getBySemester'])->name('units.by-semester');

// Catch-all route for SPA (must be at the bottom)
Route::get('/{any}', function () {
    return Inertia::render('NotFound');
})->where('any', '.*')->name('not-found');

// ✅ Process Timetable Route
Route::middleware(['auth', 'permission:process-timetable'])->group(function () {
    Route::post('/process-timetable', [ExamTimetableController::class, 'process'])->name('process-timetable');
});

Route::middleware(['auth', 'permission:update-examtimetables'])->group(function () {
    Route::put('/examtimetable/{examtimetable}', [ExamTimetableController::class, 'update'])->name('examtimetable.update');
});