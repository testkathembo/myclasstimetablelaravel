<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'code',
        'name',
        'description',
        'semester_id',
        'program_id',
        'school_id',
        'credits',
        'credit_hours', // Ensure this is included
        'is_active',
    ];

    /**
     * Get the semester that owns the unit.
     */
    public function semester()
    {
        return $this->belongsTo(Semester::class);
    }

    /**
     * Get the program that owns the unit.
     */
    public function program()
    {
        return $this->belongsTo(Program::class);
    }

    /**
     * Get the school that owns the unit.
     */
    public function school()
    {
        return $this->belongsTo(School::class);
    }

    /**
     * Get the classes for the unit.
     */
    public function classes()
    {
        return $this->belongsToMany(ClassModel::class, 'semester_unit', 'unit_id', 'class_id');
    }

    /**
     * Get the semesters for the unit.
     */
    public function semesters()
    {
        return $this->belongsToMany(Semester::class, 'semester_unit', 'unit_id', 'semester_id');
    }

    /**
     * Get the enrollments for the unit.
     */
    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }

    /**
     * Get the lecturer for the unit.
     */
    public function lecturer()
    {
        return $this->belongsTo(User::class, 'lecturer_id');
    }

    /**
     * Define a relationship with the ClassTimetable model.
     */
    public function classTimetables()
    {
        return $this->hasMany(ClassTimetable::class);
    }

    // ✅ ADDED: Get lecturers assigned to this unit through enrollments
    public function lecturers()
    {
        return $this->hasManyThrough(
            User::class,
            Enrollment::class,
            'unit_id',       // Foreign key on enrollments table
            'code',          // Foreign key on users table
            'id',            // Local key on units table
            'lecturer_code'  // Local key on enrollments table
        )->whereNotNull('enrollments.lecturer_code');
    }

    // ✅ ADDED: Get students enrolled in this unit through enrollments
    public function students()
    {
        return $this->hasManyThrough(
            User::class,
            Enrollment::class,
            'unit_id',      // Foreign key on enrollments table
            'code',         // Foreign key on users table
            'id',           // Local key on units table
            'student_code'  // Local key on enrollments table
        )->whereNotNull('enrollments.student_code');
    }
}
