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

// ✅ Public routes
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::post('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
require __DIR__.'/auth.php';

// ✅ Authenticated routes
Route::middleware(['auth'])->group(function () {

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
});

// ✅ Admin Routes - view-dashboard permission required
Route::middleware(['auth', 'role:Admin', 'permission:view-dashboard'])->group(function () {
    Route::get('/admin', fn() => Inertia::render('Admin/Dashboard'))->name('admin.dashboard');
    
    // Users management - manage-users permission required
    Route::middleware(['permission:manage-users'])->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::get('/users/{user}', [UserController::class, 'show'])->name('users.show');
        Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
        
        // Role assignment
        Route::get('/users/{user}/edit-role', [UserController::class, 'editRole'])->name('users.editRole');
        Route::put('/users/{user}/update-role', [UserController::class, 'updateRole'])->name('users.updateRole');
    });
    
    // Roles and Permissions management
    Route::middleware(['permission:manage-roles'])->group(function () {
        Route::resource('roles', RoleController::class)->except(['show']);
        Route::resource('permissions', PermissionController::class)->except(['show']);
    });
    
    // Faculties management
    Route::middleware(['permission:manage-faculties'])->group(function () {
        Route::get('/faculties', [FacultyController::class, 'index'])->name('faculties.index');
        Route::get('/faculties/create', [FacultyController::class, 'create'])->name('faculties.create');
        Route::post('/faculties', [FacultyController::class, 'store'])->name('faculties.store');
        Route::get('/faculties/{faculty}', [FacultyController::class, 'show'])->name('faculties.show');
        Route::get('/faculties/{faculty}/edit', [FacultyController::class, 'edit'])->name('faculties.edit');
        Route::match(['PUT', 'PATCH'], '/faculties/{faculty}', [FacultyController::class, 'update'])->name('faculties.update');
        Route::delete('/faculties/{faculty}', [FacultyController::class, 'destroy'])->name('faculties.destroy');
    });
    
    // Units management
    Route::middleware(['permission:manage-units'])->group(function () {
        Route::get('/units', [UnitController::class, 'index'])->name('units.index');
        Route::get('/units/create', [UnitController::class, 'create'])->name('units.create');
        Route::post('/units', [UnitController::class, 'store'])->name('units.store');
        Route::get('/units/{unit}', [UnitController::class, 'show'])->name('units.show');
        Route::get('/units/{unit}/edit', [UnitController::class, 'edit'])->name('units.edit');
        Route::put('/units/{unit}', [UnitController::class, 'update'])->name('units.update');
        Route::delete('/units/{unit}', [UnitController::class, 'destroy'])->name('units.destroy');
    });
    
    // Classrooms management
    Route::middleware(['permission:manage-classrooms'])->group(function () {
        Route::get('/classrooms', [ClassroomController::class, 'index'])->name('classrooms.index');
        Route::get('/classrooms/create', [ClassroomController::class, 'create'])->name('classrooms.create');
        Route::post('/classrooms', [ClassroomController::class, 'store'])->name('classrooms.store');
        Route::get('/classrooms/{classroom}', [ClassroomController::class, 'show'])->name('classrooms.show');
        Route::get('/classrooms/{classroom}/edit', [ClassroomController::class, 'edit'])->name('classrooms.edit');
        Route::put('/classrooms/{classroom}', [ClassroomController::class, 'update'])->name('classrooms.update');
        Route::delete('/classrooms/{classroom}', [ClassroomController::class, 'destroy'])->name('classrooms.destroy');
    });
    
    // Enrollments management
    
    // Enrollments management
    Route::middleware(['permission:manage-enrollments'])->group(function () {
        Route::get('/enrollments', [EnrollmentController::class, 'index'])->name('enrollments.index');
        Route::get('/enrollments/create', [EnrollmentController::class, 'create'])->name('enrollments.create');
        Route::post('/enrollments', [EnrollmentController::class, 'store'])->name('enrollments.store');
        Route::get('/enrollments/{enrollment}', [EnrollmentController::class, 'show'])->name('enrollments.show');
        Route::get('/enrollments/{enrollment}/edit', [EnrollmentController::class, 'edit'])->name('enrollments.edit');
        Route::put('/enrollments/{enrollment}', [EnrollmentController::class, 'update'])->name('enrollments.update');
        Route::delete('/enrollments/{enrollment}', [EnrollmentController::class, 'destroy'])->name('enrollments.destroy');
        
        Route::post('/assign-lecturers', [EnrollmentController::class, 'assignLecturers'])->name('assign.lecturers');
        Route::get('/lecturer-units/{lecturerId}', [EnrollmentController::class, 'lecturerUnits'])->name('lecturer.units');
        Route::delete('/assign-lecturers/{unitId}', [EnrollmentController::class, 'unassignLecturer'])->name('lecturer.unassign');
    });
    
    // Time Slots management - consolidated to a single controller
    Route::get('/timeslotes', [TimesloteController::class, 'index'])->name('timeslotes.index');
    Route::get('/timeslotes/create', [TimesloteController::class, 'create'])->name('timeslotes.create');
    Route::post('/timeslotes', [TimesloteController::class, 'store'])->name('timeslotes.store');
    Route::get('/timeslotes/{timeslote}', [TimesloteController::class, 'show'])->name('timeslotes.show');
    Route::get('/timeslotes/{timeslote}/edit', [TimesloteController::class, 'edit'])->name('timeslotes.edit');
    Route::put('/timeslotes/{timeslote}', [TimesloteController::class, 'update'])->name('timeslotes.update');
    Route::delete('/timeslotes/{timeslote}', [TimesloteController::class, 'destroy'])->name('timeslotes.destroy');
    
    // TimeSlots
    Route::get('/timeslots', [TimeSlotController::class, 'index'])->name('timeslots.index');
    Route::post('/timeslots', [TimeSlotController::class, 'store'])->name('timeslots.store');
    Route::put('/timeslots/{timeSlot}', [TimeSlotController::class, 'update'])->name('timeslots.update');
    Route::delete('/timeslots/{timeSlot}', [TimeSlotController::class, 'destroy'])->name('timeslots.destroy');
    
    
    // Process timetable and solve conflicts
    Route::middleware(['permission:process-timetable'])->get('/process-timetable', [ExamTimetableController::class, 'processForm'])->name('timetables.process-form');
    Route::middleware(['permission:process-timetable'])->post('/process-timetable', [ExamTimetableController::class, 'process'])->name('timetables.process');
    Route::middleware(['permission:solve-conflicts'])->get('/solve-conflicts', [ExamTimetableController::class, 'solveConflicts'])->name('timetables.conflicts');
    
    // Download timetable
    Route::middleware(['permission:download-timetable'])->get('/download-timetable', [ExamTimetableController::class, 'downloadTimetable'])->name('timetable.download');
});

