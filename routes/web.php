<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\FacultyController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\ClassroomController;
use App\Http\Controllers\SemesterController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\HomeController;
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

       Route::resource('users', UserController::class); // Ensure this route exists
        Route::resource('faculties', FacultyController::class);
        Route::resource('units', UnitController::class);
        Route::resource('classrooms', ClassroomController::class);
    });

    Route::resource('users', UserController::class); // Ensure this route exists
});

// ✅ Authentication Routes
require __DIR__.'/auth.php';