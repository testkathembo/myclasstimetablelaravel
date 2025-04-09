<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\FacultyController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\ClassroomController;
use App\Http\Controllers\SemesterController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\EnrollmentController;
use App\Http\Controllers\TimeSlotController;
use App\Http\Controllers\ExamTimetableController; // Import the ExamTimetableController
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;

// ✅ Laravel Breeze Login Route
Route::post('/login', [AuthenticatedSessionController::class, 'create'])->name('login');

// ✅ Home Page
Route::get('/', [HomeController::class, 'index'])->name('home');

// ✅ Secure Dashboard
Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

// ✅ Protected Routes for Authenticated Users
Route::middleware(['auth'])->group(function () {
    
    /** ========================
     *  🔹 Profile (Accessible to All Users)
     *  ======================== */
    Route::prefix('profile')->group(function () {
        Route::get('/', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/', [ProfileController::class, 'update'])->name('profile.update');
        Route::delete('/', [ProfileController::class, 'destroy'])->name('profile.destroy');
    });

    /** ========================
     *  🔹 Timetable Routes
     *  ======================== */
    Route::get('/timetable', [SemesterController::class, 'viewTimetable'])->name('timetable.view');

    /** ========================
     *  🔹 Admin Routes
     *  ======================== */
    Route::prefix('admin')->group(function () {
        Route::get('/dashboard', function () {
            return Inertia::render('Admin/Dashboard');
        })->name('admin.dashboard');

    });

    // Users
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    Route::get('/users/{user}', [UserController::class, 'show'])->name('users.show');
    Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
    Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');

    // Enrollments
    Route::get('/enrollments', [EnrollmentController::class, 'index'])->name('enrollments.index');
    Route::get('/enrollments/create', [EnrollmentController::class, 'create'])->name('enrollments.create');
    Route::post('/enrollments', [EnrollmentController::class, 'store'])->name('enrollments.store');
    Route::get('/enrollments/{enrollment}', [EnrollmentController::class, 'show'])->name('enrollments.show');
    Route::get('/enrollments/{enrollment}/edit', [EnrollmentController::class, 'edit'])->name('enrollments.edit');
    Route::put('/enrollments/{enrollment}', [EnrollmentController::class, 'update'])->name('enrollments.update');
    Route::delete('/enrollments/{enrollment}', [EnrollmentController::class, 'destroy'])->name('enrollments.destroy');

    // Units
    Route::get('/units', [UnitController::class, 'index'])->name('units.index');
    Route::get('/units/create', [UnitController::class, 'create'])->name('units.create');
    Route::post('/units', [UnitController::class, 'store'])->name('units.store');
    Route::get('/units/{unit}', [UnitController::class, 'show'])->name('units.show');
    Route::get('/units/{unit}/edit', [UnitController::class, 'edit'])->name('units.edit');
    Route::put('/units/{unit}', [UnitController::class, 'update'])->name('units.update');
    Route::delete('/units/{unit}', [UnitController::class, 'destroy'])->name('units.destroy');

    // Classrooms
    Route::get('/classrooms', [ClassroomController::class, 'index'])->name('classrooms.index');
    Route::get('/classrooms/create', [ClassroomController::class, 'create'])->name('classrooms.create');
    Route::post('/classrooms', [ClassroomController::class, 'store'])->name('classrooms.store');
    Route::get('/classrooms/{classroom}', [ClassroomController::class, 'show'])->name('classrooms.show');
    Route::get('/classrooms/{classroom}/edit', [ClassroomController::class, 'edit'])->name('classrooms.edit');
    Route::put('/classrooms/{classroom}', [ClassroomController::class, 'update'])->name('classrooms.update');
    Route::delete('/classrooms/{classroom}', [ClassroomController::class, 'destroy'])->name('classrooms.destroy');

    // Faculties
    Route::get('/faculties', [FacultyController::class, 'index'])->name('faculties.index');
    Route::get('/faculties/create', [FacultyController::class, 'create'])->name('faculties.create');
    Route::post('/faculties', [FacultyController::class, 'store'])->name('faculties.store');
    Route::get('/faculties/{faculty}', [FacultyController::class, 'show'])->name('faculties.show');
    Route::get('/faculties/{faculty}/edit', [FacultyController::class, 'edit'])->name('faculties.edit');
    Route::put('/faculties/{faculty}', [FacultyController::class, 'update'])->name('faculties.update');
    Route::delete('/faculties/{faculty}', [FacultyController::class, 'destroy'])->name('faculties.destroy');

    // Semesters
    Route::get('/semesters', [SemesterController::class, 'index'])->name('semesters.index');
    Route::get('/semesters/create', [SemesterController::class, 'create'])->name('semesters.create');
    Route::post('/semesters', [SemesterController::class, 'store'])->name('semesters.store');
    Route::get('/semesters/{semester}', [SemesterController::class, 'show'])->name('semesters.show');
    Route::get('/semesters/{semester}/edit', [SemesterController::class, 'edit'])->name('semesters.edit');
    Route::put('/semesters/{semester}', [SemesterController::class, 'update'])->name('semesters.update');
    Route::delete('/semesters/{semester}', [SemesterController::class, 'destroy'])->name('semesters.destroy');
    
    // Timeslotes
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

    // Exam Timetable
    Route::get('/examtimetable', [ExamTimetableController::class, 'index'])->name('exam-timetable.index');
    Route::get('/exam-timetable/view', [ExamTimetableController::class, 'view'])->name('exam-timetable.view');

    /** ========================
     *  🔹 Exam Timetable Routes
     *  ======================== */
    Route::prefix('exam-timetables')->group(function () {
        Route::get('/', [ExamTimetableController::class, 'index'])->name('exam-timetables.index');
        Route::post('/', [ExamTimetableController::class, 'store'])->name('exam-timetables.store');
        Route::put('/{id}', [ExamTimetableController::class, 'update'])->name('exam-timetables.update');
        Route::delete('/{id}', [ExamTimetableController::class, 'destroy'])->name('exam-timetables.destroy');
    });

    // Logout
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    
});

// ✅ Authentication Routes
require __DIR__.'/auth.php';
