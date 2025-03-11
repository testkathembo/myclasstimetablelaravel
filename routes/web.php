<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\FacultyController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\ClassroomController;
use App\Http\Controllers\SemesterController;
use App\Http\Controllers\EnrollmentController;

// Laravel Breeze Logout Route
Route::post('/login', [AuthenticatedSessionController::class, 'create'])->name('login');

Route::get('/', function () {
    return Inertia::render('Home', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::middleware(['auth'])->group(function () {
// Super Admin Dashboard
Route::middleware(['role:SuperAdmin'])->group(function () {
    Route::get('/admin/dashboard', function () {
        return Inertia::render('Admin/Dashboard');
    })->name('admin.dashboard');

    Route::resource('users', UserController::class)->middleware('permission:manage users');
});

// SchoolAdmin Routes
Route::middleware(['role:SchoolAdmin'])->group(function () {
    Route::get('/schooladmin/dashboard', function () {
        return Inertia::render('SchoolAdmin/Dashboard');
    })->name('schooladmin.dashboard');

    Route::resource('faculties', FacultyController::class)->middleware('permission:manage faculties');
    Route::resource('units', UnitController::class)->middleware('permission:manage units');
    Route::resource('classrooms', ClassroomController::class)->middleware('permission:manage classrooms');
    Route::resource('semesters', SemesterController::class)->middleware('permission:manage semesters');
    Route::resource('enrollment-groups', EnrollmentController::class)->middleware('permission:manage enrollment groups');
});


Route::middleware(['role:ExamOffice'])->group(function () {
    Route::get('/examoffice/dashboard', function () {
        return Inertia::render('ExamOffice/Dashboard');
    })->name('examoffice.dashboard');

    Route::resource('timetable', TimetableController::class)->middleware('permission:manage timetable');
});

Route::middleware(['role:Lecturer'])->group(function () {
    Route::get('/lecturer/dashboard', function () {
        return Inertia::render('Lecturer/Dashboard');
    })->name('lecturer.dashboard');

    Route::resource('timetable', TimetableController::class)->middleware('permission:view timetable');
});

Route::middleware(['role:Student'])->group(function () {
    Route::get('/student/dashboard', function () {
        return Inertia::render('Student/Dashboard');
    })->name('student.dashboard');

    Route::resource('timetable', TimetableController::class)->middleware('permission:view timetable');
});

Route::get('/profile', [ProfileController::class, 'index'])->name('profile');
Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');   


   
});



require __DIR__.'/auth.php';