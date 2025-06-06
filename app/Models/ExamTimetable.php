<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExamTimetable extends Model
{
    use HasFactory;

    protected $fillable = [
        'semester_id',
        'class_id',
        'unit_id',
        'day',
        'date',
        'start_time',
        'end_time',
        'venue',
        'location',
        'no',
        'chief_invigilator',
        'program_id',
        'school_id',
    ];

    protected $casts = [
        'date' => 'date',
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
    ];

    /**
     * Get the semester that owns the exam timetable.
     */
    public function semester()
    {
        return $this->belongsTo(Semester::class);
    }

    /**
     * Get the class that owns the exam timetable.
     */
    public function class()
    {
        return $this->belongsTo(ClassModel::class, 'class_id');
    }

    /**
     * Get the unit that owns the exam timetable.
     */
    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * Get the program that owns the exam timetable.
     */
    public function program()
    {
        return $this->belongsTo(Program::class);
    }

    /**
     * Get the school that owns the exam timetable.
     */
    public function school()
    {
        return $this->belongsTo(School::class);
    }
}
