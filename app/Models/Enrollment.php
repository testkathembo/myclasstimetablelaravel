<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Enrollment extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_code', 
        'unit_id',
        'semester_id',
        'lecturer_code',
    ];

    /**
     * Get the student that owns the enrollment.
     */
    public function student()
    {
        return $this->belongsTo(User::class, 'student_code', 'code'); // Reference `code` in `users`
    }

    /**
     * Get the unit that the enrollment belongs to.
     */
    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * Get the semester that the enrollment belongs to.
     */
    public function semester()
    {
        return $this->belongsTo(Semester::class);
    }

    /**
     * Get the lecturer assigned to this enrollment.
     */
    public function lecturer()
    {
        return $this->belongsTo(User::class, 'lecturer_code', 'code'); // Reference `code` in `users`
    }

    /**
     * Scope a query to filter enrollments by student code.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $studentCode
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByStudentCode($query, $studentCode)
    {
        return $query->where('student_code', $studentCode);
    }

    /**
     * Scope a query to filter enrollments by lecturer code.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $lecturerCode
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByLecturerCode($query, $lecturerCode)
    {
        return $query->where('lecturer_code', $lecturerCode);
    }
}