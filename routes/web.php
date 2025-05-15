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
use App\Http\Controllers\SemesterUnitController;
use App\Http\Controllers\StudentEnrollmentController;
use App\Http\Controllers\ProgramGroupController;
use App\Http\Controllers\ProgramController;
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
    Route::middleware(['permission:manage-users'])->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::get('/users/{user}', [UserController::class, 'show'])->name('users.show');
        Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
        Route::get('/users/{user}/edit-role', [UserController::class, 'editRole'])->name('users.editRole');
        Route::put('/users/{user}/update-role', [UserController::class, 'updateRole'])->name('users.updateRole');
    });

    // Faculties management
    Route::middleware(['permission:manage-faculties'])->group(function () {
        Route::get('/faculties', [FacultyController::class, 'index'])->name('faculties.index');
        Route::post('/faculties', [FacultyController::class, 'store'])->name('faculties.store');
        Route::put('/faculties/{faculty}', [FacultyController::class, 'update'])->name('faculties.update');
        Route::delete('/faculties/{faculty}', [FacultyController::class, 'destroy'])->name('faculties.destroy');
    });

    // Units management
    Route::middleware(['permission:manage-units'])->group(function () {
        Route::get('/schools/sces/bbit/units', [UnitController::class, 'index'])->name('units.index');
        Route::post('/units', [UnitController::class, 'store'])->name('units.store');
        Route::put('/units/{unit}', [UnitController::class, 'update'])->name('units.update');
        Route::delete('/units/{unit}', [UnitController::class, 'destroy'])->name('units.destroy');
    });

    // Semesters management
    Route::middleware(['permission:manage-semesters'])->group(function () {
        Route::resource('semesters', SemesterController::class);
        Route::get('/semesters/{semester}/edit', [SemesterController::class, 'edit'])->name('semesters.edit');
        Route::match(['PUT', 'PATCH'], '/semesters/{semester}', [SemesterController::class, 'update'])->name('semesters.update');
        Route::delete('/semesters/{semester}', [SemesterController::class, 'destroy'])->name('semesters.destroy');
    });

    // Classrooms management
    Route::middleware(['permission:manage-classrooms'])->group(function () {
        Route::get('/schools/sces/bbit/classrooms', [ClassroomController::class, 'index'])->name('classrooms.index');
        Route::get('/classrooms/create', [ClassroomController::class, 'create'])->name('classrooms.create');
        Route::post('/classrooms', [ClassroomController::class, 'store'])->name('classrooms.store');
        Route::get('/classrooms/{classroom}', [ClassroomController::class, 'show'])->name('classrooms.show');
        Route::get('/classrooms/{classroom}/edit', [ClassroomController::class, 'edit'])->name('classrooms.edit');
        Route::put('/classrooms/{classroom}', [ClassroomController::class, 'update'])->name('classrooms.update');
        Route::delete('/classrooms/{classroom}', [ClassroomController::class, 'destroy'])->name('classrooms.destroy');
        // Add a fallback POST route for DELETE method
        Route::post('/classrooms/{classroom}/delete', [ClassroomController::class, 'destroy'])->name('classrooms.destroy.post');
    });

    // ClassTimetable routes
    Route::middleware(['permission:manage-classtimetables'])->group(function () {
        Route::get('/schools/sces/bbit/classtimetable', [ClassTimetableController::class, 'index'])->name('classtimetable.index');
        Route::get('/classtimetable/create', [ClassTimetableController::class, 'create'])->name('classtimetable.create');
        Route::post('/classtimetable', [ClassTimetableController::class, 'store'])->name('classtimetable.store');
        Route::get('/classtimetable/{classtimetable}', [ClassTimetableController::class, 'show'])->name('classtimetable.show');
        Route::get('/classtimetable/{classtimetable}/edit', [ClassTimetableController::class, 'edit'])->name('classtimetable.edit');
        Route::put('/classtimetable/{classtimetable}', [ClassTimetableController::class, 'update'])->name('classtimetable.update');
        Route::delete('/classtimetable/{classtimetable}', [ClassTimetableController::class, 'destroy'])->name('classtimetable.destroy');
        Route::post('/classtimetable/{classtimetable}/delete', [ClassTimetableController::class, 'destroy'])->name('classtimetable.destroy.post');
        Route::get('/classtimetable/download', [ClassTimetableController::class, 'downloadTimetable'])->name('classtimetable.download');
    });
    
    // Exams Rooms
    Route::middleware(['permission:manage-examrooms'])->group(function () {
        Route::get('/schools/sces/bbit/examrooms', [ExamroomController::class, 'index'])->name('examrooms.index');
        Route::get('/examrooms/create', [ExamroomController::class, 'create'])->name('examrooms.create');
        Route::post('/examrooms', [ExamroomController::class, 'store'])->name('examrooms.store');
        Route::get('/examrooms/{examroom}', [ExamroomController::class, 'show'])->name('examrooms.show');
        Route::get('/examrooms/{examroom}/edit', [ExamroomController::class, 'edit'])->name('examrooms.edit');
        Route::put('/examrooms/{examroom}', [ExamroomController::class, 'update'])->name('examrooms.update');
        Route::delete('/examrooms/{examroom}', [ExamroomController::class, 'destroy'])->name('examrooms.destroy');
        Route::post('/examrooms/{examroom}/delete', [ExamroomController::class, 'destroy'])->name('examrooms.destroy.post');
    });
     
    // Time Slots
    Route::middleware(['permission:manage-timeslots'])->group(function () {
        Route::get('/schools/sces/bbit/timeslots', [TimeSlotController::class, 'index'])->name('timeslots.index');
        Route::post('/timeslots', [TimeSlotController::class, 'store'])->name('timeslots.store');
        Route::put('/timeslots/{timeSlot}', [TimeSlotController::class, 'update'])->name('timeslots.update');
        Route::delete('/timeslots/{timeSlot}', [TimeSlotController::class, 'destroy'])->name('timeslots.destroy');
    });

    // Class Time Slots
    Route::middleware(['permission:manage-classtimeslots'])->group(function () {
        Route::get('/schools/sces/bbit/classtimeslot', [ClassTimeSlotController::class, 'index'])->name('classtimeslot.index');
        Route::post('/classtimeslot', [ClassTimeSlotController::class, 'store'])->name('classtimeslot.store');
        Route::put('/classtimeslot/{classtimeSlot}', [ClassTimeSlotController::class, 'update'])->name('classtimeslot.update');
        Route::delete('/classtimeslot/{classtimeSlot}', [ClassTimeSlotController::class, 'destroy'])->name('classtimeslot.destroy');
    });
    
    // ExamTimetable routes
    Route::middleware(['permission:manage-examtimetables'])->group(function () {
        Route::get('/schools/sces/bbit/examtimetable', [ExamTimetableController::class, 'index'])->name('examtimetable.index');
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

    // Process Timetable Route
    Route::middleware(['permission:process-examtimetables'])->group(function () {
        Route::post('/process-examtimetables', [ExamTimetableController::class, 'process'])->name('examtimetables.process');
        Route::get('/solve-exam-conflicts', [ExamTimetableController::class, 'solveConflicts'])->name('examtimetables.conflicts');
    });

    // Download exam timetable route
    Route::get('/download-examtimetables', [ExamTimetableController::class, 'downloadPDF'])
        ->middleware(['permission:download-examtimetables'])
        ->name('examtimetable.download');

    // Download class timetable route
    Route::get('/download-classtimetables', [ClassTimetableController::class, 'downloadPDF'])
        ->middleware(['permission:download-classtimetables'])
        ->name('classtimetable.download');

    // NEW: Semester Unit Assignment Routes
    Route::middleware(['permission:manage-semester-units'])->group(function () {
        Route::get('/semester-units', [SemesterUnitController::class, 'index'])->name('semester-units.index');
        Route::get('/semester-units/create', [SemesterUnitController::class, 'create'])->name('semester-units.create');
        Route::post('/semester-units', [SemesterUnitController::class, 'store'])->name('semester-units.store');
        Route::delete('/semester-units/{id}', [SemesterUnitController::class, 'destroy'])->name('semester-units.destroy');
        Route::post('/semester-units/bulk-assign', [SemesterUnitController::class, 'bulkAssign'])->name('semester-units.bulk-assign');
    });

    // Programs management
    Route::middleware(['permission:manage-programs'])->group(function () {
        Route::get('/programs', [ProgramController::class, 'index'])->name('programs.index');
        Route::get('/programs/create', [ProgramController::class, 'create'])->name('programs.create');
        Route::post('/programs', [ProgramController::class, 'store'])->name('programs.store');
        Route::get('/programs/{program}', [ProgramController::class, 'show'])->name('programs.show');
        Route::get('/programs/{program}/edit', [ProgramController::class, 'edit'])->name('programs.edit');
        Route::put('/programs/{program}', [ProgramController::class, 'update'])->name('programs.update');
        Route::delete('/programs/{program}', [ProgramController::class, 'destroy'])->name('programs.destroy');
    });

    // Semesters management (centralized routes)
    Route::middleware(['permission:manage-semesters'])->group(function () {
        Route::get('/semesters', [SemesterController::class, 'index'])->name('semesters.index');
        Route::get('/semesters/create', [SemesterController::class, 'create'])->name('semesters.create');
        Route::post('/semesters', [SemesterController::class, 'store'])->name('semesters.store');
        Route::get('/semesters/{semester}', [SemesterController::class, 'show'])->name('semesters.show');
        Route::get('/semesters/{semester}/edit', [SemesterController::class, 'edit'])->name('semesters.edit');
        Route::put('/semesters/{semester}', [SemesterController::class, 'update'])->name('semesters.update');
        Route::delete('/semesters/{semester}', [SemesterController::class, 'destroy'])->name('semesters.destroy');
    });

    // Units management (centralized routes)
    Route::middleware(['permission:manage-units'])->group(function () {
        Route::get('/units', [UnitController::class, 'index'])->name('units.index');
        Route::get('/units/create', [UnitController::class, 'create'])->name('units.create');
        Route::post('/units', [UnitController::class, 'store'])->name('units.store');
        Route::get('/units/{unit}', [UnitController::class, 'show'])->name('units.show');
        Route::get('/units/{unit}/edit', [UnitController::class, 'edit'])->name('units.edit');
        Route::put('/units/{unit}', [UnitController::class, 'update'])->name('units.update');
        Route::delete('/units/{unit}', [UnitController::class, 'destroy'])->name('units.destroy');
    });

    // Enrollments management (centralized routes)
    Route::middleware(['permission:manage-enrollments'])->group(function () {
        Route::get('/enrollments', [EnrollmentController::class, 'index'])->name('enrollments.index');
        Route::get('/enrollments/create', [EnrollmentController::class, 'create'])->name('enrollments.create');
        Route::post('/enrollments', [EnrollmentController::class, 'store'])->name('enrollments.store');
        Route::get('/enrollments/{enrollment}', [EnrollmentController::class, 'show'])->name('enrollments.show');
        Route::get('/enrollments/{enrollment}/edit', [EnrollmentController::class, 'edit'])->name('enrollments.edit');
        Route::put('/enrollments/{enrollment}', [EnrollmentController::class, 'update'])->name('enrollments.update');
        Route::delete('/enrollments/{enrollment}', [EnrollmentController::class, 'destroy'])->name('enrollments.destroy');
        Route::post('/enrollments/bulk', [EnrollmentController::class, 'bulkEnroll'])->name('enrollments.bulk');
    });

    // NEW: Program Group Management Routes
    Route::middleware(['permission:manage-program-groups'])->group(function () {
        Route::get('/program-groups', [ProgramGroupController::class, 'index'])->name('program-groups.index');
        Route::post('/program-groups', [ProgramGroupController::class, 'store'])->name('program-groups.store');
        Route::put('/program-groups/{programGroup}', [ProgramGroupController::class, 'update'])->name('program-groups.update');
        Route::delete('/program-groups/{programGroup}', [ProgramGroupController::class, 'destroy'])->name('program-groups.destroy');
    });

    // NEW: Student Enrollment Routes
    Route::middleware(['role:Student'])->group(function () {
        Route::get('/enroll', [StudentEnrollmentController::class, 'showEnrollmentForm'])->name('student.enrollment-form');
        Route::post('/enroll', [StudentEnrollmentController::class, 'enroll'])->name('student.enroll');
        Route::get('/my-enrollments', [StudentEnrollmentController::class, 'viewEnrollments'])->name('student.my-enrollments');
    });

    // NEW: Admin Enrollment Management Routes
    Route::middleware(['permission:manage-enrollments'])->group(function () {
        Route::get('/schools/sces/bbit/enrollments', [EnrollmentController::class, 'index'])->name('enrollments.index');
        Route::get('/enrollments/create', [EnrollmentController::class, 'create'])->name('enrollments.create');
        Route::post('/enrollments', [EnrollmentController::class, 'store'])->name('enrollments.store');
        Route::get('/enrollments/{enrollment}', [EnrollmentController::class, 'show'])->name('enrollments.show');
        Route::get('/enrollments/{enrollment}/edit', [EnrollmentController::class, 'edit'])->name('enrollments.edit');
        Route::put('/enrollments/{enrollment}', [EnrollmentController::class, 'update'])->name('enrollments.update');
        Route::delete('/enrollments/{enrollment}', [EnrollmentController::class, 'destroy'])->name('enrollments.destroy');
        Route::post('/enrollments/{enrollment}/delete', [EnrollmentController::class, 'destroy'])->name('enrollments.destroy.post');
        Route::post('/enrollments/bulk', [EnrollmentController::class, 'bulkEnroll'])->name('enrollments.bulk');
        
        // Lecturer assignment routes
        Route::post('/assign-lecturers', [EnrollmentController::class, 'assignLecturers'])->name('assign.lecturers');
        Route::delete('/assign-lecturers/{unitId}', [EnrollmentController::class, 'destroyLecturerAssignment'])->name('assign-lecturers.destroy');
        Route::post('/assign-lecturers/{unitId}/delete', [EnrollmentController::class, 'destroyLecturerAssignment'])->name('assign-lecturers.destroy.post');
        Route::get('/lecturer-units/{lecturerId}', [EnrollmentController::class, 'getLecturerUnits'])->name('lecturer.units');
    });

    Route::get('/enroll', [EnrollmentController::class, 'create'])->name('enrollments.create');

    // Semesters routes
    Route::get('/semesters', [SemesterController::class, 'index'])->name('semesters.index');
    Route::get('/semesters/create', [SemesterController::class, 'create'])->name('semesters.create');
    Route::post('/semesters', [SemesterController::class, 'store'])->name('semesters.store');

    // Programs routes
    Route::get('/programs', [ProgramController::class, 'index'])->name('programs.index');
    Route::get('/programs/create', [ProgramController::class, 'create'])->name('programs.create');
    Route::post('/programs', [ProgramController::class, 'store'])->name('programs.store');
});

