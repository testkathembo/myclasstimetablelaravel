<?php

namespace App\Policies;

use App\Models\Enrollment;
use App\Models\User;

class EnrollmentPolicy
{
    /**
     * Determine if the user can view any enrollments.
     */
    public function viewAny(User $user)
    {
        return $user->hasRole('Admin') || $user->hasRole('Faculty Admin');
    }

    /**
     * Determine if the user can view a specific enrollment.
     */
    public function view(User $user, Enrollment $enrollment)
    {
        return $user->hasRole('Admin') || $user->code === $enrollment->student_code;
    }

    // Add other methods as needed (e.g., create, update, delete)
}
