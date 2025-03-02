<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\FacultyController;

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

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    // Route for Faculty
    Route::get('/faculties', [FacultyController::class, 'index'])->name('faculties.index');
    Route::get('/faculties/create', [FacultyController::class, 'create'])->name('faculties.create');
    Route::post('/faculties', [FacultyController::class, 'store'])->name('faculties.store');
    Route::get('/faculties/{faculty}/edit', [FacultyController::class, 'edit'])->name('faculties.edit');
    Route::patch('/faculties/{faculty}', [FacultyController::class, 'update'])->name('faculties.update');
    Route::delete('/faculties/{faculty}', [FacultyController::class, 'destroy'])->name('faculties.destroy');
});




require __DIR__.'/auth.php';