// Student download routes
Route::middleware(['auth', 'role:Student'])->group(function () {
    Route::get('/my-class/download', [ClassTimetableController::class, 'downloadStudentClassTimetable'])
        ->name('student.classes.download');
    Route::get('/my-exams/download', [ExamTimetableController::class, 'downloadStudentTimetable'])
        ->name('student.exams.download');
});

// Lecturer download route
Route::middleware(['auth', 'permission:download-own-timetable'])->group(function () {
    Route::get('/lecturer/timetable/download', [ExamTimetableController::class, 'downloadLecturerTimetable'])
        ->name('lecturer.timetable.download');
});

// Admin Routes - Admin role bypasses permission checks
Route::middleware(['auth', 'role:Admin'])->group(function () {
    // Keep the original dashboard
    Route::get('/admin', fn() => Inertia::render('Admin/Dashboard'))->name('admin.dashboard');
    
    // Roles and Permissions management
    Route::resource('roles', RoleController::class)->except(['show']);
    Route::resource('permissions', PermissionController::class)->except(['show']);

    // Semesters management
    Route::get('/schools/sces/bbit/semesters', [SemesterController::class, 'index'])->name('semesters.index');
    Route::get('/semesters/create', [SemesterController::class, 'create'])->name('semesters.create');
    Route::post('/semesters', [SemesterController::class, 'store'])->name('semesters.store');
    Route::get('/semesters/{semester}', [SemesterController::class, 'show'])->name('semesters.show');
    Route::get('/semesters/{semester}/edit', [SemesterController::class, 'edit'])->name('semesters.edit');
    Route::put('/semesters/{semester}', [SemesterController::class, 'update'])->name('semesters.update');
    Route::delete('/semesters/{semester}', [SemesterController::class, 'destroy'])->name('semesters.destroy');

    // Time Slots management
    Route::get('/timeslots', [TimeSlotController::class, 'index'])->name('timeslots.index');
    Route::post('/timeslots', [TimeSlotController::class, 'store'])->name('timeslots.store');
    Route::put('/timeslots/{timeSlot}', [TimeSlotController::class, 'update'])->name('timeslots.update');
    Route::delete('/timeslots/{timeSlot}', [TimeSlotController::class, 'destroy'])->name('timeslots.destroy');

    Route::resource('classtimetable', ClassTimetableController::class);
});