// ✅ Exam Office Routes
Route::middleware(['auth', 'role:Exam office', 'permission:view-dashboard'])->group(function () {
    Route::get('/exam-office', fn() => Inertia::render('ExamOffice/Dashboard'))->name('exam-office.dashboard');
    
    // Timetable management
    Route::middleware(['permission:create-timetable'])->group(function () {
        Route::get('/exam-timetable/view', [ExamTimetableController::class, 'view'])->name('exam-timetable.view');
        Route::get('/examtimetable', [ExamTimetableController::class, 'index'])->name('exam-timetable.index');
        Route::post('/exam-timetables', [ExamTimetableController::class, 'store'])->name('exam-timetables.store');
        Route::put('/exam-timetables/{id}', [ExamTimetableController::class, 'update'])->name('exam-timetables.update');
        Route::delete('/exam-timetables/{id}', [ExamTimetableController::class, 'destroy'])->name('exam-timetables.destroy');
    });
    
    // Process timetable and solve conflicts
    Route::middleware(['permission:process-timetable'])->post('/process-timetable', [ExamTimetableController::class, 'process'])->name('exam.timetables.process');
    Route::middleware(['permission:solve-conflicts'])->get('/solve-conflicts', [ExamTimetableController::class, 'solveConflicts'])->name('exam.timetables.conflicts');
});

// ✅ Faculty Admin Routes
Route::middleware(['auth', 'role:Faculty Admin', 'permission:view-dashboard'])->group(function () {
    Route::get('/faculty-admin', fn() => Inertia::render('FacultyAdmin/Dashboard'))->name('faculty-admin.dashboard');
    
    // Faculty user management
    Route::middleware(['permission:manage-faculty-users'])->group(function () {
        Route::get('/faculty/lecturers', [LecturerController::class, 'index'])->name('faculty.lecturers');
        Route::get('/faculty/students', [StudentController::class, 'index'])->name('faculty.students');
    });
    
    // Faculty units management
    Route::middleware(['permission:manage-faculty-units'])->group(function () {
        Route::get('/faculty/units', [UnitController::class, 'facultyUnits'])->name('faculty.units');
    });
    
    // Faculty enrollments management
    Route::middleware(['permission:manage-faculty-enrollments'])->group(function () {
        Route::get('/faculty/enrollments', [EnrollmentController::class, 'facultyEnrollments'])->name('faculty.enrollments');
    });
    
    // Faculty semesters management
    Route::middleware(['permission:manage-semesters'])->group(function () {
        Route::get('/semesters', [SemesterController::class, 'facultySemesters'])->name('faculty.semesters');
    });
    
    // Faculty timetable download
    Route::middleware(['permission:download-faculty-timetable'])->get('/faculty/timetable/download', [ExamTimetableController::class, 'downloadFacultyTimetable'])->name('faculty.timetable.download');
});

// ✅ Lecturer Routes
Route::middleware(['auth', 'role:Lecturer', 'permission:view-dashboard'])->group(function () {
    Route::get('/lecturer', fn() => Inertia::render('Lecturer/Dashboard'))->name('lecturer.dashboard');
    
    // View own timetable
    Route::middleware(['permission:view-own-timetable'])->get('/lecturer/timetable', [ExamTimetableController::class, 'viewLecturerTimetable'])->name('lecturer.timetable');
    
    // View own units
    Route::middleware(['permission:view-own-units'])->get('/lecturer/units', [UnitController::class, 'lecturerUnits'])->name('lecturer.units');
    
    // Download own timetable
    Route::middleware(['permission:download-own-timetable'])->get('/lecturer/timetable/download', [ExamTimetableController::class, 'downloadLecturerTimetable'])->name('lecturer.timetable.download');
});

// ✅ Student Routes
Route::middleware(['auth', 'role:Student', 'permission:view-dashboard'])->group(function () {
    Route::get('/student', fn() => Inertia::render('Student/Dashboard'))->name('student.dashboard');
    
    // View own timetable
    Route::middleware(['permission:view-own-timetable'])->get('/student/timetable', [ExamTimetableController::class, 'viewStudentTimetable'])->name('student.timetable');
    
    // View own units
    Route::middleware(['permission:view-own-units'])->get('/student/units', [UnitController::class, 'studentUnits'])->name('student.units');
    
    // Download own timetable
    Route::middleware(['permission:download-own-timetable'])->get('/student/timetable/download', [ExamTimetableController::class, 'downloadStudentTimetable'])->name('student.timetable.download');
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

// Catch-all route for SPA (must be at the bottom)
Route::get('/{any}', function () {
    return Inertia::render('NotFound');
})->where('any', '.*')->name('not-found');