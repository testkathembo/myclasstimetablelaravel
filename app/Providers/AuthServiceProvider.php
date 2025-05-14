<?php

namespace App\Providers;

use App\Models\Unit;
use App\Models\ClassTimetable;
use App\Models\ExamTimetable;
use App\Policies\UnitPolicy;
use App\Policies\ClassTimetablePolicy;
use App\Policies\ExamTimetablePolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Unit::class => UnitPolicy::class,
        ClassTimetable::class => ClassTimetablePolicy::class,
        ExamTimetable::class => ExamTimetablePolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();
    }
}