// ✅ Exam Office and Admin Routes
Route::middleware(['auth', 'role:Admin|Exam office'])->group(function () {
    // Keep the original dashboard
    Route::get('/exam-office', fn() => Inertia::render('ExamOffice/Dashboard'))->name('exam-office.dashboard');
    
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
    // Keep the original dashboard
    Route::get('/faculty-admin', fn() => Inertia::render('FacultyAdmin/Dashboard'))->name('faculty-admin.dashboard');
    
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
Route::middleware(['auth', 'role:Lecturer'])->group(function () {
    // Lecturer Dashboard
    Route::get('/lecturer/dashboard', [DashboardController::class, 'lecturerDashboard'])->name('lecturer.dashboard');
    
    // Lecturer Classes
    Route::get('/lecturer/my-classes', [LecturerController::class, 'myClasses'])->name('lecturer.my-classes');
    Route::get('/lecturer/my-classes/{unitId}/students', [LecturerController::class, 'classStudents'])->name('lecturer.class-students');
    
    // Lecturer Timetables
    Route::get('/lecturer/class-timetable', [LecturerController::class, 'viewClassTimetable'])->name('lecturer.class-timetable');
    Route::get('/lecturer/exam-supervision', [LecturerController::class, 'examSupervision'])->name('lecturer.exam-supervision');
    
    // Lecturer Profile
    Route::get('/lecturer/profile', [LecturerController::class, 'profile'])->name('lecturer.profile');
});

// ✅ Student Routes
Route::middleware(['auth', 'role:Student'])->group(function () {
    // Student Dashboard
    Route::get('/student', [DashboardController::class, 'studentDashboard'])->name('student.dashboard');
    
    // My Enrollments
    Route::get('/my-enrollments', [StudentController::class, 'myEnrollments'])->name('student.enrollments');
    
    // My Exams
    Route::get('/my-exams', [ExamTimetableController::class, 'viewStudentTimetable'])->name('student.exams');
    Route::get('/my-exams/{examtimetable}', [ExamTimetableController::class, 'viewStudentExamDetails'])
        ->name('student.exams.show');
        
    // My Class Timetable
    Route::get('/my-classes', [ClassTimetableController::class, 'viewStudentClassTimetable'])
        ->name('student.classes');
    
    // Student Exam Timetable
    Route::get('/student/exam-timetable', [ExamTimetableController::class, 'viewStudentTimetable'])
        ->name('student.exam-timetable');

    // Student Timetable
    Route::get('/student/timetable', [ClassTimetableController::class, 'viewStudentClassTimetable'])
        ->name('student.timetable');
});

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

// Notification routes
Route::middleware(['auth'])->group(function () {
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/send', [NotificationController::class, 'sendReminders'])->name('notifications.send');
    Route::get('/notifications/preview/{exam}', [NotificationController::class, 'previewNotifications'])->name('notifications.preview');
    
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

// Catch-all route for SPA (must be at the bottom)
Route::get('/{any}', function () {
    return Inertia::render('NotFound');
})->where('any', '.*')->name('not-found');
