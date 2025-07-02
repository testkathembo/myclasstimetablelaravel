<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EnrollmentController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\SchoolController;
use App\Http\Controllers\ProgramController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\ClassController;
use App\Http\Controllers\SemesterController;
use App\Http\Controllers\ClassroomController;
use App\Http\Controllers\TimetableController;
use App\Http\Controllers\ClassTimetableController;
use App\Http\Controllers\ExamTimetableController;
use App\Http\Controllers\ExamroomController;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Inertia\Inertia;

// Authentication routes
require __DIR__.'/auth.php';

// ===================================================================
// AUTHENTICATED ROUTES
// ===================================================================

Route::middleware(['auth', 'verified'])->group(function () {
    
    // ===============================================================
    // CORE AUTHENTICATION & USER ROUTES
    // ===============================================================
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    // ===============================================================
    // DASHBOARD ROUTES - Available to all authenticated users
    // ===============================================================
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->middleware('permission:view-dashboard')
        ->name('dashboard');
    
    Route::get('/admin/dashboard', [DashboardController::class, 'adminDashboard'])
        ->middleware('permission:view-dashboard')
        ->name('admin.dashboard');

    // ===============================================================
    // ADMINISTRATION ROUTES - Permission-based access
    // ===============================================================
    
    // User Management
    Route::middleware(['permission:manage-users'])->group(function () {
        Route::resource('users', UserController::class);
    });

    // Role Management
    Route::middleware(['permission:manage-roles'])->group(function () {
        Route::resource('roles', RoleController::class);
    });

    // Permission Management
    Route::middleware(['permission:manage-permissions'])->group(function () {
        Route::resource('permissions', PermissionController::class)->except(['show']);
        Route::get('/api/permissions/grouped', [RoleController::class, 'getGroupedPermissions']);
    });

    // ===============================================================
    // ACADEMIC MANAGEMENT ROUTES - Permission-based access
    // ===============================================================

    // Schools Management
    Route::middleware(['permission:manage-schools'])->group(function () {
        Route::get('/schools', [SchoolController::class, 'index'])->name('schools.index');
        Route::get('/schools/create', [SchoolController::class, 'create'])->name('schools.create');
        Route::post('/schools', [SchoolController::class, 'store'])->name('schools.store');
        Route::get('/schools/{school}/edit', [SchoolController::class, 'edit'])->name('schools.edit');
        Route::put('/schools/{school}', [SchoolController::class, 'update'])->name('schools.update');
        Route::patch('/schools/{school}', [SchoolController::class, 'update'])->name('schools.patch');
        Route::delete('/schools/{school}', [SchoolController::class, 'destroy'])->name('schools.destroy');
    });

    // Schools View (separate permission for viewing)
    Route::middleware(['permission:view-schools'])->group(function () {
        Route::get('/schools/{school}', [SchoolController::class, 'show'])->name('schools.show');
        Route::get('/schools/{school}/dashboard', [SchoolController::class, 'dashboard'])->name('schools.dashboard');
    });

    // Programs Management
    Route::middleware(['permission:manage-programs'])->group(function () {
        Route::get('/programs', [ProgramController::class, 'index'])->name('programs.index');
        Route::get('/programs/create', [ProgramController::class, 'create'])->name('programs.create');
        Route::post('/programs', [ProgramController::class, 'store'])->name('programs.store');
        Route::get('/programs/{program}/edit', [ProgramController::class, 'edit'])->name('programs.edit');
        Route::put('/programs/{program}', [ProgramController::class, 'update'])->name('programs.update');
        Route::patch('/programs/{program}', [ProgramController::class, 'update'])->name('programs.patch');
        Route::delete('/programs/{program}', [ProgramController::class, 'destroy'])->name('programs.destroy');
    });

    // Programs View
    Route::middleware(['permission:view-programs'])->group(function () {
        Route::get('/programs/{program}', [ProgramController::class, 'show'])->name('programs.show');
    });

    // Units Management
    Route::middleware(['permission:manage-units'])->group(function () {
        Route::get('/units', [UnitController::class, 'index'])->name('units.index');
        Route::get('/units/create', [UnitController::class, 'create'])->name('units.create');
        Route::post('/units', [UnitController::class, 'store'])->name('units.store');
        Route::get('/units/{unit}/edit', [UnitController::class, 'edit'])->name('units.edit');
        Route::put('/units/{unit}', [UnitController::class, 'update'])->name('units.update');
        Route::patch('/units/{unit}', [UnitController::class, 'update'])->name('units.patch');
        Route::delete('/units/{unit}', [UnitController::class, 'destroy'])->name('units.destroy');
    });

    // Units View
    Route::middleware(['permission:view-units'])->group(function () {
        Route::get('/units/{unit}', [UnitController::class, 'show'])->name('units.show');
    });

    // Classes Management
    Route::middleware(['permission:manage-classes'])->group(function () {
        Route::get('/classes', [ClassController::class, 'index'])->name('classes.index');
        Route::get('/classes/create', [ClassController::class, 'create'])->name('classes.create');
        Route::post('/classes', [ClassController::class, 'store'])->name('classes.store');
        Route::get('/classes/{class}/edit', [ClassController::class, 'edit'])->name('classes.edit');
        Route::put('/classes/{class}', [ClassController::class, 'update'])->name('classes.update');
        Route::patch('/classes/{class}', [ClassController::class, 'update'])->name('classes.patch');
        Route::delete('/classes/{class}', [ClassController::class, 'destroy'])->name('classes.destroy');
    });

    // Classes View
    Route::middleware(['permission:view-classes'])->group(function () {
        Route::get('/classes/{class}', [ClassController::class, 'show'])->name('classes.show');
    });

    // Enrollments Management
    Route::middleware(['permission:manage-enrollments'])->group(function () {
        Route::get('/enrollments', [EnrollmentController::class, 'index'])->name('enrollments.index');
        Route::get('/enrollments/create', [EnrollmentController::class, 'create'])->name('enrollments.create');
        Route::post('/enrollments', [EnrollmentController::class, 'store'])->name('enrollments.store');
        Route::get('/enrollments/{enrollment}/edit', [EnrollmentController::class, 'edit'])->name('enrollments.edit');
        Route::put('/enrollments/{enrollment}', [EnrollmentController::class, 'update'])->name('enrollments.update');
        Route::patch('/enrollments/{enrollment}', [EnrollmentController::class, 'update'])->name('enrollments.patch');
        Route::delete('/enrollments/{enrollment}', [EnrollmentController::class, 'destroy'])->name('enrollments.destroy');
    });

    // Enrollments View
    Route::middleware(['permission:view-enrollments'])->group(function () {
        Route::get('/enrollments/{enrollment}', [EnrollmentController::class, 'show'])->name('enrollments.show');
    });

    // Semesters Management
    Route::middleware(['permission:manage-semesters'])->group(function () {
        Route::get('/semesters', [SemesterController::class, 'index'])->name('semesters.index');
        Route::get('/semesters/create', [SemesterController::class, 'create'])->name('semesters.create');
        Route::post('/semesters', [SemesterController::class, 'store'])->name('semesters.store');
        Route::get('/semesters/{semester}/edit', [SemesterController::class, 'edit'])->name('semesters.edit');
        Route::put('/semesters/{semester}', [SemesterController::class, 'update'])->name('semesters.update');
        Route::patch('/semesters/{semester}', [SemesterController::class, 'update'])->name('semesters.patch');
        Route::delete('/semesters/{semester}', [SemesterController::class, 'destroy'])->name('semesters.destroy');
    });

    // Semesters View
    Route::middleware(['permission:view-semesters'])->group(function () {
        Route::get('/semesters/{semester}', [SemesterController::class, 'show'])->name('semesters.show');
    });

    // Classrooms Management
    Route::middleware(['permission:manage-classrooms'])->group(function () {
        Route::get('/classrooms', [ClassroomController::class, 'index'])->name('classrooms.index');
        Route::get('/classrooms/create', [ClassroomController::class, 'create'])->name('classrooms.create');
        Route::post('/classrooms', [ClassroomController::class, 'store'])->name('classrooms.store');
        Route::get('/classrooms/{classroom}/edit', [ClassroomController::class, 'edit'])->name('classrooms.edit');
        Route::put('/classrooms/{classroom}', [ClassroomController::class, 'update'])->name('classrooms.update');
        Route::patch('/classrooms/{classroom}', [ClassroomController::class, 'update'])->name('classrooms.patch');
        Route::delete('/classrooms/{classroom}', [ClassroomController::class, 'destroy'])->name('classrooms.destroy');
    });

    // Classrooms View
    Route::middleware(['permission:view-classrooms'])->group(function () {
        Route::get('/classrooms/{classroom}', [ClassroomController::class, 'show'])->name('classrooms.show');
    });

    // ===============================================================
    // TIMETABLE MANAGEMENT ROUTES - Permission-based access
    // ===============================================================

    // General Timetables Management
    Route::middleware(['permission:manage-timetables'])->group(function () {
        Route::get('/timetables', [TimetableController::class, 'index'])->name('timetables.index');
        Route::get('/timetables/create', [TimetableController::class, 'create'])->name('timetables.create');
        Route::post('/timetables', [TimetableController::class, 'store'])->name('timetables.store');
        Route::get('/timetables/{timetable}/edit', [TimetableController::class, 'edit'])->name('timetables.edit');
        Route::put('/timetables/{timetable}', [TimetableController::class, 'update'])->name('timetables.update');
        Route::patch('/timetables/{timetable}', [TimetableController::class, 'update'])->name('timetables.patch');
        Route::delete('/timetables/{timetable}', [TimetableController::class, 'destroy'])->name('timetables.destroy');
    });

    // Timetables View
    Route::middleware(['permission:view-timetables'])->group(function () {
        Route::get('/timetables/{timetable}', [TimetableController::class, 'show'])->name('timetables.show');
    });

    // Class Timetables Management
    Route::middleware(['permission:manage-class-timetables'])->group(function () {
        Route::get('/classtimetables', [ClassTimetableController::class, 'index'])->name('classtimetables.index');
        Route::get('/classtimetables/create', [ClassTimetableController::class, 'create'])->name('classtimetables.create');
        Route::post('/classtimetables', [ClassTimetableController::class, 'store'])->name('classtimetables.store');
        Route::get('/classtimetables/{classtimetable}/edit', [ClassTimetableController::class, 'edit'])->name('classtimetables.edit');
        Route::put('/classtimetables/{classtimetable}', [ClassTimetableController::class, 'update'])->name('classtimetables.update');
        Route::patch('/classtimetables/{classtimetable}', [ClassTimetableController::class, 'update'])->name('classtimetables.patch');
        Route::delete('/classtimetables/{classtimetable}', [ClassTimetableController::class, 'destroy'])->name('classtimetables.destroy');
    });

    // Class Timetables View
    Route::middleware(['permission:view-class-timetables'])->group(function () {
        Route::get('/classtimetables/{classtimetable}', [ClassTimetableController::class, 'show'])->name('classtimetables.show');
    });

    // Exam Timetables Management
    Route::middleware(['permission:manage-exam-timetables'])->group(function () {
        Route::get('/examtimetables', [ExamTimetableController::class, 'index'])->name('examtimetables.index');
        Route::get('/examtimetables/create', [ExamTimetableController::class, 'create'])->name('examtimetables.create');
        Route::post('/examtimetables', [ExamTimetableController::class, 'store'])->name('examtimetables.store');
        Route::get('/examtimetables/{examtimetable}/edit', [ExamTimetableController::class, 'edit'])->name('examtimetables.edit');
        Route::put('/examtimetables/{examtimetable}', [ExamTimetableController::class, 'update'])->name('examtimetables.update');
        Route::patch('/examtimetables/{examtimetable}', [ExamTimetableController::class, 'update'])->name('examtimetables.patch');
        Route::delete('/examtimetables/{examtimetable}', [ExamTimetableController::class, 'destroy'])->name('examtimetables.destroy');
    });

    // Exam Timetables View
    Route::middleware(['permission:view-exam-timetables'])->group(function () {
        Route::get('/examtimetables/{examtimetable}', [ExamTimetableController::class, 'show'])->name('examtimetables.show');
    });

    // Exam Rooms Management
    Route::middleware(['permission:manage-exam-rooms'])->group(function () {
        Route::get('/examrooms', [ExamroomController::class, 'index'])->name('examrooms.index');
        Route::get('/examrooms/create', [ExamroomController::class, 'create'])->name('examrooms.create');
        Route::post('/examrooms', [ExamroomController::class, 'store'])->name('examrooms.store');
        Route::get('/examrooms/{examroom}/edit', [ExamroomController::class, 'edit'])->name('examrooms.edit');
        Route::put('/examrooms/{examroom}', [ExamroomController::class, 'update'])->name('examrooms.update');
        Route::patch('/examrooms/{examroom}', [ExamroomController::class, 'update'])->name('examrooms.patch');
        Route::delete('/examrooms/{examroom}', [ExamroomController::class, 'destroy'])->name('examrooms.destroy');
    });

    // Exam Rooms View
    Route::middleware(['permission:view-exam-rooms'])->group(function () {
        Route::get('/examrooms/{examroom}', [ExamroomController::class, 'show'])->name('examrooms.show');
    });

    // ===============================================================
    // STUDENT-SPECIFIC ROUTES
    // ===============================================================
    Route::middleware(['permission:view-own-class-timetables'])->group(function () {
        Route::get('/my-timetable', [TimetableController::class, 'studentTimetable'])->name('student.timetable');
    });

    Route::middleware(['permission:view-enrollments'])->group(function () {
        Route::get('/enroll', fn() => Inertia::render('Student/Enrollment'))->name('student.enrollment');
    });

    // ===============================================================
    // LECTURER-SPECIFIC ROUTES
    // ===============================================================
    Route::middleware(['permission:view-own-class-timetables'])->group(function () {
        Route::get('/my-classes', [TimetableController::class, 'lecturerClasses'])->name('lecturer.classes');
    });

    // ===============================================================
    // FACULTY ADMIN-SPECIFIC ROUTES
    // ===============================================================
    Route::middleware(['permission:view-users'])->group(function () {
        Route::get('/faculty/lecturers', fn() => Inertia::render('FacultyAdmin/Lecturers'))->name('faculty.lecturers');
        Route::get('/faculty/students', fn() => Inertia::render('FacultyAdmin/Students'))->name('faculty.students');
    });

    Route::middleware(['permission:view-enrollments'])->group(function () {
        Route::get('/faculty/enrollments', fn() => Inertia::render('FacultyAdmin/Enrollments'))->name('faculty.enrollments');
    });

    // ===============================================================
    // SETTINGS ROUTES
    // ===============================================================
    Route::middleware(['permission:manage-settings'])->group(function () {
        Route::get('/settings', fn() => Inertia::render('Admin/Settings'))->name('settings.index');
    });

    // ===============================================================
    // API ROUTES FOR FILTERING BY SCHOOL
    // ===============================================================
    Route::get('/api/faculty/students', function(Request $request) {
        $schoolId = $request->get('school');
        // Add your Student model logic here
        // return Student::where('school_id', $schoolId)->get();
        return response()->json(['message' => 'Students filtered by school']);
    })->middleware('permission:view-users');

    Route::get('/api/faculty/lecturers', function(Request $request) {
        $schoolId = $request->get('school');
        // Add your Lecturer model logic here
        // return Lecturer::where('school_id', $schoolId)->get();
        return response()->json(['message' => 'Lecturers filtered by school']);
    })->middleware('permission:view-users');

    // ===============================================================
    // DEBUG ROUTE (Remove in production)
    // ===============================================================
    Route::get('/debug-user', function() {
        $user = auth()->user();
        
        if (!$user) {
            return response()->json(['error' => 'No authenticated user']);
        }

        return response()->json([
            'user_id' => $user->id,
            'user_code' => $user->code ?? 'N/A',
            'user_name' => $user->first_name . ' ' . $user->last_name,
            'user_email' => $user->email,
            'roles' => $user->getRoleNames(),
            'permissions' => $user->getAllPermissions()->pluck('name'),
            'has_admin_role' => $user->hasRole('Admin'),
            'can_manage_users' => $user->can('manage-users'),
        ]);
    });
});

