<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use App\Models\ExamTimetable; // Import ExamTimetable model
use App\Observers\ExamTimetableObserver; // Import ExamTimetableObserver
use App\Models\ClassTimetable; // Import ClassTimetable model
use App\Observers\ClassTimetableObserver; // Import ClassTimetableObserver

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Fix for files binding
        $this->app->singleton('files', function ($app) {
            return new \Illuminate\Filesystem\Filesystem();
        });

        // Fix for 'cache.store' binding
        $this->app->singleton('cache.store', function ($app) {
            return $app['cache']->driver();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Share auth user with all Inertia views
        Inertia::share([
            'auth.user' => function () {
                $user = Auth::user();
                return $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'roles' => $user->roles->map->only(['id', 'name']),
                    'permissions' => $user->getAllPermissions()->pluck('name'),
                ] : null;
            },
        ]);

        // Register the ExamTimetableObserver
        ExamTimetable::observe(ExamTimetableObserver::class);
        
        // Register the ClassTimetableObserver
        ClassTimetable::observe(ClassTimetableObserver::class);
    
    }
}
