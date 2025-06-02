<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
        'code', // Make sure code is fillable
        'phone',
        // Add other fillable attributes as needed
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
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
     * Get the enrollments for the user as a student.
     */
    public function enrollments()
    {
        return $this->hasMany(Enrollment::class, 'student_code', 'code');
    }

    /**
     * Get the units taught by the lecturer.
     */
    public function taughtUnits()
    {
        return $this->hasMany(Enrollment::class, 'lecturer_code', 'code');
    }
    // For lecturers - relationship to units they teach
    public function units()
    {
        return $this->belongsToMany(Unit::class, 'lecturer_units')
                    ->withPivot('semester_id')
                    ->withTimestamps();
    }
    
    // Alternative if using a different pivot table structure
    public function lecturerUnits()
    {
        return $this->hasMany(LecturerUnit::class, 'lecturer_id');
    }
}
