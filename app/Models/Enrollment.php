<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Enrollment extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_code',
        'lecturer_code',
        'group',
        'unit_id',
        'semester_id',
        'program_id',
        'school_id',
    ];

    /**
     * Get the unit that owns the enrollment.
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * Get the semester that owns the enrollment.
     */
    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    /**
     * Get the program that owns the enrollment.
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    /**
     * Get the school that owns the enrollment.
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * Get the student that owns the enrollment.
     * This assumes you have a User or Student model with a 'code' field.
     */
    public function student()
    {
        return $this->belongsTo(User::class, 'student_code', 'code');
    }

    /**
     * Get the lecturer that owns the enrollment.
     * This assumes you have a User or Lecturer model with a 'code' field.
     */
    public function lecturer()
    {
        return $this->belongsTo(User::class, 'lecturer_code', 'code');
    }
}