// ===================================================================
// CATCH-ALL ROUTE (Must be last)
// ===================================================================
Route::get('/{any}', function () {
    return Inertia::render('NotFound');
})->where('any', '.*')->name('not-found');

// Add this in your Admin bypass section (around line 150)
Route::middleware(['role:Admin'])->group(function () {
    // ... your existing routes ...
    
    // ✅ ADD THESE MISSING /timeslots ROUTES
    Route::get('/timeslots', [TimeSlotController::class, 'index'])->name('timeslots.index');
    Route::get('/timeslots/create', [TimeSlotController::class, 'create'])->name('timeslots.create');
    Route::post('/timeslots', [TimeSlotController::class, 'store'])->name('timeslots.store');
    Route::get('/timeslots/{timeSlot}', [TimeSlotController::class, 'show'])->name('timeslots.show');
    Route::get('/timeslots/{timeSlot}/edit', [TimeSlotController::class, 'edit'])->name('timeslots.edit');
    Route::put('/timeslots/{timeSlot}', [TimeSlotController::class, 'update'])->name('timeslots.update');
    Route::patch('/timeslots/{timeSlot}', [TimeSlotController::class, 'update'])->name('timeslots.patch');
    Route::delete('/timeslots/{timeSlot}', [TimeSlotController::class, 'destroy'])->name('timeslots.destroy');
    Route::post('/timeslots/bulk-delete', [TimeSlotController::class, 'bulkDestroy'])->name('timeslots.bulk-delete');
    

});

// Add this after the admin bypass section
Route::middleware(['permission:manage-time-slots'])->group(function () {
    Route::get('/timeslots', [TimeSlotController::class, 'index'])->name('timeslots.index');
    Route::post('/timeslots', [TimeSlotController::class, 'store'])->name('timeslots.store');
    Route::put('/timeslots/{timeSlot}', [TimeSlotController::class, 'update'])->name('timeslots.update');
    Route::delete('/timeslots/{timeSlot}', [TimeSlotController::class, 'destroy'])->name('timeslots.destroy');

});