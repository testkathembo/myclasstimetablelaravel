<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles;

    protected $fillable = [
        'first_name',
        'last_name',
        'faculty',
        'email',
        'phone',
        'code',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
    ];
   
    /**
     * Get the user's full name.
     *
     * @return string
     */
    public function getNameAttribute()
    {
        return "{$this->first_name} {$this->last_name}";
    }
   
    /**
     * Check if the user is an admin.
     *
     * @return bool
     */
    public function isAdmin()
    {
        return $this->hasRole('Admin');
    }

    /**
     * Check if the user is a student.
     *
     * @return bool
     */
    public function isStudent()
    {
        return $this->hasRole('Student');
    }

    /**
     * Check if the user is a lecturer.
     *
     * @return bool
     */
    public function isLecturer()
    {
        return $this->hasRole('Lecturer');
    }
   
    /**
     * Get the faculty that the user belongs to.
     */
    public function faculty()
    {
        return $this->belongsTo(Faculty::class);
    }
   
    /**
     * Get the units that the student is enrolled in.
     */
    public function enrolledUnits()
    {
        if (!$this->hasRole('Student')) {
            return collect();
        }
       
        return $this->belongsToMany(Unit::class, 'enrollments', 'student_code', 'unit_id')
            ->withPivot(['semester_id', 'lecturer_code'])
            ->withTimestamps();
    }
   
    /**
     * Get the units that the lecturer is assigned to teach.
     */
    public function assignedUnits()
    {
        if (!$this->hasRole('Lecturer')) {
            return collect();
        }
       
        return $this->belongsToMany(Unit::class, 'enrollments', 'lecturer_code', 'unit_id')
            ->withPivot(['semester_id'])
            ->withTimestamps();
    }
   
    /**
     * Get all enrollments for a student.
     */
    public function enrollments()
    {
        return $this->hasMany(Enrollment::class, 'student_code', 'code');
    }

    /**
     * Get all units taught by a lecturer.
     */
    public function taughtUnits()
    {
        return $this->hasMany(Enrollment::class, 'lecturer_code', 'code');
    }

    /**
     * Find a user by their code.
     *
     * @param string $code
     * @return \App\Models\User|null
     */
    public static function findByCode($code)
    {
        return static::where('code', $code)->first();
    }
    /**
     * Get the notification preferences for the user.
     */
    public function notificationPreference()
    {
        return $this->hasOne(NotificationPreference::class);
    }

    /**
     * Create default notification preferences for a new user.
     */
    public static function boot()
    {
        parent::boot();

        static::created(function ($user) {
            $user->notificationPreference()->create([
                'email_enabled' => true,
                'sms_enabled' => false,
                'push_enabled' => false,
                'hours_before' => 24,
                'reminder_enabled' => true,
            ]);
        });
    }
}
