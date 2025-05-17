<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Semester extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'is_active',
        // Add any other fields your semester model has
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the timetables for the semester.
     */
    public function timetables()
    {
        return $this->hasMany(Timetable::class);
    }

    /**
     * Get the enrollments for the semester.
     */
    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }

    // Update the units relationship to use the pivot table
    public function units()
    {
        return $this->belongsToMany(Unit::class, 'semester_unit', 'semester_id', 'unit_id');
    }

    // Add a relationship for classes through the semester_unit table
    public function classes()
    {
        return $this->belongsToMany(ClassModel::class, 'semester_unit', 'semester_id', 'class_id');
    }

    /**
     * Define a relationship with the ClassTimetable model.
     */
    public function classTimetables()
    {
        return $this->hasMany(ClassTimetable::class); // Adjust the model name if necessary
    }

    /**
     * Define a relationship with the ExamTimetable model.
     */
    public function examTimetables()
    {
        return $this->hasMany(ExamTimetable::class); // Adjust the model name if necessary
    }
}
